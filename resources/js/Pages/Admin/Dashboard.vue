<script setup lang="ts">
import AlertBox from '@/Components/AlertBox.vue';
import AppButton from '@/Components/AppButton.vue';
import BarList from '@/Components/BarList.vue';
import CardPanel from '@/Components/CardPanel.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatCard from '@/Components/StatCard.vue';
import TrendBars from '@/Components/TrendBars.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, usePage } from '@inertiajs/vue3';
import {
    Activity,
    BadgeCheck,
    Briefcase,
    CalendarDays,
    CircleCheck,
    ClipboardCheck,
    GraduationCap,
    Megaphone,
    ShieldAlert,
    ShieldCheck,
    TrendingUp,
    UserPlus,
    Users,
    UsersRound,
} from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    stats: { totalAlumni: number; verified: number; pending: number; rejected: number; newThisMonth: number; activeUsers30d: number };
    trend: { label: string; value: number }[];
    growth: number | null;
    attention: { label: string; count: number; route: string }[];
    byProgramme: { name: string; total: number }[];
    modules: { upcomingEvents: number; registrations: number; liveJobs: number; pendingJobs: number; mentors: number; activeMentorships: number; communities: number; openReports: number };
    security: {
        failedLogins: number;
        rateLimited: number;
        lockedAccounts: number;
        roleChanges: number;
        adminsWithout2fa: number;
        recent: { id: number; action: string; user: string | null; ip: string | null; at: string }[];
    } | null;
}>();

const can = (p: string) => usePage().props.auth.user!.permissions.includes(p);
const verifiedShare = computed(() => (props.stats.totalAlumni ? Math.round((props.stats.verified / props.stats.totalAlumni) * 100) : 0));
const openWork = computed(() => props.attention.filter((a) => a.count > 0));

const quickActions = computed(() =>
    [
        { label: 'Review verifications', icon: BadgeCheck, route: 'admin.verification.index', show: can('alumni.verify') },
        { label: 'Create an event', icon: CalendarDays, route: 'admin.events.create', show: can('events.create') },
        { label: 'Send a campaign', icon: Megaphone, route: 'admin.communications.create', show: can('communications.send') },
        { label: 'Import alumni records', icon: UserPlus, route: 'admin.alumni.import', show: can('alumni.import') },
        { label: 'View analytics', icon: TrendingUp, route: 'admin.analytics', show: can('reports.view') },
        { label: 'Audit log', icon: ShieldCheck, route: 'admin.audit-logs.index', show: can('audit.view') },
    ].filter((a) => a.show && route().has(a.route)),
);
</script>

<template>
    <AppLayout title="Administration">
        <PageHeader title="Administration" description="Alumni network at a glance.">
            <AppButton v-if="can('reports.view') && route().has('admin.reports.index')" variant="secondary" :icon="Activity" :href="route('admin.reports.index')">Reports</AppButton>
            <AppButton v-if="can('alumni.verify')" :icon="BadgeCheck" :href="route('admin.verification.index')">Verification queue</AppButton>
        </PageHeader>

        <div class="space-y-6">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard label="Registered alumni" :value="stats.totalAlumni" :icon="Users" tone="brand" :trend="growth" trend-label="new vs last month" />
                <StatCard label="Verified" :value="stats.verified" :icon="CircleCheck" tone="success" :hint="`${verifiedShare}% of registrations`" />
                <StatCard
                    label="Awaiting verification"
                    :value="stats.pending"
                    :icon="ClipboardCheck"
                    :tone="stats.pending > 0 ? 'warning' : 'default'"
                    :alert="stats.pending > 0"
                    :href="can('alumni.verify') ? route('admin.verification.index') : undefined"
                    :hint="can('alumni.verify') ? 'Open the queue' : undefined"
                />
                <StatCard label="Active users (30 days)" :value="stats.activeUsers30d" :icon="Activity" tone="accent" :hint="`${stats.newThisMonth} new this month`" />
            </div>

            <div class="grid gap-6 xl:grid-cols-3">
                <CardPanel class="xl:col-span-2" title="New registrations" description="Alumni profiles created per month, last 12 months." :icon="TrendingUp">
                    <template v-if="can('reports.view') && route().has('admin.analytics')" #actions>
                        <AppButton variant="ghost" size="sm" :href="route('admin.analytics')">Full analytics</AppButton>
                    </template>
                    <TrendBars :data="trend" label="New registrations per month" :height="170" />
                </CardPanel>

                <CardPanel title="Needs attention" :icon="ClipboardCheck" flush>
                    <EmptyState v-if="openWork.length === 0" compact :icon="CircleCheck" title="All queues are clear" description="Nothing is waiting for your review." />
                    <ul v-else class="divide-y divide-line-soft">
                        <li v-for="a in openWork" :key="a.route">
                            <Link :href="route(a.route)" class="flex items-center justify-between gap-3 px-5 py-3.5 hover:bg-surface-muted">
                                <span class="text-sm text-ink-soft">{{ a.label }}</span>
                                <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-800 tabular-nums dark:bg-amber-500/15 dark:text-amber-300">{{ a.count }}</span>
                            </Link>
                        </li>
                    </ul>
                </CardPanel>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard label="Upcoming events" :value="modules.upcomingEvents" :icon="CalendarDays" tone="default" :hint="`${modules.registrations} confirmed registrations`" />
                <StatCard label="Live jobs & internships" :value="modules.liveJobs" :icon="Briefcase" :tone="modules.pendingJobs ? 'warning' : 'default'" :hint="`${modules.pendingJobs} awaiting review`" />
                <StatCard label="Mentors accepting" :value="modules.mentors" :icon="GraduationCap" tone="default" :hint="`${modules.activeMentorships} active mentorships`" />
                <StatCard label="Groups & chapters" :value="modules.communities" :icon="UsersRound" :tone="modules.openReports ? 'warning' : 'default'" :hint="`${modules.openReports} open abuse reports`" />
            </div>

            <div class="grid gap-6 xl:grid-cols-3">
                <CardPanel title="Verified alumni by programme" :icon="GraduationCap">
                    <EmptyState v-if="byProgramme.length === 0" compact title="No verified alumni yet" />
                    <BarList v-else :items="byProgramme.map((p) => ({ label: p.name, value: p.total }))" />
                </CardPanel>

                <CardPanel v-if="quickActions.length" title="Quick actions" :icon="Activity">
                    <div class="grid gap-2">
                        <Link
                            v-for="a in quickActions"
                            :key="a.route"
                            :href="route(a.route)"
                            class="group flex items-center gap-3 rounded-md p-2.5 ring-1 ring-line transition-colors ring-inset hover:bg-brand-50/60 hover:ring-brand-200 dark:hover:bg-brand-500/10"
                        >
                            <span class="grid size-8 place-items-center rounded-md bg-surface-sunken text-muted group-hover:bg-brand-600 group-hover:text-white"><component :is="a.icon" :size="16" /></span>
                            <span class="text-sm font-medium text-ink-soft group-hover:text-ink">{{ a.label }}</span>
                        </Link>
                    </div>
                </CardPanel>

                <CardPanel v-if="security" title="Security" description="Last 24 hours unless noted." :icon="ShieldAlert">
                    <AlertBox v-if="security.adminsWithout2fa > 0" tone="warning" class="mb-4">
                        {{ security.adminsWithout2fa }} privileged {{ security.adminsWithout2fa === 1 ? 'account has' : 'accounts have' }} not enrolled in 2FA yet. They cannot use the portal until they do.
                    </AlertBox>
                    <dl class="grid grid-cols-2 gap-3">
                        <div class="rounded-md bg-surface-muted p-3"><dt class="text-xs text-muted">Failed sign-ins</dt><dd class="mt-0.5 text-lg font-semibold text-ink tabular-nums">{{ security.failedLogins }}</dd></div>
                        <div class="rounded-md bg-surface-muted p-3"><dt class="text-xs text-muted">Rate-limited</dt><dd class="mt-0.5 text-lg font-semibold text-ink tabular-nums">{{ security.rateLimited }}</dd></div>
                        <div class="rounded-md bg-surface-muted p-3">
                            <dt class="text-xs text-muted">Locked accounts</dt>
                            <dd :class="['mt-0.5 text-lg font-semibold tabular-nums', security.lockedAccounts ? 'text-red-600' : 'text-ink']">{{ security.lockedAccounts }}</dd>
                        </div>
                        <div class="rounded-md bg-surface-muted p-3"><dt class="text-xs text-muted">Role changes (7d)</dt><dd class="mt-0.5 text-lg font-semibold text-ink tabular-nums">{{ security.roleChanges }}</dd></div>
                    </dl>
                    <h3 class="mt-5 text-[13px] font-semibold text-ink">Recent security events</h3>
                    <ul class="mt-1 divide-y divide-line-soft text-sm">
                        <li v-for="e in security.recent" :key="e.id" class="flex justify-between gap-3 py-2">
                            <span class="min-w-0 truncate"><code class="rounded bg-surface-sunken px-1 py-0.5 text-xs">{{ e.action }}</code> <span class="text-muted">{{ e.user ?? '—' }}</span></span>
                            <span class="shrink-0 text-xs text-subtle">{{ e.at }}</span>
                        </li>
                        <li v-if="security.recent.length === 0" class="py-2 text-muted">Nothing recorded.</li>
                    </ul>
                    <AppButton v-if="can('audit.view')" variant="ghost" size="sm" class="mt-2 -ml-3" :href="route('admin.audit-logs.index')">Full audit log</AppButton>
                </CardPanel>
            </div>
        </div>
    </AppLayout>
</template>
