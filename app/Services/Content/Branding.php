<?php

namespace App\Services\Content;

use App\Models\StoredFile;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Uploads\FileUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Portal name, tagline and logos. A logo slot without a custom image falls
 * back to the header logo, and the header logo to the built-in mark, so
 * every place always has something to show.
 *
 * Logo files are private StoredFiles (purpose "brand_logo"); PublicMedia
 * serves one to visitors only while the published branding uses it, and
 * StoredFilePolicy lets branding managers see drafts for the preview.
 */
class Branding extends PublishableSettings
{
    public const PURPOSE = 'brand_logo';

    public const SLOTS = [
        'header' => ['label' => 'Header logo', 'hint' => 'Site header and the app sidebar. The fallback for every other place.'],
        'mobile' => ['label' => 'Mobile logo', 'hint' => 'Headers on phones. A compact mark works best.'],
        'footer' => ['label' => 'Footer logo', 'hint' => 'The landing-page footer.'],
        'login' => ['label' => 'Login-page logo', 'hint' => 'Sign-in, registration and other account pages.'],
        'favicon' => ['label' => 'Favicon', 'hint' => 'The browser tab icon. A square PNG (at least 32 × 32) works in every browser; SVG works in most.'],
    ];

    private const CACHE_KEY = 'branding.v1';

    public function __construct(AuditLogger $audit, private readonly FileUploadService $uploads)
    {
        parent::__construct($audit);
    }

    public function area(): string
    {
        return 'branding';
    }

    protected function auditPrefix(): string
    {
        return 'branding';
    }

    public function defaults(): array
    {
        return ['name' => 'Alumni Connect', 'tagline' => 'ABV-IIITM Gwalior', 'show_name' => true]
            + array_map(fn () => null, self::SLOTS);
    }

    protected function normalize(array $values): array
    {
        $d = $this->defaults();
        $clean = fn ($v, int $max) => Str::substr(trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $v) ?? ''), 0, $max);
        $out = [
            'name' => $clean($values['name'] ?? $d['name'], 60) ?: $d['name'],
            'tagline' => $clean($values['tagline'] ?? $d['tagline'], 80),
            'show_name' => (bool) ($values['show_name'] ?? true),
        ];
        // Logo slots hold the id of an existing brand-logo file, or null.
        $ids = array_filter(array_map(fn ($s) => is_string($values[$s] ?? null) ? $values[$s] : null, array_keys(self::SLOTS)));
        $valid = $ids ? StoredFile::whereIn('id', $ids)->where('purpose', self::PURPOSE)->pluck('id')->all() : [];
        foreach (array_keys(self::SLOTS) as $slot) {
            $id = $values[$slot] ?? null;
            $out[$slot] = in_array($id, $valid, true) ? $id : null;
        }

        return $out;
    }

    protected function flushPublished(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    protected function auditValue(string $key, mixed $value): mixed
    {
        if (! isset(self::SLOTS[$key])) {
            return parent::auditValue($key, $value);
        }
        if ($value === null) {
            return 'default';
        }
        $file = StoredFile::find($value);

        return $file ? "file {$file->id} ({$file->original_name})" : "file {$value}";
    }

    /** Store an uploaded logo and put it in the draft. The old file is kept, so earlier versions can be restored. */
    public function uploadLogo(string $slot, UploadedFile $upload, User $by): StoredFile
    {
        abort_unless(isset(self::SLOTS[$slot]), 404);
        $file = $this->uploads->storeLogo($upload, $by, self::PURPOSE, favicon: $slot === 'favicon');
        $this->saveDraft([$slot => $file->id], $by);

        return $file;
    }

    public function removeLogo(string $slot, User $by): void
    {
        abort_unless(isset(self::SLOTS[$slot]), 404);
        $this->saveDraft([$slot => null], $by);
    }

    /** Is this file part of the published branding? (PublicMedia) */
    public function isPublishedLogo(StoredFile $file): bool
    {
        return in_array($file->id, $this->forPages()['file_ids'], true);
    }

    /**
     * Shared with every page (HandleInertiaRequests), cached until the next publish.
     *
     * @return array{name: string, tagline: string, show_name: bool, logos: array<string, ?string>, favicon: ?string, file_ids: list<string>}
     */
    public function forPages(bool $draft = false): array
    {
        if ($draft) {
            return $this->resolve($this->draft());
        }

        return Cache::rememberForever(self::CACHE_KEY, fn () => $this->resolve($this->live()));
    }

    /** Editor view: each slot's own file (no fallbacks), with a URL for the preview. */
    public function slotsForEditor(): array
    {
        $draft = $this->draft();
        $files = StoredFile::whereIn('id', array_filter(array_map(fn ($s) => $draft[$s], array_keys(self::SLOTS))))->get()->keyBy('id');

        return collect(self::SLOTS)->map(fn ($meta, $slot) => [
            'key' => $slot,
            ...$meta,
            'file' => ($f = $files[$draft[$slot]] ?? null) ? ['url' => $this->url($f->id), 'name' => $f->original_name, 'width' => $f->width, 'height' => $f->height, 'kb' => (int) ceil($f->size / 1024), 'svg' => $f->mime === 'image/svg+xml'] : null,
        ])->values()->all();
    }

    private function resolve(array $v): array
    {
        $header = $v['header'];

        return [
            'name' => $v['name'],
            'tagline' => $v['tagline'],
            'show_name' => $v['show_name'],
            'logos' => [
                'header' => $this->url($header),
                'mobile' => $this->url($v['mobile'] ?? $header),
                'footer' => $this->url($v['footer'] ?? $header),
                'login' => $this->url($v['login'] ?? $header),
            ],
            'favicon' => $this->url($v['favicon']),
            'file_ids' => array_values(array_filter(array_map(fn ($s) => $v[$s], array_keys(self::SLOTS)))),
        ];
    }

    private function url(?string $fileId): ?string
    {
        return $fileId ? route('files.show', $fileId, absolute: false) : null;
    }
}
