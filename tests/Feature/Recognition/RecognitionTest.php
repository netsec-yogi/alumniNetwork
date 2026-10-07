<?php

namespace Tests\Feature\Recognition;

use App\Enums\RoleName;
use App\Models\Achievement;
use App\Models\DistinguishedAlumnus;
use App\Models\StoredFile;
use App\Models\Story;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RecognitionTest extends TestCase
{
    private function png(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'img');
        imagepng(imagecreatetruecolor(40, 40), $path);

        return new UploadedFile($path, 'award.png', null, null, true);
    }

    public function test_achievement_workflow_submit_review_publish(): void
    {
        Storage::fake('local');
        $alumnus = $this->verifiedAlumnus();

        $this->actingAs($alumnus->user)->post(route('achievements.submit'), [
            'category' => 'award', 'title' => 'Young Innovator Award 2026', 'image' => $this->png(), 'status' => 'published',
        ])->assertSessionHas('success');

        $a = Achievement::sole();
        $this->assertSame(Achievement::SUBMITTED, $a->status, 'status never comes from input');
        // Not public, and its image is private, until reviewed.
        $this->actingAs($this->verifiedAlumnus()->user)->get(route('files.show', $a->image))->assertForbidden();
        $this->actingAsGuest();
        $this->get(route('achievements.index'))->assertInertia(fn (Assert $p) => $p->where('achievements.total', 0));
        $this->get(route('files.show', $a->image))->assertForbidden();

        $this->actingAs($this->admin(RoleName::EventManager))->post(route('admin.achievements.review', $a), ['decision' => 'publish'])->assertForbidden();
        $this->actingAs($this->admin(RoleName::AlumniAdmin))->post(route('admin.achievements.review', $a), ['decision' => 'publish'])->assertSessionHas('success');

        $this->actingAsGuest();
        $this->get(route('achievements.index'))->assertInertia(fn (Assert $p) => $p->where('achievements.total', 1));
        $this->get(route('files.show', $a->fresh()->image))->assertOk();
        $this->actingAs($this->verifiedAlumnus()->user)->get(route('alumni.show', $alumnus))->assertInertia(fn (Assert $p) => $p->where('achievements.0.title', 'Young Innovator Award 2026'));
    }

    public function test_rejection_needs_a_reason_and_only_verified_alumni_submit(): void
    {
        $a = new Achievement(['category' => 'award', 'title' => 'x']);
        $a->forceFill(['alumni_profile_id' => $this->verifiedAlumnus()->id, 'status' => 'submitted'])->save();
        $admin = $this->admin(RoleName::AlumniAdmin);

        $this->actingAs($admin)->post(route('admin.achievements.review', $a), ['decision' => 'reject'])->assertSessionHasErrors('reason');
        $this->actingAs($this->verifiedAlumnus(['verification_status' => 'pending'])->user)->post(route('achievements.submit'), ['category' => 'award', 'title' => 'x'])->assertForbidden();
    }

    public function test_story_markdown_is_rendered_without_script_injection(): void
    {
        $story = new Story(['type' => 'article', 'title' => 'From Gwalior to Geneva', 'body' => "## Early days\n\nHello **world**.\n\n<script>alert('xss')</script>\n\n<img src=x onerror=alert(1)>\n\n[click](javascript:alert(1))"]);
        $story->forceFill(['status' => 'published', 'published_at' => now()->subMinute()])->save();

        $html = $story->bodyHtml();
        $this->assertStringContainsString('<h2>Early days</h2>', $html);
        $this->assertStringContainsString('<strong>world</strong>', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('javascript:', $html);

        $this->get(route('stories.show', $story))->assertOk()->assertInertia(fn (Assert $p) => $p->component('Recognition/Story'));
    }

    public function test_drafts_and_future_stories_are_hidden(): void
    {
        $draft = (new Story(['type' => 'article', 'title' => 'Draft', 'body' => 'x']))->forceFill(['status' => 'draft']);
        $draft->save();
        $future = (new Story(['type' => 'article', 'title' => 'Future', 'body' => 'x']))->forceFill(['status' => 'published', 'published_at' => now()->addDay()]);
        $future->save();

        $this->get(route('stories.show', $draft))->assertNotFound();
        $this->get(route('stories.show', $future))->assertNotFound();
        $this->get(route('stories.index'))->assertInertia(fn (Assert $p) => $p->where('stories.total', 0));
    }

    public function test_staff_create_and_publish_stories(): void
    {
        Storage::fake('local');
        $admin = $this->admin(RoleName::AlumniAdmin);
        $featured = $this->verifiedAlumnus();

        $this->actingAs($admin)->post(route('admin.stories.store'), [
            'type' => 'interview', 'title' => 'Building payments for a billion', 'body' => 'Q&A',
            'roll_number' => strtolower($featured->roll_number), 'publish' => true, 'cover' => $this->png(),
        ])->assertRedirect();

        $story = Story::sole();
        $this->assertSame(['published', $featured->id], [$story->status, $story->alumni_profile_id]);
        $this->assertSame(StoredFile::PUBLIC, $story->cover->visibility);
        $this->get(route('stories.index'))->assertInertia(fn (Assert $p) => $p->where('stories.total', 1));

        $this->actingAs($this->admin(RoleName::CareerAdmin))->post(route('admin.stories.store'), ['type' => 'article', 'title' => 'x', 'body' => 'x'])->assertForbidden();
    }

    public function test_distinguished_alumni_by_roll_number(): void
    {
        $admin = $this->admin(RoleName::AlumniAdmin);
        $honouree = $this->verifiedAlumnus();

        $this->actingAs($admin)->post(route('admin.distinguished.store'), ['roll_number' => 'NOPE', 'category' => 'research', 'award_year' => 2026, 'citation' => str_repeat('Pioneering work. ', 3)])
            ->assertSessionHasErrors('roll_number');
        $this->actingAs($admin)->post(route('admin.distinguished.store'), ['roll_number' => $honouree->roll_number, 'category' => 'research', 'award_year' => 2026, 'citation' => str_repeat('Pioneering work. ', 3), 'is_published' => true])
            ->assertSessionHas('success');

        $this->assertSame(1, DistinguishedAlumnus::count());
        $this->get(route('distinguished.index'))->assertInertia(fn (Assert $p) => $p->has('honourees.Research', 1));
    }
}
