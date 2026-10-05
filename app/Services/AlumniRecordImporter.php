<?php

namespace App\Services;

use App\Models\AlumniRecord;
use App\Models\Import;
use App\Models\Programme;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Import institute graduate records from CSV (SRS 94):
 * upload -> validate -> preview -> confirm -> queued import -> summary.
 *
 * Rows are upserted by roll number, so re-importing a corrected file is
 * safe. The uploaded file lives on the private disk and is deleted once
 * the import finishes.
 */
class AlumniRecordImporter
{
    public const COLUMNS = ['roll_number', 'name', 'programme_code', 'admission_year', 'graduation_year', 'date_of_birth', 'email'];

    public const REQUIRED = ['roll_number', 'name', 'programme_code', 'graduation_year'];

    public const MAX_ROWS = 20000;

    private const MAX_ERRORS = 50;

    public function __construct(
        private readonly AlumniVerificationService $verification,
        private readonly AuditLogger $audit,
    ) {}

    /** Store the upload and validate it without writing any records. */
    public function preview(User $user, UploadedFile $file): array
    {
        $path = $file->storeAs('imports', Str::uuid().'.csv', 'local');

        $import = new Import;
        $import->forceFill([
            'user_id' => $user->id,
            'type' => 'alumni_records',
            'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
            'path' => $path,
            'status' => 'previewed',
        ]);

        $result = $this->validate($path);
        $import->forceFill([
            'total_rows' => $result['total'],
            'valid_rows' => count($result['valid']),
            'errors' => array_slice($result['errors'], 0, self::MAX_ERRORS),
        ])->save();

        $existing = AlumniRecord::whereIn('roll_number', array_column($result['valid'], 'roll_number'))->count();

        return [
            'import' => $import,
            'sample' => array_slice($result['valid'], 0, 10),
            'new' => count($result['valid']) - $existing,
            'updates' => $existing,
            'duplicates' => $result['duplicates'],
        ];
    }

    /** Run the import (called from the queued job). */
    public function run(Import $import): void
    {
        $import->forceFill(['status' => 'processing'])->save();

        try {
            $rows = $this->validate($import->path)['valid'];
            $created = $updated = 0;

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::transaction(function () use ($chunk, &$created, &$updated) {
                    $existing = AlumniRecord::whereIn('roll_number', array_column($chunk, 'roll_number'))->pluck('id', 'roll_number');
                    foreach ($chunk as $row) {
                        AlumniRecord::updateOrCreate(['roll_number' => $row['roll_number']], $row);
                        isset($existing[$row['roll_number']]) ? $updated++ : $created++;
                    }
                });
            }

            $import->forceFill(['status' => 'completed', 'created_rows' => $created, 'updated_rows' => $updated, 'completed_at' => now()])->save();
            $this->audit->record('alumni_records.imported', 'alumni', $import, null, ['created' => $created, 'updated' => $updated], $import->user_id);
        } catch (Throwable $e) {
            $import->forceFill(['status' => 'failed', 'errors' => [['row' => 0, 'message' => 'Import failed: '.Str::limit($e->getMessage(), 200)]]])->save();
            throw $e;
        } finally {
            Storage::disk('local')->delete($import->path);
        }
    }

    /** @return array{total: int, valid: list<array<string, mixed>>, errors: list<array{row: int, message: string}>, duplicates: int} */
    private function validate(string $path): array
    {
        $handle = Storage::disk('local')->readStream($path) ?: throw new RuntimeException('Upload not found.');
        $programmes = Programme::pluck('id', 'code')->mapWithKeys(fn ($id, $code) => [Str::upper($code) => $id]);

        $header = fgetcsv($handle);
        $header = array_map(fn ($h) => Str::snake(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $header ?: []);
        $missing = array_diff(self::REQUIRED, $header);
        if ($missing !== []) {
            fclose($handle);

            return ['total' => 0, 'valid' => [], 'errors' => [['row' => 1, 'message' => 'Missing columns: '.implode(', ', $missing)]], 'duplicates' => 0];
        }

        $valid = $errors = $seen = [];
        $total = $duplicates = 0;
        $line = 1;

        while (($cells = fgetcsv($handle)) !== false) {
            $line++;
            if ($cells === [null] || implode('', $cells) === '') {
                continue;
            }
            if (++$total > self::MAX_ROWS) {
                $errors[] = ['row' => $line, 'message' => 'File exceeds '.self::MAX_ROWS.' rows; split it and import in parts.'];
                break;
            }

            $row = array_combine($header, array_pad(array_slice($cells, 0, count($header)), count($header), null));
            [$record, $problem] = $this->normalise($row, $programmes->all());

            if ($problem) {
                $errors[] = ['row' => $line, 'message' => $problem];
            } elseif (isset($seen[$record['roll_number']])) {
                $duplicates++;
                $errors[] = ['row' => $line, 'message' => "Duplicate roll number {$record['roll_number']} (first on row {$seen[$record['roll_number']]})."];
            } else {
                $seen[$record['roll_number']] = $line;
                $valid[] = $record;
            }
        }
        fclose($handle);

        return compact('total', 'valid', 'errors', 'duplicates');
    }

    /** @return array{0: array<string, mixed>|null, 1: string|null} */
    private function normalise(array $row, array $programmes): array
    {
        $roll = $this->verification->normaliseRoll((string) ($row['roll_number'] ?? ''));
        $name = trim((string) ($row['name'] ?? ''));
        $code = Str::upper(trim((string) ($row['programme_code'] ?? '')));
        $grad = trim((string) ($row['graduation_year'] ?? ''));
        $adm = trim((string) ($row['admission_year'] ?? ''));
        $email = trim((string) ($row['email'] ?? ''));
        $dob = trim((string) ($row['date_of_birth'] ?? ''));

        return match (true) {
            $roll === '' || strlen($roll) > 30 || ! preg_match('/^[A-Z0-9\-\/]+$/', $roll) => [null, 'Invalid roll number.'],
            $name === '' || mb_strlen($name) > 255 => [null, 'Name is required.'],
            ! isset($programmes[$code]) => [null, "Unknown programme code \"{$code}\"."],
            ! ctype_digit($grad) || (int) $grad < 1998 || (int) $grad > now()->year + 1 => [null, 'Graduation year must be between 1998 and next year.'],
            $adm !== '' && (! ctype_digit($adm) || (int) $adm > (int) $grad) => [null, 'Admission year must be a year no later than graduation.'],
            $email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL) => [null, 'Invalid email address.'],
            $dob !== '' && ! $this->parseDate($dob) => [null, 'Date of birth must be YYYY-MM-DD.'],
            default => [[
                'roll_number' => $roll,
                'name' => $name,
                'programme_id' => $programmes[$code],
                'admission_year' => $adm !== '' ? (int) $adm : null,
                'graduation_year' => (int) $grad,
                'date_of_birth' => $dob !== '' ? $this->parseDate($dob) : null,
                'email' => $email !== '' ? Str::lower($email) : null,
            ], null],
        };
    }

    private function parseDate(string $value): ?string
    {
        try {
            $d = Carbon::createFromFormat('!Y-m-d', $value);

            return $d && $d->format('Y-m-d') === $value ? $value : null;
        } catch (Throwable) {
            return null;
        }
    }
}
