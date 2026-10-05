<?php

namespace App\Enums;

/**
 * The portal's roles (SRS 8). Permissions are attached to these by
 * RolesAndPermissionsSeeder; code checks permissions, not role names, except
 * where a rule is genuinely about the role (2FA policy, privilege ceilings).
 */
enum RoleName: string
{
    case SuperAdmin = 'super_admin';
    case AlumniAdmin = 'alumni_admin';
    case VerificationOfficer = 'verification_officer';
    case ChapterAdmin = 'chapter_admin';
    case CommunityModerator = 'community_moderator';
    case EventManager = 'event_manager';
    case FundraisingManager = 'fundraising_manager';
    case CareerAdmin = 'career_admin';
    case Faculty = 'faculty';
    case Student = 'student';
    case Alumni = 'alumni';
    case Recruiter = 'recruiter';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Administrator',
            self::AlumniAdmin => 'Alumni Administrator',
            self::VerificationOfficer => 'Verification Officer',
            self::ChapterAdmin => 'Chapter Administrator',
            self::CommunityModerator => 'Community Moderator',
            self::EventManager => 'Event Manager',
            self::FundraisingManager => 'Fundraising Manager',
            self::CareerAdmin => 'Career Administrator',
            self::Faculty => 'Faculty',
            self::Student => 'Student',
            self::Alumni => 'Alumni',
            self::Recruiter => 'Recruiter',
        };
    }
}
