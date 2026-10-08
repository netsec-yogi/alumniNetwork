<?php

namespace Tests\Feature\Landing;

use App\Enums\RoleName;
use App\Models\Community;
use App\Models\DistinguishedAlumnus;
use App\Models\Event;
use App\Models\GalleryItem;
use App\Models\StoredFile;
use App\Models\Story;
use App\Services\LandingPageService;
use App\Services\Uploads\FileUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Cache::flush();
    }

    private function landing(): array
    {
        $props = null;
        $this->get('/')->assertOk()->assertInertia(function (Assert $page) use (&$props) {
            $page->component('Welcome');
            $props = $page->toArray()['props'];
        });

        return $props;
    }

    private function photo(string $visibility = StoredFile::PUBLIC): StoredFile
    {
        return app(FileUploadService::class)->storeImage(UploadedFile::fake()->image('p.jpg', 400, 300), null, 'test', $visibility);
    }

    private function gallery(string $status, array $attrs = [], string $visibility = StoredFile::PUBLIC): GalleryItem
    {
        $g = new GalleryItem(['title' => "Photo {$status}", 'category' => 'reunion', ...$attrs]);
        $g->forceFill(['stored_file_id' => $this->photo($visibility)->id, 'status' => $status])->save();

        return $g;
    }

    public function test_guests_see_the_landing_page_with_server_rendered_seo(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('<meta property="og:title"', $html);
        $this->assertStringContainsString('<link rel="canonical"', $html);
        $this->assertStringContainsString('"@type":"Organization"', $html);
    }

    public function test_no_private_alumni_data_reaches_the_public_page(): void
    {
        $private = $this->verifiedAlumnus(['company' => 'SecretCorp', 'designation' => 'Hidden Role', 'roll_number' => 'IPG-2010-999']);
        $private->user->forceFill(['phone' => '9876543210'])->save();
        $public = $this->verifiedAlumnus(['company' => 'OpenCorp', 'designation' => 'CTO', 'visibility' => ['company' => 'public', 'designation' => 'public']]);
        foreach ([$private, $public] as $p) {
            DistinguishedAlumnus::create(['alumni_profile_id' => $p->id, 'category' => 'industry', 'award_year' => 2025, 'citation' => str_repeat('Great work. ', 3), 'is_published' => true]);
        }
        Event::factory()->public()->create(['is_online' => true, 'online_url' => 'https://meet.example.com/secret-room', 'status' => Event::PUBLISHED]);

        $json = json_encode($this->landing());

        $this->assertStringContainsString('OpenCorp', $json);
        $this->assertStringContainsString('CTO', $json);
        foreach (['SecretCorp', 'Hidden Role', '9876543210', 'IPG-2010-999', $private->user->email, $public->user->email, 'secret-room'] as $secret) {
            $this->assertStringNotContainsString($secret, $json, "Leaked: {$secret}");
        }
        $this->assertStringNotContainsString('"alumni_profile_id"', $json);
    }

    public function test_only_published_public_content_is_listed(): void
    {
        Event::factory()->public()->create(['title' => 'Public Gala', 'status' => Event::PUBLISHED]);
        Event::factory()->create(['title' => 'Members Only Night', 'audience' => Event::AUDIENCE_MEMBERS, 'status' => Event::PUBLISHED]);
        Event::factory()->public()->create(['title' => 'Draft Party', 'status' => Event::DRAFT]);

        $news = new Story(['type' => 'news', 'title' => 'Published news', 'body' => 'x']);
        $news->forceFill(['status' => 'published', 'published_at' => now()->subDay()])->save();
        $draft = new Story(['type' => 'news', 'title' => 'Draft news', 'body' => 'x']);
        $draft->forceFill(['status' => 'draft'])->save();
        $future = new Story(['type' => 'announcement', 'title' => 'Scheduled news', 'body' => 'x']);
        $future->forceFill(['status' => 'published', 'published_at' => now()->addWeek()])->save();

        $hidden = $this->verifiedAlumnus();
        DistinguishedAlumnus::create(['alumni_profile_id' => $hidden->id, 'category' => 'research', 'award_year' => 2024, 'citation' => 'Unpublished honour text', 'is_published' => false]);

        $this->gallery(GalleryItem::PUBLISHED, ['title' => 'Shown photo']);
        $this->gallery(GalleryItem::DRAFT, ['title' => 'Draft photo']);
        $this->gallery(GalleryItem::ARCHIVED, ['title' => 'Archived photo']);
        $this->gallery(GalleryItem::PUBLISHED, ['title' => 'Expired photo', 'visible_until' => now()->subDay()]);
        $this->gallery(GalleryItem::PUBLISHED, ['title' => 'Future photo', 'visible_from' => now()->addDay()]);
        $this->gallery(GalleryItem::PUBLISHED, ['title' => 'Members file photo'], StoredFile::MEMBERS);

        $props = $this->landing();
        $json = json_encode($props);

        $this->assertSame(['Public Gala'], collect($props['events'])->pluck('title')->all());
        $this->assertSame(['Published news'], collect($props['news'])->pluck('title')->all());
        $this->assertSame(['Shown photo'], collect($props['gallery']['items'])->pluck('title')->all());
        $this->assertStringNotContainsString('Unpublished honour text', $json);
    }

    public function test_statistics_are_real_counts_and_zeroes_are_omitted(): void
    {
        $this->verifiedAlumnus(['country' => 'India', 'city' => 'Pune', 'company' => 'A']);
        $this->verifiedAlumnus(['country' => 'India', 'city' => 'Delhi', 'company' => 'B']);

        $stats = collect($this->landing()['stats'])->pluck('value', 'key');

        $this->assertSame(2, $stats['alumni']);
        $this->assertSame(1, $stats['countries']);
        $this->assertSame(2, $stats['cities']);
        $this->assertArrayNotHasKey('startups', $stats->all(), 'A zero statistic must not be displayed.');
        $this->assertArrayNotHasKey('distinguished', $stats->all());
    }

    public function test_small_country_groups_are_folded_into_other(): void
    {
        foreach (range(1, 3) as $i) {
            $this->verifiedAlumnus(['country' => 'India']);
        }
        $this->verifiedAlumnus(['country' => 'Iceland']);
        $this->verifiedAlumnus(['country' => 'Iceland']);

        $network = $this->landing()['network'];

        $this->assertSame([['country' => 'India', 'count' => 3]], $network['countries']);
        $this->assertSame(2, $network['other']);
        $this->assertStringNotContainsString('Iceland', json_encode($network));
    }

    public function test_admins_control_sections_and_stats_and_changes_refresh_the_cache(): void
    {
        $this->landing(); // warm the cache
        $admin = $this->admin(RoleName::AlumniAdmin);
        $sections = collect(app(LandingPageService::class)->settings()['sections'])->map(fn ($s) => ['key' => $s['key'], 'visible' => $s['key'] !== 'gallery'])->all();

        $this->actingAs($this->verifiedAlumnus()->user)->put(route('admin.landing.update'), ['sections' => $sections, 'stats' => []])->assertForbidden();
        $this->actingAs($admin)->put(route('admin.landing.update'), ['sections' => $sections, 'stats' => ['alumni' => true]])->assertRedirect();
        auth()->logout();

        $props = $this->landing();
        $this->assertNotContains('gallery', $props['sections']);
        $this->assertContains('events', $props['sections']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'landing.settings_updated']);
    }

    public function test_gallery_files_are_public_only_while_published(): void
    {
        $admin = $this->admin(RoleName::AlumniAdmin);
        $payload = ['title' => 'Reunion', 'category' => 'reunion', 'status' => 'draft', 'is_featured' => false];

        $this->actingAs($this->verifiedAlumnus()->user)->post(route('admin.gallery.store'), [...$payload, 'photo' => UploadedFile::fake()->image('a.jpg')])->assertForbidden();

        $this->actingAs($admin)->post(route('admin.gallery.store'), [...$payload, 'photo' => UploadedFile::fake()->image('a.jpg')])->assertRedirect();
        $item = GalleryItem::firstOrFail();
        $this->assertSame(StoredFile::MEMBERS, $item->file->visibility);

        $this->actingAs($admin)->put(route('admin.gallery.update', $item), [...$payload, 'status' => 'published'])->assertRedirect();
        $this->assertSame(StoredFile::PUBLIC, $item->fresh()->file->visibility);

        $this->actingAs($admin)->put(route('admin.gallery.update', $item), [...$payload, 'status' => 'archived'])->assertRedirect();
        $this->assertSame(StoredFile::MEMBERS, $item->fresh()->file->visibility);
    }

    public function test_only_chapter_managers_edit_chapter_landing_details(): void
    {
        $chapter = Community::factory()->chapter('Pune')->create();
        $data = ['city' => 'Pune', 'country' => 'India', 'coordinator_name' => 'Asha', 'show_on_landing' => true];

        $this->actingAs($this->verifiedAlumnus()->user)->put(route('admin.communities.landing', $chapter), $data)->assertForbidden();
        $this->actingAs($this->admin(RoleName::AlumniAdmin))->put(route('admin.communities.landing', $chapter), $data)->assertRedirect();
        auth()->logout();

        $this->assertSame('Asha', collect($this->landing()['chapters'])->firstWhere('name', 'Pune Chapter')['coordinator'] ?? null);
    }
}
