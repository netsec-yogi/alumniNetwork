<?php

namespace Tests\Feature\Media;

use App\Enums\RoleName;
use App\Models\DistinguishedAlumnus;
use App\Models\Event;
use App\Models\EventPhoto;
use App\Models\StoredFile;
use App\Models\Story;
use App\Models\User;
use App\Notifications\EmailChangedByAdmin;
use App\Services\MediaSettings;
use App\Services\Uploads\FileUploadService;
use App\Services\Uploads\UploadRejected;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MediaManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Cache::flush();
    }

    /** A noisy (hard to compress) image of the given size, as a real upload. */
    private function noisy(int $w = 1600, int $h = 1200, string $type = 'png'): UploadedFile
    {
        $im = imagecreatetruecolor($w, $h);
        mt_srand(42);
        for ($y = 0; $y < $h; $y += 2) {
            for ($x = 0; $x < $w; $x += 2) {
                imagefilledrectangle($im, $x, $y, $x + 1, $y + 1, mt_rand(0, 0xFFFFFF));
            }
        }
        $path = tempnam(sys_get_temp_dir(), 'img').".{$type}";
        $type === 'png' ? imagepng($im, $path) : imagegif($im, $path);

        return new UploadedFile($path, "photo.{$type}", "image/{$type}", null, true);
    }

    private function fileUrl(StoredFile $file, bool $thumb = false): string
    {
        return parse_url($file->url($thumb), PHP_URL_PATH);
    }

    // --- Optimising upload pipeline -------------------------------------

    public function test_large_images_are_compressed_under_the_limit_and_metadata_stripped(): void
    {
        $upload = $this->noisy();
        $this->assertGreaterThan(1024 * 1024, $upload->getSize());

        $file = app(FileUploadService::class)->storeOptimizedImage($upload, null, 'news_image', StoredFile::MEMBERS, 300, 'News image');

        $this->assertLessThanOrEqual(300 * 1024, $file->size);
        $this->assertSame('image/webp', $file->mime);
        $this->assertLessThanOrEqual(1920, max($file->width, $file->height));
        $this->assertNotNull($file->thumb_path);
        // Re-encoded: no EXIF block in the stored bytes, and the original isn't kept.
        $this->assertStringNotContainsString('Exif', Storage::disk('local')->get($file->path));
        $this->assertCount(2, Storage::disk('local')->allFiles('files'));
    }

    public function test_images_that_cannot_fit_are_rejected_and_bad_types_refused(): void
    {
        $service = app(FileUploadService::class);

        try {
            $service->storeOptimizedImage($this->noisy(2400, 2400), null, 'profile_photo', StoredFile::MEMBERS, 1, 'Profile photo');
            $this->fail('An image that cannot fit must be rejected.');
        } catch (UploadRejected $e) {
            $this->assertStringContainsString('Profile photo must be 1 KB or smaller', $e->getMessage());
        }

        $this->expectException(UploadRejected::class);
        $service->storeOptimizedImage($this->noisy(200, 200, 'gif'), null, 'profile_photo', StoredFile::MEMBERS, 200, 'Profile photo');
    }

    public function test_a_disguised_file_is_not_accepted_as_a_profile_photo(): void
    {
        $user = $this->verifiedAlumnus()->user;
        $fake = UploadedFile::fake()->createWithContent('photo.jpg', "<?php echo 'hi';");

        $this->actingAs($user)->post(route('profile.photo.update'), ['photo' => $fake])->assertSessionHasErrors('photo');
        $this->assertNull($user->alumniProfile->fresh()->photo_file_id);
    }

    public function test_media_settings_drive_the_limit_and_are_admin_only_and_bounded(): void
    {
        $admin = $this->admin(RoleName::AlumniAdmin);
        $payload = ['event_image_kb' => 400, 'news_image_kb' => 250, 'profile_photo_kb' => 120];

        $this->actingAs($this->verifiedAlumnus()->user)->put(route('admin.settings.media.update'), $payload)->assertForbidden();
        $this->actingAs($admin)->put(route('admin.settings.media.update'), [...$payload, 'profile_photo_kb' => 5])->assertSessionHasErrors('profile_photo_kb');
        $this->actingAs($admin)->put(route('admin.settings.media.update'), $payload)->assertRedirect();

        $this->assertSame(120, app(MediaSettings::class)->limit('profile_photo_kb'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.media_updated']);

        $alumnus = $this->verifiedAlumnus();
        $this->actingAs($alumnus->user)->post(route('profile.photo.update'), ['photo' => $this->noisy()])->assertSessionHasNoErrors();
        $this->assertLessThanOrEqual(120 * 1024, $alumnus->fresh()->photo->size);
    }

    // --- Profile photo privacy -------------------------------------------

    public function test_profile_photos_are_private_until_the_alumnus_is_featured(): void
    {
        $alumnus = $this->verifiedAlumnus();
        $this->actingAs($alumnus->user)->post(route('profile.photo.update'), ['photo' => UploadedFile::fake()->image('me.jpg', 600, 600)])->assertSessionHasNoErrors();
        $photo = $alumnus->fresh()->photo;
        $this->assertSame(StoredFile::MEMBERS, $photo->visibility);
        auth()->logout();

        $this->get($this->fileUrl($photo))->assertForbidden();

        $honour = DistinguishedAlumnus::create(['alumni_profile_id' => $alumnus->id, 'category' => 'industry', 'award_year' => 2025, 'citation' => 'For exceptional work.', 'is_published' => true]);
        $this->get($this->fileUrl($photo))->assertOk();

        $honour->update(['is_published' => false]);
        $this->get($this->fileUrl($photo))->assertForbidden();
    }

    // --- Event galleries ---------------------------------------------------

    private function eventWithImages(int $n = 2, array $attrs = []): array
    {
        $admin = $this->admin(RoleName::SuperAdmin);
        $event = Event::factory()->public()->create(['status' => Event::PUBLISHED, ...$attrs]);
        $images = array_map(fn ($i) => UploadedFile::fake()->image("e{$i}.jpg", 800, 600), range(1, $n));
        $this->actingAs($admin)->post(route('admin.media.store', ['type' => 'events', 'id' => $event->id]), ['images' => $images])->assertSessionHasNoErrors();

        return [$admin, $event, $event->officialPhotos()->with('file')->get()];
    }

    public function test_event_gallery_keeps_exactly_one_featured_image(): void
    {
        [$admin, $event, $photos] = $this->eventWithImages(3);
        $this->assertSame(1, $photos->where('is_featured', true)->count());
        $this->assertTrue($photos->first()->is_featured);

        $this->actingAs($admin)->post(route('admin.media.feature', ['type' => 'events', 'id' => $event->id, 'image' => $photos[2]->id]))->assertRedirect();
        $this->assertSame([$photos[2]->id], $event->officialPhotos()->where('is_featured', true)->pluck('id')->all());

        $this->actingAs($admin)->delete(route('admin.media.destroy', ['type' => 'events', 'id' => $event->id, 'image' => $photos[2]->id]))->assertRedirect();
        $this->assertSame(1, $event->officialPhotos()->where('is_featured', true)->count());
        $this->assertDatabaseMissing('stored_files', ['id' => $photos[2]->file_id]);

        $featured = $event->featuredPhoto()->first();
        $this->actingAs($admin)->post(route('admin.media.replace', ['type' => 'events', 'id' => $event->id, 'image' => $featured->id]), ['image' => UploadedFile::fake()->image('new.jpg', 800, 600)])->assertRedirect();
        $this->assertTrue($featured->fresh()->is_featured);
        $this->assertNotSame($featured->file_id, $featured->fresh()->file_id);
    }

    public function test_reordering_and_cross_gallery_tampering(): void
    {
        [$admin, $event, $photos] = $this->eventWithImages(2);
        [, $other] = $this->eventWithImages(1);
        $ids = $photos->pluck('id')->all();

        $this->actingAs($admin)->put(route('admin.media.reorder', ['type' => 'events', 'id' => $event->id]), ['order' => array_reverse($ids)])->assertRedirect();
        $this->assertSame(array_reverse($ids), $event->photos()->where('is_official', true)->orderBy('sort_order')->pluck('id')->all());

        $foreign = $other->officialPhotos()->first()->id;
        $this->actingAs($admin)->put(route('admin.media.reorder', ['type' => 'events', 'id' => $event->id]), ['order' => [$ids[0], $foreign]])->assertSessionHasErrors('order');
        $this->actingAs($admin)->delete(route('admin.media.destroy', ['type' => 'events', 'id' => $event->id, 'image' => $foreign]))->assertNotFound();
    }

    public function test_only_event_staff_manage_event_images(): void
    {
        $event = Event::factory()->public()->create();

        $this->actingAs($this->verifiedAlumnus()->user)
            ->post(route('admin.media.store', ['type' => 'events', 'id' => $event->id]), ['images' => [UploadedFile::fake()->image('x.jpg')]])
            ->assertForbidden();
        $this->assertSame(0, EventPhoto::count());
    }

    public function test_event_images_follow_the_event_and_the_landing_page_gets_only_the_featured_one(): void
    {
        [, $event, $photos] = $this->eventWithImages(2);
        auth()->logout();
        $featured = $photos->firstWhere('is_featured', true)->file;

        $this->get($this->fileUrl($featured))->assertOk();
        $this->get('/')->assertInertia(fn (Assert $p) => $p->where('events.0.cover', $featured->url()));
        $landing = json_encode($this->get('/')->viewData('page')['props']);
        $this->assertStringNotContainsString($photos->firstWhere('is_featured', false)->file_id, $landing);

        $event->forceFill(['status' => Event::DRAFT])->save();
        $this->get($this->fileUrl($featured))->assertForbidden();

        $event->forceFill(['status' => Event::PUBLISHED, 'audience' => Event::AUDIENCE_MEMBERS])->save();
        $this->get($this->fileUrl($featured))->assertForbidden();
    }

    public function test_event_page_shows_the_gallery_featured_first(): void
    {
        [, $event, $photos] = $this->eventWithImages(3);
        auth()->logout();

        $this->get(route('events.show', $event))->assertInertia(fn (Assert $p) => $p->has('gallery', 3)->where('gallery.0.featured', true));
    }

    // --- News galleries ----------------------------------------------------

    public function test_news_images_are_public_only_once_published(): void
    {
        $admin = $this->admin(RoleName::AlumniAdmin);
        $story = new Story(['type' => 'news', 'title' => 'Campus news', 'body' => 'Body']);
        $story->forceFill(['status' => 'draft'])->save();
        $this->actingAs($admin)->post(route('admin.media.store', ['type' => 'stories', 'id' => $story->id]), ['images' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')]])->assertSessionHasNoErrors();
        $file = $story->featuredImage()->first()->file;
        auth()->logout();

        $this->get($this->fileUrl($file))->assertForbidden();

        $story->forceFill(['status' => 'published', 'published_at' => now()->subMinute()])->save();
        $this->get($this->fileUrl($file))->assertOk();
        $this->get(route('stories.show', $story))->assertInertia(fn (Assert $p) => $p->where('story.cover_url', $file->url())->has('story.gallery', 2));
    }

    public function test_content_managers_only_for_news_images(): void
    {
        $story = new Story(['type' => 'news', 'title' => 'x', 'body' => 'x']);
        $story->forceFill(['status' => 'draft'])->save();

        $this->actingAs($this->verifiedAlumnus()->user)
            ->post(route('admin.media.store', ['type' => 'stories', 'id' => $story->id]), ['images' => [UploadedFile::fake()->image('x.jpg')]])
            ->assertForbidden();
    }

    // --- Administrator: alumni email --------------------------------------

    public function test_admin_updates_an_alumnus_email_with_reverification_notice_and_audit(): void
    {
        Notification::fake();
        $admin = $this->admin(RoleName::SuperAdmin);
        $alumnus = $this->verifiedAlumnus()->user;
        $old = $alumnus->email;
        $taken = User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($admin)->put(route('admin.users.email', $alumnus), ['email' => 'new@example.com'])->assertRedirect(route('password.confirm'));

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->put(route('admin.users.email', $alumnus), ['email' => 'TAKEN@example.com'])->assertSessionHasErrors(['email' => 'Another account already uses this email address.']);
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->put(route('admin.users.email', $alumnus), ['email' => 'not-an-email'])->assertSessionHasErrors('email');

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->put(route('admin.users.email', $alumnus), ['email' => 'New.Address@Example.com'])->assertRedirect();

        $alumnus->refresh();
        $this->assertSame('new.address@example.com', $alumnus->email);
        $this->assertNull($alumnus->email_verified_at);
        Notification::assertSentTo($alumnus, VerifyEmail::class);
        Notification::assertSentTo(new AnonymousNotifiable, EmailChangedByAdmin::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === $old);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.email_changed', 'entity_id' => $alumnus->id]);
        $this->assertNotNull($taken->fresh());
    }

    public function test_admins_cannot_change_email_of_someone_above_them(): void
    {
        $alumniAdmin = $this->admin(RoleName::AlumniAdmin);
        $super = $this->admin(RoleName::SuperAdmin);

        $this->actingAs($alumniAdmin)->withSession(['auth.password_confirmed_at' => time()])
            ->put(route('admin.users.email', $super), ['email' => 'hijack@example.com'])->assertForbidden();
        $this->assertNotSame('hijack@example.com', $super->fresh()->email);
    }
}
