<?php

namespace App\Services\Uploads;

use App\Models\StoredFile;
use App\Models\User;
use GdImage;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The only way files enter the system (SRS 71-73).
 *
 * - The type is sniffed from the bytes; the client's extension and
 *   Content-Type are ignored.
 * - Images are decoded and re-encoded (WebP), which strips EXIF/GPS and
 *   anything smuggled after the image data, then resized and thumbnailed.
 *   SVG is never accepted.
 * - Documents are limited to PDF, checked by magic bytes.
 * - Everything is scanned, given a random name, and stored on the private
 *   disk outside the web root.
 */
class FileUploadService
{
    private const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    private const DOCUMENT_MIMES = ['application/pdf'];

    public function __construct(private readonly MalwareScanner $scanner) {}

    public function storeImage(UploadedFile $upload, ?User $owner, string $purpose, string $visibility = StoredFile::MEMBERS, int $maxSide = 1600, int $thumbSide = 320): StoredFile
    {
        $tmp = $this->checkedPath($upload, config('security.uploads.max_image_kb'));
        $mime = $this->sniff($tmp);
        if (! in_array($mime, self::IMAGE_MIMES, true)) {
            throw new UploadRejected('Upload a JPEG, PNG, WebP or GIF image.');
        }

        $info = @getimagesize($tmp);
        if (! $info || $info[0] < 1 || $info[1] < 1) {
            throw new UploadRejected('That image could not be read.');
        }
        if (config('security.uploads.max_pixels') < $info[0] * $info[1]) {
            throw new UploadRejected('That image is too large. Please use one under 40 megapixels.');
        }

        $scan = $this->scanner->scan($tmp);

        $image = @imagecreatefromstring((string) file_get_contents($tmp));
        if (! $image instanceof GdImage) {
            throw new UploadRejected('That image could not be read.');
        }
        $image = $this->orient($image, $tmp, $mime);

        $main = $this->resize($image, $maxSide);
        $thumb = $this->resize($image, $thumbSide, square: true);

        $base = 'files/images/'.now()->format('Y/m').'/'.Str::ulid();
        $path = $this->putWebp($main, "{$base}.webp");
        $thumbPath = $this->putWebp($thumb, "{$base}-thumb.webp");

        return $this->record($upload, $owner, $purpose, 'image', $path, $thumbPath, 'image/webp', imagesx($main), imagesy($main), $visibility, $scan);
    }

    /**
     * An image that must end up at or under $maxKb (event, news and profile
     * images). Pipeline: real-content type check, decode, dimension check,
     * orient, resize, re-encode to WebP (which strips all metadata), then
     * step quality and size down until the result fits. If it still can't
     * fit, the upload is rejected. The original is never stored.
     *
     * @param  list<string>  $mimes  accepted source types
     */
    public function storeOptimizedImage(UploadedFile $upload, ?User $owner, string $purpose, string $visibility, int $maxKb, string $label = 'Image', int $maxSide = 1920, int $thumbSide = 400, array $mimes = ['image/jpeg', 'image/png', 'image/webp']): StoredFile
    {
        $tmp = $this->checkedPath($upload, config('security.uploads.max_image_kb'));
        $mime = $this->sniff($tmp);
        if (! in_array($mime, $mimes, true)) {
            throw new UploadRejected("{$label} must be a JPG, PNG or WebP image.");
        }
        $info = @getimagesize($tmp);
        if (! $info || $info[0] < 1 || $info[1] < 1) {
            throw new UploadRejected('That image could not be read.');
        }
        if (config('security.uploads.max_pixels') < $info[0] * $info[1]) {
            throw new UploadRejected('That image is too large. Please use one under 40 megapixels.');
        }

        $scan = $this->scanner->scan($tmp);
        $image = @imagecreatefromstring((string) file_get_contents($tmp));
        if (! $image instanceof GdImage) {
            throw new UploadRejected('That image could not be read.');
        }
        $image = $this->orient($image, $tmp, $mime);

        // Smallest acceptable encoding: quality first, then dimensions.
        $limit = $maxKb * 1024;
        $bytes = null;
        $main = null;
        for ($side = $maxSide; $side >= 480 && $bytes === null; $side = (int) ($side * 0.8)) {
            $main = $this->resize($image, $side);
            foreach ([82, 72, 62, 52, 42] as $quality) {
                $encoded = $this->encodeWebp($main, $quality);
                if (strlen($encoded) <= $limit) {
                    $bytes = $encoded;
                    break;
                }
            }
        }
        if ($bytes === null) {
            throw new UploadRejected("{$label} must be {$maxKb} KB or smaller, and this one can't be compressed enough. Try a simpler or smaller image.");
        }

        $base = 'files/images/'.now()->format('Y/m').'/'.Str::ulid();
        Storage::disk('local')->put("{$base}.webp", $bytes);
        $thumbPath = $this->putWebp($this->resize($image, $thumbSide, square: true), "{$base}-thumb.webp");

        return $this->record($upload, $owner, $purpose, 'image', "{$base}.webp", $thumbPath, 'image/webp', imagesx($main), imagesy($main), $visibility, $scan);
    }

    public function storeDocument(UploadedFile $upload, ?User $owner, string $purpose, string $visibility = StoredFile::PRIVATE): StoredFile
    {
        $tmp = $this->checkedPath($upload, config('security.uploads.max_document_kb'));
        $mime = $this->sniff($tmp);
        $head = (string) file_get_contents($tmp, false, null, 0, 5);

        if (! in_array($mime, self::DOCUMENT_MIMES, true) || $head !== '%PDF-') {
            throw new UploadRejected('Upload a PDF document.');
        }

        $scan = $this->scanner->scan($tmp);
        $path = 'files/documents/'.now()->format('Y/m').'/'.Str::ulid().'.pdf';
        Storage::disk('local')->putFileAs(dirname($path), new File($tmp), basename($path));

        return $this->record($upload, $owner, $purpose, 'document', $path, null, 'application/pdf', null, null, $visibility, $scan);
    }

    private function checkedPath(UploadedFile $upload, int $maxKb): string
    {
        if (! $upload->isValid()) {
            throw new UploadRejected('The upload did not complete. Please try again.');
        }
        if ($upload->getSize() > $maxKb * 1024) {
            throw new UploadRejected('That file is too large (max '.round($maxKb / 1024).' MB).');
        }

        return $upload->getRealPath();
    }

    private function sniff(string $path): string
    {
        return (string) (new \finfo(FILEINFO_MIME_TYPE))->file($path);
    }

    /** Apply the EXIF orientation before the EXIF data is discarded. */
    private function orient(GdImage $image, string $path, string $mime): GdImage
    {
        if ($mime !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $image;
        }
        $orientation = (int) (@exif_read_data($path)['Orientation'] ?? 1);

        return match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };
    }

    private function resize(GdImage $src, int $max, bool $square = false): GdImage
    {
        $w = imagesx($src);
        $h = imagesy($src);
        $sx = $sy = 0;

        if ($square) {
            $side = min($w, $h);
            $sx = intdiv($w - $side, 2);
            $sy = intdiv($h - $side, 2);
            $w = $h = $side;
        }

        $scale = min(1, $max / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));

        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $nw, $nh, $w, $h);

        return $dst;
    }

    private function encodeWebp(GdImage $image, int $quality): string
    {
        ob_start();
        imagewebp($image, null, $quality);

        return (string) ob_get_clean();
    }

    private function putWebp(GdImage $image, string $path): string
    {
        ob_start();
        imagewebp($image, null, 82);
        Storage::disk('local')->put($path, (string) ob_get_clean());

        return $path;
    }

    private function record(UploadedFile $upload, ?User $owner, string $purpose, string $kind, string $path, ?string $thumb, string $mime, ?int $w, ?int $h, string $visibility, string $scan): StoredFile
    {
        $file = new StoredFile;
        $file->forceFill([
            'owner_id' => $owner?->id,
            'purpose' => $purpose,
            'kind' => $kind,
            'path' => $path,
            'thumb_path' => $thumb,
            'original_name' => Str::limit(preg_replace('/[^\w.\- ]+/u', '_', $upload->getClientOriginalName()), 200, ''),
            'mime' => $mime,
            'size' => Storage::disk('local')->size($path),
            'width' => $w,
            'height' => $h,
            'sha256' => hash('sha256', Storage::disk('local')->get($path)),
            'visibility' => $visibility,
            'scan_status' => $scan,
        ])->save();

        return $file;
    }
}
