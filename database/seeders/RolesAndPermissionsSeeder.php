<?php

namespace Database\Seeders;

use App\Enums\Permission as P;
use App\Enums\RoleName as R;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles and their permissions (SRS 8, 17-18, 112). Idempotent: safe to
 * re-run after adding a permission. Least privilege throughout -- e.g. the
 * Event Manager gets events, not donations.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (P::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }

        $map = [
            R::SuperAdmin->value => P::cases(),
            R::AlumniAdmin->value => [
                P::AlumniView, P::AlumniCreate, P::AlumniUpdate, P::AlumniVerify, P::AlumniExport, P::AlumniImport,
                P::EventsView, P::CommunitiesModerate, P::ChaptersManage, P::MentoringManage, P::JobsModerate,
                P::UsersView, P::UsersManage, P::ReportsView, P::ReportsExport, P::ContentManage, P::CommunicationsSend,
            ],
            R::VerificationOfficer->value => [P::AlumniView, P::AlumniVerify],
            R::ChapterAdmin->value => [P::ChaptersManage, P::EventsView, P::EventsCreate, P::EventsUpdate],
            R::CommunityModerator->value => [P::CommunitiesModerate],
            R::EventManager->value => [P::EventsView, P::EventsCreate, P::EventsUpdate, P::EventsDelete, P::EventsManageAttendance],
            R::FundraisingManager->value => [P::DonationsView, P::DonationsCreate, P::DonationsRefund, P::DonationsExport, P::ReportsView, P::FundraisingManage],
            R::CareerAdmin->value => [P::JobsModerate, P::MentoringManage, P::ReportsView],
            // Member roles: what they may do is decided by policies
            // (ownership, verification), not by admin permissions.
            R::Faculty->value => [],
            R::Student->value => [],
            R::Alumni->value => [],
            R::Recruiter->value => [],
        ];

        foreach ($map as $role => $permissions) {
            Role::findOrCreate($role, 'web')->syncPermissions(array_map(fn (P $p) => $p->value, $permissions));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
