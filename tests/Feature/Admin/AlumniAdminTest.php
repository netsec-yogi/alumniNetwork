<?php

namespace Tests\Feature\Admin;

use App\Enums\RoleName;
use App\Jobs\ImportAlumniRecords;
use App\Models\AlumniRecord;
use App\Models\EngagementActivity;
use App\Models\Import;
use App\Services\EngagementScore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AlumniAdminTest extends TestCase
{
    private function csv(string $body): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('records.csv', "roll_number,name,programme_code,admission_year,graduation_year,email\n".$body);
    }

    public function test_import_validates_previews_then_imports_on_confirm(): void
    {
        Storage::fake('local');
        $admin = $this->admin(RoleName::AlumniAdmin);
        AlumniRecord::factory()->create(['roll_number' => '2012IPG-001']);

        $file = $this->csv(implode("\n", [
            '2012ipg-001,Asha Rao,IPG-MTECH,2007,2012,asha@example.com', // update
            '2013BCS-002,Ravi Kumar,BTECH-CSE,2009,2013,',              // new
            '2013BCS-002,Ravi Again,BTECH-CSE,2009,2013,',              // duplicate in file
            'BAD ROLL!,X,BTECH-CSE,,2013,',                              // invalid roll
            '2014XYZ-003,Meena,NOPE,,2014,',                             // unknown programme
            '2015BCS-004,Old Grad,BTECH-CSE,2016,2015,',                 // admission after graduation
        ]));

        $this->actingAs($admin)->post(route('admin.alumni.import.preview'), ['file' => $file])->assertRedirect(route('admin.alumni.import'));

        $import = Import::sole();
        $this->assertSame(['previewed', 6, 2], [$import->status, $import->total_rows, $import->valid_rows]);
        $this->assertSame(1, AlumniRecord::count(), 'nothing is written before confirmation');

        $this->actingAs($admin)->post(route('admin.alumni.import.confirm', $import))->assertRedirect();

        // Sync queue in tests: the job has run.
        $import->refresh();
        $this->assertSame(['completed', 1, 1], [$import->status, $import->created_rows, $import->updated_rows]);
        $this->assertSame('Asha Rao', AlumniRecord::where('roll_number', '2012IPG-001')->value('name'));
        Storage::disk('local')->assertMissing($import->path);
    }

    public function test_import_needs_permission_and_rejects_non_csv(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin(RoleName::VerificationOfficer))->post(route('admin.alumni.import.preview'), ['file' => $this->csv('')])->assertForbidden();

        // A real PNG renamed .csv: the MIME type is sniffed from content, not the name.
        $png = tempnam(sys_get_temp_dir(), 'png');
        imagepng(imagecreatetruecolor(4, 4), $png);
        $disguised = new UploadedFile($png, 'records.csv', 'text/csv', null, true);
        $this->actingAs($this->admin(RoleName::AlumniAdmin))->post(route('admin.alumni.import.preview'), ['file' => $disguised])->assertSessionHasErrors('file');
    }

    public function test_import_can_only_be_confirmed_once_by_its_uploader(): void
    {
        Storage::fake('local');
        Queue::fake();
        $admin = $this->admin(RoleName::AlumniAdmin);
        $this->actingAs($admin)->post(route('admin.alumni.import.preview'), ['file' => $this->csv('2013BCS-002,Ravi Kumar,BTECH-CSE,2009,2013,')]);
        $import = Import::sole();

        $this->actingAs($this->admin())->post(route('admin.alumni.import.confirm', $import))->assertStatus(409);
        $this->actingAs($admin)->post(route('admin.alumni.import.confirm', $import))->assertRedirect();
        $this->actingAs($admin)->post(route('admin.alumni.import.confirm', $import))->assertStatus(409);
        Queue::assertPushed(ImportAlumniRecords::class, 1);
    }

    public function test_export_requires_permission_password_and_is_audited_and_capped(): void
    {
        $this->verifiedAlumnus(['company' => '=cmd|calc']);
        $admin = $this->admin(RoleName::AlumniAdmin);
        $this->actingAs($admin)->get(route('admin.alumni.export'))->assertRedirect(route('password.confirm'));

        $officer = $this->admin(RoleName::VerificationOfficer);
        $this->actingAs($officer)->withSession(['auth.password_confirmed_at' => time()])->get(route('admin.alumni.export'))->assertForbidden();

        $csv = $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->get(route('admin.alumni.export'))->streamedContent();
        $this->assertStringContainsString("'=cmd|calc", $csv);
        $this->assertDatabaseHas('audit_logs', ['action' => 'alumni.exported', 'user_id' => $admin->id]);
    }

    public function test_360_view_is_audited_and_shows_score(): void
    {
        $alumnus = $this->verifiedAlumnus();
        EngagementActivity::create(['alumni_profile_id' => $alumnus->id, 'activity_type' => 'EVENT_ATTENDED', 'activity_date' => now(), 'engagement_mode' => 'experiential']);
        EngagementActivity::create(['alumni_profile_id' => $alumnus->id, 'activity_type' => 'MENTORSHIP_COMPLETED', 'activity_date' => now(), 'engagement_mode' => 'volunteer']);
        $admin = $this->admin(RoleName::AlumniAdmin);

        $this->actingAs($admin)->get(route('admin.alumni.show', $alumnus))
            ->assertInertia(fn (Assert $p) => $p->where('score.total', 30)->where('byMode.volunteer', 1));
        $this->assertDatabaseHas('audit_logs', ['action' => 'alumni.viewed', 'entity_id' => $alumnus->id]);

        $this->actingAs($this->user(RoleName::Student))->get(route('admin.alumni.show', $alumnus))->assertForbidden();
    }

    public function test_score_follows_configured_weights(): void
    {
        $alumnus = $this->verifiedAlumnus();
        EngagementActivity::create(['alumni_profile_id' => $alumnus->id, 'activity_type' => 'COMMUNITY_POST', 'activity_date' => now(), 'engagement_mode' => 'communication']);

        config(['engagement.weights.COMMUNITY_POST' => 7]);
        $this->assertSame(7, app(EngagementScore::class)->forProfile($alumnus->id));
    }

    public function test_reports_need_permission_and_count_case_modes(): void
    {
        $a = $this->verifiedAlumnus();
        EngagementActivity::create(['alumni_profile_id' => $a->id, 'activity_type' => 'JOB_REFERRAL', 'activity_date' => now(), 'engagement_mode' => 'volunteer']);

        $this->actingAs($this->admin(RoleName::EventManager))->get(route('admin.reports.index'))->assertForbidden();
        $this->actingAs($this->admin(RoleName::AlumniAdmin))->get(route('admin.reports.index'))
            ->assertInertia(fn (Assert $p) => $p->where('alumni.engaged', 1)->where('case.1.mode', 'volunteer')->where('case.1.alumni', 1));
    }

    public function test_programme_management(): void
    {
        $admin = $this->admin(RoleName::AlumniAdmin);
        $this->actingAs($admin)->post(route('admin.programmes.store'), ['code' => 'mtech-cy', 'name' => 'M.Tech. Cyber Security', 'degree' => 'M.Tech.', 'duration_years' => 2, 'is_active' => true])->assertSessionHas('success');
        $this->assertDatabaseHas('programmes', ['code' => 'MTECH-CY']);

        $this->actingAs($this->admin(RoleName::EventManager))->post(route('admin.programmes.store'), ['code' => 'X', 'name' => 'X', 'degree' => 'X', 'duration_years' => 1])->assertForbidden();
    }

    public function test_completing_a_profile_records_engagement_once(): void
    {
        $profile = $this->verifiedAlumnus();
        $full = ['company' => 'A', 'designation' => 'B', 'industry' => 'C', 'city' => 'D', 'country' => 'E', 'bio' => 'F', 'linkedin_url' => 'https://linkedin.com/in/x', 'specialization' => 'G', 'interests' => ['mentor']];

        $this->actingAs($profile->user)->put(route('profile.update'), $full);
        $this->actingAs($profile->user)->put(route('profile.update'), [...$full, 'city' => 'Z']);

        $this->assertSame(1, EngagementActivity::where('activity_type', 'PROFILE_COMPLETED')->count());
    }
}
