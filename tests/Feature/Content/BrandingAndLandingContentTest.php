<?php

namespace Tests\Feature\Content;

use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\ContentRevision;
use App\Models\StoredFile;
use App\Models\User;
use App\Services\Content\Branding;
use App\Services\Content\LandingCopy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BrandingAndLandingContentTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Cache::flush();
        $this->admin = $this->admin(); // super administrator: has both permissions
    }

    private function homeProps(): array
    {
        $props = null;
        $this->get('/')->assertOk()->assertInertia(function (Assert $page) use (&$props) {
            $props = $page->toArray()['props'];
        });

        return $props;
    }

    private function saveText(array $values)
    {
        return $this->actingAs($this->admin)->put(route('admin.landing.content.update'), ['values' => [...app(LandingCopy::class)->draft(), ...$values]]);
    }

    private function svg(string $body): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('logo.svg', '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 60" width="200" height="60">'.$body.'</svg>');
    }

    public function test_only_holders_of_the_new_permissions_can_manage_branding_and_text(): void
    {
        // Content managers keep the section layout page but don't get the new pages automatically.
        $contentAdmin = $this->admin(RoleName::AlumniAdmin);
        $this->assertTrue($contentAdmin->can('content.manage'));
        foreach ([route('admin.branding'), route('admin.landing.content'), route('admin.landing.preview')] as $url) {
            $this->actingAs($contentAdmin)->get($url)->assertForbidden();
        }
        $this->actingAs($contentAdmin)->post(route('admin.branding.publish'))->assertForbidden();
        $this->actingAs($contentAdmin)->put(route('admin.landing.content.update'), ['values' => []])->assertForbidden();

        $this->actingAs($this->admin)->get(route('admin.branding'))->assertInertia(fn (Assert $p) => $p->component('Admin/Branding/Index')->has('slots', 5));
        $this->actingAs($this->admin)->get(route('admin.landing.content'))->assertInertia(fn (Assert $p) => $p->component('Admin/Landing/Content')->where('values', fn ($v) => $v['landing.hero.title'] === 'Where memories connect.'));
    }

    public function test_text_is_a_draft_until_published_then_shows_at_once(): void
    {
        $this->assertSame('Where memories connect.', $this->homeProps()['copy']['landing.hero.title']); // defaults, now cached

        $this->saveText(['landing.hero.title' => 'Where Memories Connect. Where Alumni Thrive.', 'landing.events.subtitle' => 'Discover events, reunions and networking opportunities.'])->assertSessionHasNoErrors();
        $this->assertSame('Where memories connect.', $this->homeProps()['copy']['landing.hero.title']);

        // The preview shows the draft.
        $this->actingAs($this->admin)->get(route('admin.landing.preview'))->assertInertia(fn (Assert $p) => $p->component('Welcome')->where('preview', true)->where('copy', fn ($c) => $c['landing.hero.title'] === 'Where Memories Connect. Where Alumni Thrive.'));

        $this->actingAs($this->admin)->post(route('admin.landing.content.publish'))->assertSessionHas('success');
        auth()->logout();
        $copy = $this->homeProps()['copy'];
        $this->assertSame('Where Memories Connect. Where Alumni Thrive.', $copy['landing.hero.title']);
        $this->assertSame('Discover events, reunions and networking opportunities.', $copy['landing.events.subtitle']);

        $publish = AuditLog::where('action', 'landing_content.published')->sole();
        $this->assertSame('Where memories connect.', $publish->old_values['landing.hero.title']);
        $this->assertSame('Where Memories Connect. Where Alumni Thrive.', $publish->new_values['landing.hero.title']);
        $this->assertSame($this->admin->id, $publish->user_id);
        $this->assertNotNull($publish->ip_address);
        $this->assertTrue(AuditLog::where('action', 'landing_content.draft_saved')->exists());
    }

    public function test_required_text_cannot_be_blank_and_unknown_keys_are_ignored(): void
    {
        $this->saveText(['landing.hero.title' => ''])->assertSessionHasErrors('values.landing.hero.title');
        $this->saveText(['landing.hero.title' => str_repeat('x', 81)])->assertSessionHasErrors('values.landing.hero.title');

        $this->saveText(['landing.hero.badge' => '', 'landing.evil' => 'x'])->assertSessionHasNoErrors();
        $draft = app(LandingCopy::class)->draft();
        $this->assertSame('', $draft['landing.hero.badge']);
        $this->assertArrayNotHasKey('landing.evil', $draft);
    }

    public function test_rich_text_is_sanitised_on_the_server(): void
    {
        $this->saveText(['landing.hero.description' => "Hello **bold** _it_ <script>alert(1)</script> <img src=x onerror=alert(1)>\n\n[bad](javascript:alert(1)) [good](https://iiitm.ac.in)\n\n- one\n- two"]);
        $this->actingAs($this->admin)->post(route('admin.landing.content.publish'));

        $html = $this->homeProps()['copy']['landing.hero.description'];
        $this->assertStringContainsString('<strong>bold</strong>', $html);
        $this->assertStringContainsString('<em>it</em>', $html);
        $this->assertStringContainsString('<a href="https://iiitm.ac.in" rel="noopener nofollow" target="_blank">good</a>', $html);
        $this->assertStringContainsString('<li>one</li>', $html);
        foreach (['<script', 'onerror', 'javascript:', '<img'] as $bad) {
            $this->assertStringNotContainsString($bad, $html);
        }
    }

    public function test_unpublish_returns_to_defaults_and_versions_can_be_restored(): void
    {
        $this->saveText(['landing.news.title' => 'Version one']);
        $this->actingAs($this->admin)->post(route('admin.landing.content.publish'));
        $v1 = ContentRevision::sole();
        $this->saveText(['landing.news.title' => 'Version two']);
        $this->actingAs($this->admin)->post(route('admin.landing.content.publish'));

        $this->actingAs($this->admin)->post(route('admin.landing.content.unpublish'));
        $this->assertSame('News & announcements', $this->homeProps()['copy']['landing.news.title']);

        $this->actingAs($this->admin)->post(route('admin.landing.content.restore'), ['revision' => $v1->id])->assertSessionHasNoErrors();
        $this->assertSame('Version one', app(LandingCopy::class)->draft()['landing.news.title']);
        $this->assertSame('News & announcements', $this->homeProps()['copy']['landing.news.title']); // restore only fills the draft
        $this->assertTrue(AuditLog::where('action', 'landing_content.unpublished')->exists());
        $this->assertTrue(AuditLog::where('action', 'landing_content.restored')->exists());

        // A branding version can't be restored into the text.
        $other = new ContentRevision;
        $other->forceFill(['area' => 'branding', 'values' => []])->save();
        $this->actingAs($this->admin)->post(route('admin.landing.content.restore'), ['revision' => $other->id])->assertSessionHasErrors('revision');
    }

    public function test_a_logo_is_private_until_published_then_shows_everywhere(): void
    {
        $this->actingAs($this->admin)->post(route('admin.branding.logo', 'header'), ['logo' => UploadedFile::fake()->image('Our Logo.png', 1200, 300)])->assertSessionHasNoErrors();

        $file = StoredFile::where('purpose', Branding::PURPOSE)->sole();
        $this->assertSame('image/webp', $file->mime);
        $this->assertSame(800, $file->width); // resized
        $this->assertSame(StoredFile::PRIVATE, $file->visibility);
        $this->assertMatchesRegularExpression('#^files/branding/[0-9A-Z]{26}\.webp$#', $file->path); // server-chosen name

        // Draft: admins can preview it, visitors can't fetch it and still see the default.
        $this->actingAs($this->admin)->get(route('files.show', $file))->assertOk();
        auth()->logout();
        $this->get(route('files.show', $file))->assertForbidden();
        $this->assertNull($this->homeProps()['branding']['logos']['header']);

        $this->actingAs($this->admin)->post(route('admin.branding.publish'));
        auth()->logout();
        $branding = $this->homeProps()['branding'];
        $url = route('files.show', $file, absolute: false);
        $this->assertSame($url, $branding['logos']['header']);
        // Places without their own logo fall back to the header logo.
        $this->assertSame($url, $branding['logos']['mobile']);
        $this->assertSame($url, $branding['logos']['login']);
        $this->assertArrayNotHasKey('file_ids', $branding);
        $this->get($url)->assertOk();

        $audit = AuditLog::where('action', 'branding.published')->sole();
        $this->assertSame('default', $audit->old_values['header']);
        $this->assertStringContainsString($file->id, $audit->new_values['header']);
        $this->assertStringContainsString('Our Logo.png', $audit->new_values['header']);
    }

    public function test_svg_logos_are_sanitised(): void
    {
        $this->actingAs($this->admin)->post(route('admin.branding.logo', 'header'), [
            'logo' => $this->svg('<script>alert(1)</script><rect width="10" height="10" onload="alert(1)" fill="#123"/><image href="https://evil.example/x.png"/><a href="javascript:alert(1)"><text>x</text></a><foreignObject><div>x</div></foreignObject>'),
        ])->assertSessionHasNoErrors();

        $file = StoredFile::where('purpose', Branding::PURPOSE)->sole();
        $this->assertSame('image/svg+xml', $file->mime);
        $svg = Storage::disk('local')->get($file->path);
        foreach (['<script', 'onload', 'evil.example', 'javascript:', 'foreignObject'] as $bad) {
            $this->assertStringNotContainsString($bad, $svg);
        }
        $this->assertStringContainsString('<rect', $svg);

        // Served as an image with a sandboxing policy.
        $response = $this->actingAs($this->admin)->get(route('files.show', $file))->assertOk();
        $this->assertStringContainsString('sandbox', $response->headers->get('Content-Security-Policy'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function test_disguised_tiny_and_oversized_files_are_refused(): void
    {
        $upload = fn (UploadedFile $f) => $this->actingAs($this->admin)->post(route('admin.branding.logo', 'header'), ['logo' => $f]);

        $upload(UploadedFile::fake()->createWithContent('logo.png', '<?php echo "hi"; ?>'))->assertSessionHasErrors('logo');
        $upload(UploadedFile::fake()->createWithContent('logo.png', '<html><body>hi</body></html>'))->assertSessionHasErrors('logo');
        $upload(UploadedFile::fake()->image('tiny.png', 8, 8))->assertSessionHasErrors('logo');
        $upload(UploadedFile::fake()->image('huge.png', 4500, 200))->assertSessionHasErrors('logo');
        $upload(UploadedFile::fake()->create('big.png', 3000, 'image/png'))->assertSessionHasErrors('logo');
        $upload(UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><rect/></svg>'))->assertSessionHasErrors('logo'); // no size
        $this->actingAs($this->admin)->post(route('admin.branding.logo', 'nowhere'), ['logo' => UploadedFile::fake()->image('a.png', 100, 100)])->assertNotFound();

        $this->assertSame(0, StoredFile::where('purpose', Branding::PURPOSE)->count());
    }

    public function test_favicon_name_and_removal(): void
    {
        $this->actingAs($this->admin)->post(route('admin.branding.logo', 'favicon'), ['logo' => UploadedFile::fake()->image('icon.png', 512, 512)]);
        $favicon = StoredFile::where('purpose', Branding::PURPOSE)->sole();
        $this->assertSame('image/png', $favicon->mime);
        $this->assertSame(256, $favicon->width);

        $this->actingAs($this->admin)->put(route('admin.branding.update'), ['name' => 'IIITM Alumni', 'tagline' => '', 'show_name' => false])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('admin.branding.publish'));
        auth()->logout();

        $html = $this->get('/')->getContent();
        $this->assertStringContainsString('<link rel="icon" href="'.route('files.show', $favicon, absolute: false).'">', $html);
        $branding = $this->homeProps()['branding'];
        $this->assertSame('IIITM Alumni', $branding['name']);
        $this->assertFalse($branding['show_name']);

        // Removing goes through the draft too; publishing brings back the built-in icon.
        $this->actingAs($this->admin)->delete(route('admin.branding.logo.destroy', 'favicon'));
        $this->actingAs($this->admin)->post(route('admin.branding.publish'));
        auth()->logout();
        $this->assertStringContainsString('<link rel="icon" href="/favicon.ico">', $this->get('/')->getContent());
        $this->get(route('files.show', $favicon))->assertForbidden(); // no longer published
    }
}
