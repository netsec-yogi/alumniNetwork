<?php

namespace Tests\Feature\Admin;

use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\VerificationRequest;
use App\Services\AlumniVerificationService;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    /** SRS 104, test 2. */
    public function test_students_and_alumni_cannot_reach_admin(): void
    {
        $student = $this->user(RoleName::Student);
        $alumnus = $this->verifiedAlumnus()->user;

        foreach (['/admin', '/admin/users', '/admin/verification', '/admin/audit-logs'] as $url) {
            $this->actingAs($student)->get($url)->assertForbidden();
            $this->actingAs($alumnus)->get($url)->assertForbidden();
        }
    }

    public function test_admins_only_see_what_their_permissions_allow(): void
    {
        $events = $this->admin(RoleName::EventManager);

        // Least privilege (SRS 112): an event manager has no business here.
        $this->actingAs($events)->get('/admin/users')->assertForbidden();
        $this->actingAs($events)->get('/admin/audit-logs')->assertForbidden();
    }

    /** SRS 104, test 12: changing an id in the URL does not bypass authorisation. */
    public function test_verification_decisions_need_permission_not_just_an_id(): void
    {
        $pending = $this->verifiedAlumnus(['verification_status' => 'pending']);
        $request = app(AlumniVerificationService::class)->submit($pending);

        $this->actingAs($this->verifiedAlumnus()->user)
            ->post(route('admin.verification.approve', $request))->assertForbidden();
        $this->actingAs($this->admin(RoleName::EventManager))
            ->post(route('admin.verification.approve', $request))->assertForbidden();

        $this->assertSame('pending', $request->fresh()->status->value);
    }

    public function test_officer_cannot_verify_their_own_claim(): void
    {
        $officer = $this->admin(RoleName::VerificationOfficer);
        $own = $this->verifiedAlumnus(['user_id' => $officer->id, 'verification_status' => 'pending']);
        $request = app(AlumniVerificationService::class)->submit($own);

        $this->actingAs($officer)->post(route('admin.verification.approve', $request))->assertForbidden();
    }

    public function test_officer_can_approve_and_reject_with_reason(): void
    {
        $officer = $this->admin(RoleName::VerificationOfficer);
        $service = app(AlumniVerificationService::class);
        $a = $service->submit($this->verifiedAlumnus(['verification_status' => 'pending']));
        $b = $service->submit($this->verifiedAlumnus(['verification_status' => 'pending']));

        $this->actingAs($officer)->post(route('admin.verification.approve', $a))->assertSessionHas('success');
        $this->actingAs($officer)->post(route('admin.verification.reject', $b), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($officer)->post(route('admin.verification.reject', $b), ['reason' => 'Roll number does not exist for that batch.'])->assertSessionHas('success');

        $this->assertSame('verified', $a->profile->fresh()->verification_status->value);
        $this->assertSame('rejected', $b->profile->fresh()->verification_status->value);
        $this->assertSame(2, AuditLog::where('module', 'alumni')->whereIn('action', ['verification.verified', 'verification.rejected'])->count());

        // Decided requests cannot be decided again.
        $this->actingAs($officer)->post(route('admin.verification.reject', $a), ['reason' => 'Changed my mind about it.'])->assertSessionHas('error');
        $this->assertSame('verified', VerificationRequest::find($a->id)->status->value);
    }
}
