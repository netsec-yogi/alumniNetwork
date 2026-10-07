<?php

namespace Tests\Feature\Uploads;

use App\Enums\RoleName;
use App\Models\StoredFile;
use App\Services\Uploads\FileUploadService;
use App\Services\Uploads\UploadRejected;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileUploadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /** A real file on disk, so the type is sniffed from its bytes. */
    private function realUpload(string $bytes, string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'up');
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $name, null, null, true);
    }

    private function jpegWithExif(): string
    {
        $img = imagecreatetruecolor(1200, 800);
        ob_start();
        imagejpeg($img);

        // Splice an APP1 (EXIF) segment with a recognisable marker after SOI.
        $jpeg = ob_get_clean();
        $exif = "Exif\0\0SECRET-GPS-48.8584N";

        return substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2);
    }

    public function test_photo_upload_is_reencoded_resized_and_stripped(): void
    {
        $profile = $this->verifiedAlumnus();

        $this->actingAs($profile->user)->post(route('profile.photo.update'), ['photo' => $this->realUpload($this->jpegWithExif(), 'me.jpg')])->assertSessionHas('success');

        $file = StoredFile::sole();
        $this->assertSame(['image', 'image/webp', 800], [$file->kind, $file->mime, $file->width]);
        $bytes = Storage::disk('local')->get($file->path);
        $this->assertStringStartsWith('RIFF', $bytes);
        $this->assertStringNotContainsString('SECRET-GPS', $bytes, 'metadata must be stripped');
        $this->assertStringNotContainsString('me.jpg', $file->path, 'stored names are random');
        Storage::disk('local')->assertExists($file->thumb_path);
    }

    /** SRS 104, test 10: uploaded files cannot execute server-side code. */
    public function test_disguised_scripts_and_svg_are_rejected(): void
    {
        $profile = $this->verifiedAlumnus();
        $cases = [
            ['<?php system($_GET["c"]); ?>', 'avatar.jpg'],
            ['<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>', 'avatar.svg'],
            ["GIF89a<?php echo 'polyglot'; ?>", 'avatar.gif'], // magic bytes but not a decodable image
        ];

        foreach ($cases as [$bytes, $name]) {
            $this->actingAs($profile->user)->post(route('profile.photo.update'), ['photo' => $this->realUpload($bytes, $name)])->assertSessionHasErrors('photo');
        }

        $this->assertSame(0, StoredFile::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_documents_must_really_be_pdf(): void
    {
        $service = app(FileUploadService::class);

        $file = $service->storeDocument($this->realUpload("%PDF-1.4\n%âãÏÓ\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF", 'cv.pdf'), null, 'test');
        $this->assertSame('application/pdf', $file->mime);
        $this->assertStringEndsWith('.pdf', $file->path);

        $this->expectException(UploadRejected::class);
        $service->storeDocument($this->realUpload('<html><script>alert(1)</script></html>', 'cv.pdf'), null, 'test');
    }

    /** SRS 104, test 11: unauthorised users cannot download private documents. */
    public function test_download_authorisation_and_safe_headers(): void
    {
        $service = app(FileUploadService::class);
        $owner = $this->verifiedAlumnus()->user;
        $private = $service->storeDocument($this->realUpload("%PDF-1.4\n%%EOF", 'secret.pdf'), $owner, 'test');
        $members = $service->storeImage($this->realUpload($this->jpegWithExif(), 'p.jpg'), $owner, 'test');

        $this->get(route('files.show', $members))->assertForbidden(); // guest
        $this->actingAs($this->verifiedAlumnus(['verification_status' => 'pending'])->user)->get(route('files.show', $members))->assertForbidden();
        $this->actingAs($this->user(RoleName::Student))->get(route('files.show', $members))->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Content-Type', 'image/webp');

        $this->actingAs($this->verifiedAlumnus()->user)->get(route('files.show', $private))->assertForbidden();
        $response = $this->actingAs($owner)->get(route('files.show', $private))->assertOk();
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('sandbox', $response->headers->get('Content-Security-Policy'));
    }

    public function test_scanner_fails_closed_when_required_and_unavailable(): void
    {
        config(['security.uploads.scan_required' => true, 'security.uploads.clamav_host' => null]);
        $profile = $this->verifiedAlumnus();

        $this->actingAs($profile->user)->post(route('profile.photo.update'), ['photo' => $this->realUpload($this->jpegWithExif(), 'me.jpg')])
            ->assertSessionHasErrors(['photo' => 'File scanning is unavailable. Please try again later.']);
    }

    public function test_decompression_bombs_are_refused(): void
    {
        config(['security.uploads.max_pixels' => 1000]);
        $profile = $this->verifiedAlumnus();

        $this->actingAs($profile->user)->post(route('profile.photo.update'), ['photo' => $this->realUpload($this->jpegWithExif(), 'big.jpg')])->assertSessionHasErrors('photo');
    }

    public function test_replacing_a_photo_deletes_the_old_file(): void
    {
        $profile = $this->verifiedAlumnus();
        $this->actingAs($profile->user)->post(route('profile.photo.update'), ['photo' => $this->realUpload($this->jpegWithExif(), 'a.jpg')]);
        $first = StoredFile::sole();
        $this->actingAs($profile->user)->post(route('profile.photo.update'), ['photo' => $this->realUpload($this->jpegWithExif(), 'b.jpg')]);

        $this->assertSame(1, StoredFile::withTrashed()->count());
        Storage::disk('local')->assertMissing($first->path);
    }
}
