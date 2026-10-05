<?php

namespace Tests\Feature\Admin;

use App\Enums\RoleName;
use Tests\TestCase;

class RoleAssignmentTest extends TestCase
{
    private function confirmed()
    {
        return $this->withSession(['auth.password_confirmed_at' => time()]);
    }

    /** SRS 104, test 13. */
    public function test_admin_cannot_change_their_own_roles(): void
    {
        $admin = $this->admin(RoleName::AlumniAdmin);
        $admin->givePermissionTo('roles.manage');

        $this->actingAs($admin)->confirmed()
            ->put(route('admin.users.roles', $admin), ['roles' => ['super_admin']])->assertForbidden();

        $this->assertFalse($admin->fresh()->hasRole('super_admin'));
    }

    public function test_cannot_grant_a_role_with_permissions_you_lack(): void
    {
        $admin = $this->admin(RoleName::AlumniAdmin);
        $admin->givePermissionTo('roles.manage');
        $target = $this->user(RoleName::Alumni);

        // Fundraising Manager carries donations.* which an Alumni Admin lacks.
        $this->actingAs($admin)->confirmed()
            ->put(route('admin.users.roles', $target), ['roles' => ['fundraising_manager']])->assertSessionHasErrors('roles.0');
        $this->actingAs($admin)->confirmed()
            ->put(route('admin.users.roles', $target), ['roles' => ['super_admin']])->assertSessionHasErrors('roles.0');

        $this->assertSame(['alumni'], $target->fresh()->getRoleNames()->all());
    }

    public function test_cannot_manage_someone_more_privileged(): void
    {
        $admin = $this->admin(RoleName::AlumniAdmin);
        $super = $this->admin(RoleName::SuperAdmin);

        $this->actingAs($admin)->put(route('admin.users.status', $super), ['status' => 'suspended', 'reason' => 'Trying to escalate'])->assertForbidden();
        $this->assertSame('active', $super->fresh()->status->value);
    }

    public function test_role_changes_require_recent_password_confirmation(): void
    {
        $super = $this->admin();
        $target = $this->user(RoleName::Alumni);

        $this->actingAs($super)->put(route('admin.users.roles', $target), ['roles' => ['verification_officer']])
            ->assertRedirect(route('password.confirm'));
        $this->assertFalse($target->fresh()->hasRole('verification_officer'));
    }

    public function test_super_admin_can_assign_roles_and_it_is_audited(): void
    {
        $super = $this->admin();
        $target = $this->user(RoleName::Alumni);

        $this->actingAs($super)->confirmed()
            ->put(route('admin.users.roles', $target), ['roles' => ['alumni', 'verification_officer']])->assertSessionHas('success');

        $this->assertTrue($target->fresh()->hasRole('verification_officer'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'role.assigned', 'entity_id' => $target->id, 'user_id' => $super->id]);
    }

    public function test_suspending_a_user_signs_them_out_and_needs_a_reason(): void
    {
        $super = $this->admin();
        $target = $this->user(RoleName::Alumni);

        $this->actingAs($super)->put(route('admin.users.status', $target), ['status' => 'suspended'])->assertSessionHasErrors('reason');
        $this->actingAs($super)->put(route('admin.users.status', $target), ['status' => 'suspended', 'reason' => 'Spam'])->assertSessionHas('success');

        $this->assertSame('suspended', $target->fresh()->status->value);
    }
}
