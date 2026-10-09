<?php

namespace App\Enums;

/**
 * Fine-grained permissions (SRS 18). Modules that are not built yet already
 * have their permissions here so roles can be configured once.
 */
enum Permission: string
{
    case AlumniView = 'alumni.view';
    case AlumniCreate = 'alumni.create';
    case AlumniUpdate = 'alumni.update';
    case AlumniVerify = 'alumni.verify';
    case AlumniDelete = 'alumni.delete';
    case AlumniExport = 'alumni.export';
    case AlumniImport = 'alumni.import';

    case EventsView = 'events.view';
    case EventsCreate = 'events.create';
    case EventsUpdate = 'events.update';
    case EventsDelete = 'events.delete';
    case EventsManageAttendance = 'events.manage_attendance';

    case DonationsView = 'donations.view';
    case DonationsCreate = 'donations.create';
    case DonationsRefund = 'donations.refund';
    case DonationsExport = 'donations.export';

    case JobsModerate = 'jobs.moderate';
    case MentoringManage = 'mentoring.manage';
    case CommunitiesModerate = 'communities.moderate';
    case ChaptersManage = 'chapters.manage';

    case UsersView = 'users.view';
    case UsersManage = 'users.manage';
    // Set or reset another user's password. Super administrators only, by default.
    case UsersPasswordManage = 'users.password.manage';
    case RolesManage = 'roles.manage';

    case ReportsView = 'reports.view';
    case ReportsExport = 'reports.export';

    case AuditView = 'audit.view';
    case SecurityManage = 'security.manage';

    // Stories, achievement approval, distinguished alumni (SRS 39-41).
    case ContentManage = 'content.manage';
    case PortalBrandingManage = 'portal.branding.manage';
    case LandingContentManage = 'landing-page.content.manage';

    // Bulk communications to segmented audiences (SRS 48-49).
    case CommunicationsSend = 'communications.send';

    // Fundraising campaigns, crowdfunding approval, Giving Day (SRS 46).
    case FundraisingManage = 'fundraising.manage';

    /** Permissions that make a user count as an administrator. */
    public static function adminPanel(): array
    {
        return [
            self::AlumniView, self::AlumniVerify, self::UsersView, self::AuditView,
            self::EventsCreate, self::DonationsView, self::JobsModerate,
            self::MentoringManage, self::CommunitiesModerate, self::ChaptersManage,
            self::ReportsView, self::SecurityManage, self::ContentManage, self::CommunicationsSend, self::FundraisingManage,
            self::PortalBrandingManage, self::LandingContentManage,
        ];
    }
}
