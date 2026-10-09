import type { AuthUser } from '@/types';
import {
    Award,
    BarChart3,
    Bell,
    Bot,
    Briefcase,
    CalendarDays,
    ClipboardList,
    FileClock,
    GraduationCap,
    HandCoins,
    HandHeart,
    Handshake,
    House,
    Lightbulb,
    MessageSquare,
    Newspaper,
    Rss,
    ShieldAlert,
    ShieldCheck,
    UserCog,
    Users,
    UsersRound,
    type LucideIcon,
} from 'lucide-vue-next';

/**
 * The app's navigation, defined once. AppLayout renders it as the sidebar;
 * PageHeader derives breadcrumbs from it. Items whose route doesn't exist
 * or that the user may not use are filtered out, so the nav never links to
 * a page that 403s or 404s.
 */
export interface NavLeaf {
    label: string;
    route: string;
    /** route().current() pattern for the active state; defaults to the route name. */
    match?: string;
    badge?: 'messages' | 'connectionRequests' | 'notifications';
    /** false hides the item (permission or feature checks). */
    show?: boolean;
}

export interface NavItem extends NavLeaf {
    icon: LucideIcon;
    children?: NavLeaf[];
}

export interface NavSection {
    title: string;
    items: NavItem[];
}

interface Ctx {
    user: AuthUser;
    ai: boolean;
}


export function buildNavigation({ user, ai }: Ctx): NavSection[] {
    const can = (p: string) => user.permissions.includes(p);
    const canMentor = user.verification_status === 'verified' || user.roles.includes('faculty');

    const sections: NavSection[] = [
        {
            title: 'Network',
            items: [
                { label: 'Home', route: 'dashboard', icon: House },
                { label: 'Feed', route: 'feed', match: 'feed*', icon: Rss },
                { label: 'Directory', route: 'directory', match: '(directory|alumni.*)', icon: Users },
                { label: 'Connections', route: 'connections.index', match: 'connections.*', icon: Handshake, badge: 'connectionRequests' },
                { label: 'Messages', route: 'messages.index', match: 'messages.*', icon: MessageSquare, badge: 'messages' },
                { label: 'Communities', route: 'communities.index', match: 'communities.*', icon: UsersRound },
                { label: 'Assistant', route: 'assistant', icon: Bot, show: ai && user.is_member },
            ],
        },
        {
            title: 'Opportunities',
            items: [
                { label: 'Events', route: 'events.index', match: 'events.*', icon: CalendarDays },
                {
                    label: 'Careers',
                    route: 'jobs.index',
                    match: 'jobs.*',
                    icon: Briefcase,
                    children: [
                        { label: 'Jobs & internships', route: 'jobs.index', match: 'jobs.(index|show)' },
                        { label: 'Post an opening', route: 'jobs.create', match: 'jobs.(create|edit)' },
                        { label: 'Referral requests', route: 'jobs.referrals' },
                    ],
                },
                {
                    label: 'Mentoring',
                    route: 'mentoring.index',
                    match: 'mentoring.*',
                    icon: GraduationCap,
                    children: [
                        { label: 'My mentoring', route: 'mentoring.index' },
                        { label: 'Find a mentor', route: 'mentoring.find' },
                        { label: 'Mentor profile', route: 'mentoring.profile', show: canMentor },
                    ],
                },
                {
                    label: 'Collaborate',
                    route: 'research.index',
                    match: '(research|speakers|startups|volunteering).*',
                    icon: Lightbulb,
                    children: [
                        { label: 'Research', route: 'research.index', match: 'research.*' },
                        { label: 'Speakers', route: 'speakers.index', match: 'speakers.*' },
                        { label: 'Startups', route: 'startups.index', match: 'startups.*' },
                        { label: 'Volunteering', route: 'volunteering.index', match: 'volunteering.*' },
                    ],
                },
            ],
        },
        {
            title: 'Community',
            items: [
                {
                    label: 'Recognition',
                    route: 'stories.index',
                    match: '(stories|achievements|distinguished).*',
                    icon: Award,
                    children: [
                        { label: 'Stories', route: 'stories.index', match: 'stories.*' },
                        { label: 'Achievements', route: 'achievements.index' },
                        { label: 'Distinguished alumni', route: 'distinguished.index' },
                        { label: 'My achievements', route: 'achievements.mine' },
                    ],
                },
                { label: 'Surveys', route: 'surveys.index', match: 'surveys.*', icon: ClipboardList },
                {
                    label: 'Giving',
                    route: 'giving.index',
                    match: '(giving|fundraising).*',
                    icon: HandHeart,
                    children: [
                        { label: 'Campaigns', route: 'fundraising.index', match: 'fundraising.(index|show)' },
                        { label: 'Give', route: 'giving.index' },
                        { label: 'My donations', route: 'giving.mine' },
                        { label: 'Propose a campaign', route: 'fundraising.create', match: 'fundraising.(create|edit)' },
                    ],
                },
            ],
        },
        {
            title: 'Administration',
            items: [
                { label: 'Overview', route: 'admin.dashboard', icon: BarChart3, show: user.can_access_admin },
                {
                    label: 'People',
                    route: 'admin.alumni.index',
                    match: 'admin.(alumni|verification|users).*',
                    icon: ShieldCheck,
                    children: [
                        { label: 'Alumni records', route: 'admin.alumni.index', match: 'admin.alumni.*', show: can('alumni.view') },
                        { label: 'Verification', route: 'admin.verification.index', match: 'admin.verification.*', show: can('alumni.verify') },
                        { label: 'Users & roles', route: 'admin.users.index', match: 'admin.users.*', show: can('users.view') },
                    ],
                },
                {
                    label: 'Engagement',
                    route: 'admin.events.index',
                    match: 'admin.(events|jobs|communities|surveys).*',
                    icon: CalendarDays,
                    children: [
                        { label: 'Events', route: 'admin.events.index', match: 'admin.events.*', show: can('events.view') },
                        { label: 'Jobs', route: 'admin.jobs.index', match: 'admin.jobs.*', show: can('jobs.moderate') },
                        { label: 'Communities', route: 'admin.communities.index', match: 'admin.communities.*', show: can('communities.moderate') || can('chapters.manage') },
                        { label: 'Surveys', route: 'admin.surveys.index', match: 'admin.surveys.*', show: can('communications.send') || can('events.update') },
                    ],
                },
                {
                    label: 'Content',
                    route: 'admin.achievements.index',
                    match: 'admin.(achievements|stories|distinguished|communications|landing|gallery).*',
                    icon: Newspaper,
                    children: [
                        { label: 'Achievements', route: 'admin.achievements.index', match: 'admin.achievements.*', show: can('content.manage') },
                        { label: 'Stories', route: 'admin.stories.index', match: 'admin.stories.*', show: can('content.manage') },
                        { label: 'Distinguished alumni', route: 'admin.distinguished.index', match: 'admin.distinguished.*', show: can('content.manage') },
                        { label: 'Communications', route: 'admin.communications.index', match: 'admin.communications.*', show: can('communications.send') },
                        { label: 'Landing page', route: 'admin.landing.index', match: 'admin.landing.index', show: can('content.manage') },
                        { label: 'Landing page text', route: 'admin.landing.content', match: 'admin.landing.(content|preview)*', show: can('landing-page.content.manage') },
                        { label: 'Gallery', route: 'admin.gallery.index', match: 'admin.gallery.*', show: can('content.manage') },
                    ],
                },
                {
                    label: 'Finance',
                    route: 'admin.donations.index',
                    match: 'admin.(fundraising|donations).*',
                    icon: HandCoins,
                    children: [
                        { label: 'Campaigns', route: 'admin.fundraising.index', match: 'admin.fundraising.*', show: can('fundraising.manage') },
                        { label: 'Donations', route: 'admin.donations.index', match: 'admin.donations.*', show: can('donations.view') },
                    ],
                },
                { label: 'Moderation', route: 'admin.moderation.index', match: 'admin.moderation.*', icon: ShieldAlert, show: ['communities.moderate', 'jobs.moderate', 'users.manage'].some(can) },
                {
                    label: 'Insights',
                    route: 'admin.analytics',
                    match: 'admin.(analytics|reports.*)',
                    icon: BarChart3,
                    children: [
                        { label: 'Analytics', route: 'admin.analytics', show: can('reports.view') },
                        { label: 'Engagement reports', route: 'admin.reports.index', match: 'admin.reports.*', show: can('reports.view') },
                    ],
                },
                {
                    label: 'System',
                    route: 'admin.programmes.index',
                    match: 'admin.(programmes|audit-logs|settings|branding).*',
                    icon: UserCog,
                    children: [
                        { label: 'Programmes', route: 'admin.programmes.index', match: 'admin.programmes.*', show: can('alumni.update') },
                        { label: 'Audit log', route: 'admin.audit-logs.index', match: 'admin.audit-logs.*', show: can('audit.view') },
                        { label: 'Media settings', route: 'admin.settings.media', match: 'admin.settings.media*', show: can('content.manage') },
                        { label: 'Branding', route: 'admin.branding', match: 'admin.branding*', show: can('portal.branding.manage') },
                        { label: 'OTP sign-in', route: 'admin.settings.otp', match: 'admin.settings.otp*', show: can('security.manage') },
                    ],
                },
            ],
        },
    ];

    const exists = (r: string) => route().has(r);
    const adminOk = (r: string) => !r.startsWith('admin.') || user.can_access_admin;

    return sections
        .map((s) => ({
            title: s.title,
            items: s.items
                .map((i) => ({ ...i, children: i.children?.filter((c) => c.show !== false && exists(c.route) && adminOk(c.route)) }))
                .filter((i) => i.show !== false && exists(i.route) && adminOk(i.route) && (!i.children || i.children.length > 0))
                // A group with its landing page hidden opens its first visible child.
                .map((i) => (i.children?.length ? { ...i, route: i.children[0].route } : i)),
        }))
        .filter((s) => s.items.length > 0);
}

export const accountNav: (NavLeaf & { icon: LucideIcon })[] = [
    { label: 'My profile', route: 'profile.edit', icon: UsersRound },
    { label: 'Security', route: 'profile.security', icon: ShieldCheck },
    { label: 'Active sessions', route: 'profile.sessions', icon: FileClock },
    { label: 'Notifications', route: 'notifications.index', icon: Bell },
];

export const isActive = (leaf: NavLeaf) => !!route().current(leaf.match ?? leaf.route);

/** Breadcrumb trail for the current route: section › group › item. */
export function currentTrail(sections: NavSection[]): { label: string; route?: string }[] {
    for (const s of sections) {
        for (const i of s.items) {
            const child = i.children?.find(isActive);
            if (child) return [{ label: i.label, route: i.route }, { label: child.label, route: child.route }];
            if (!i.children && isActive(i)) return [{ label: i.label, route: i.route }];
        }
    }
    for (const a of accountNav) if (isActive(a)) return [{ label: a.label, route: a.route }];
    return [];
}
