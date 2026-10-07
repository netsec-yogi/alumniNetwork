<script setup lang="ts">
import AlertBox from '@/Components/AlertBox.vue';
import AppButton from '@/Components/AppButton.vue';
import AvatarImage from '@/Components/AvatarImage.vue';
import CardPanel from '@/Components/CardPanel.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatCard from '@/Components/StatCard.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Bell,
    Briefcase,
    CalendarDays,
    CalendarPlus,
    GraduationCap,
    Handshake,
    MessageSquare,
    PenSquare,
    Search,
    ShieldCheck,
    Sparkles,
    UserPlus,
    UserRoundPen,
} from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    profile: {
        id: number;
        programme: string;
        graduation_year: number;
        verification_status: string;
        rejection_reason: string | null;
        completion: number;
    } | null;
    canBrowseDirectory: boolean;
    alumniCount: number;
    myEvents: { slug: string; title: string; starts_at: string }[];
    stats: { connections: number; upcomingEvents: number; mentorships: number; liveJobs: number | null };
    activity: { id: string; title: string; read: boolean; at: string }[];
    suggestions: { profile_id: number; name: string; subtitle: string; reason: string }[];
    latestJobs: { id: number; title: string; organization: string; location: string | null; type: string }[];
    pending: { mentoring: number; referrals: number };
}>();

const page = usePage();
const user = computed(() => page.props.auth.user!);
const firstName = computed(() => user.value.name.split(' ')[0]);
const greeting = computed(() => {
    const h = new Date().getHours();
    return h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening';
});
const verified = computed(() => props.profile?.verification_status === 'verified' || user.value.is_member);
const unreadMessages = computed(() => (page.props.counts as { messages?: number } | null)?.messages ?? 0);

const quickActions = computed(() =>
    [
        { label: 'Find alumni', icon: Search, href: route('directory'), show: props.canBrowseDirectory },
        { label: 'Write a post', icon: PenSquare, href: route('feed'), show: verified.value },
        { label: 'Find a mentor', icon: GraduationCap, href: route('mentoring.find'), show: verified.value },
        { label: 'Post a job', icon: Briefcase, href: route('jobs.create'), show: verified.value && route().has('jobs.create') },
        { label: 'Browse events', icon: CalendarPlus, href: route('events.index'), show: true },
        { label: 'Update profile', icon: UserRoundPen, href: route('profile.edit'), show: true },
    ].filter((a) => a.show),
);

const openNotification = (id: string) => router.post(route('notifications.open', id));
</script>

<template>
    <AppLayout title="Dashboard">
        <PageHeader :title="`${greeting}, ${firstName}`" :description="profile ? `${profile.programme} · Batch of ${profile.graduation_year}` : 'Here’s what’s happening in the ABV-IIITM network.'">
            <AppButton v-if="canBrowseDirectory" variant="secondary" :icon="Search" :href="route('directory')">Find alumni</AppButton>
            <AppButton :icon="CalendarDays" :href="route('events.index')">Events</AppButton>
        </PageHeader>

        <div class="space-y-6">
            <!-- Things that need attention come first. -->
            <AlertBox v-if="profile?.verification_status === 'pending'" tone="warning" title="Verification in progress">
                We couldn't match your details to the institute's records automatically, so the alumni office is reviewing them. You'll get an email when it's done
                — usually within a few working days. The directory opens once you're verified.
            </AlertBox>
            <AlertBox v-else-if="profile?.verification_status === 'rejected'" tone="danger" title="We couldn't verify your alumni status">
                <p>{{ profile.rejection_reason }}</p>
                <p class="mt-1">Please contact the alumni office if you think this is a mistake.</p>
            </AlertBox>
            <AlertBox v-if="pending.mentoring || pending.referrals" tone="info" title="Waiting on you">
                <ul class="flex flex-wrap gap-x-5 gap-y-1">
                    <li v-if="pending.mentoring">
                        <Link :href="route('mentoring.index', { tab: 'mentoring' })" class="font-medium underline underline-offset-2">{{ pending.mentoring }} mentoring {{ pending.mentoring === 1 ? 'request' : 'requests' }}</Link>
                    </li>
                    <li v-if="pending.referrals">
                        <Link :href="route('jobs.referrals')" class="font-medium underline underline-offset-2">{{ pending.referrals }} referral {{ pending.referrals === 1 ? 'request' : 'requests' }}</Link>
                    </li>
                </ul>
            </AlertBox>

            <!-- KPIs -->
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard label="Connections" :value="stats.connections" :icon="Handshake" tone="brand" :href="route('connections.index')" hint="View your network" />
                <StatCard label="Unread messages" :value="unreadMessages" :icon="MessageSquare" tone="success" :href="route('messages.index')" hint="Open inbox" />
                <StatCard label="Upcoming events" :value="stats.upcomingEvents" :icon="CalendarDays" tone="warning" :href="route('events.index')" hint="You’re registered" />
                <StatCard
                    v-if="stats.liveJobs !== null"
                    label="Open jobs"
                    :value="stats.liveJobs"
                    :icon="Briefcase"
                    tone="accent"
                    :href="route('jobs.index')"
                    :hint="stats.mentorships ? `${stats.mentorships} active mentorship${stats.mentorships === 1 ? '' : 's'}` : 'Posted by alumni'"
                />
                <StatCard v-else label="Active mentorships" :value="stats.mentorships" :icon="GraduationCap" tone="accent" :href="route('mentoring.index')" />
            </div>

            <div class="grid gap-6 xl:grid-cols-3">
                <div class="space-y-6 xl:col-span-2">
                    <!-- Quick actions -->
                    <CardPanel title="Quick actions" :icon="Sparkles">
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            <Link
                                v-for="a in quickActions"
                                :key="a.label"
                                :href="a.href"
                                class="group flex items-center gap-3 rounded-md p-3 ring-1 ring-line transition-colors ring-inset hover:bg-brand-50/60 hover:ring-brand-200 dark:hover:bg-brand-500/10"
                            >
                                <span class="grid size-9 shrink-0 place-items-center rounded-md bg-surface-sunken text-muted transition-colors group-hover:bg-brand-600 group-hover:text-white">
                                    <component :is="a.icon" :size="17" aria-hidden="true" />
                                </span>
                                <span class="text-sm font-medium text-ink-soft group-hover:text-ink">{{ a.label }}</span>
                            </Link>
                        </div>
                    </CardPanel>

                    <!-- Upcoming events + jobs -->
                    <div class="grid gap-6 md:grid-cols-2">
                        <CardPanel title="Your upcoming events" :icon="CalendarDays" flush>
                            <template #actions><AppButton variant="ghost" size="sm" :href="route('events.index')">All events</AppButton></template>
                            <EmptyState v-if="myEvents.length === 0" compact :icon="CalendarDays" title="Nothing booked yet" description="Reunions, webinars and chapter meets are listed under Events." />
                            <ul v-else class="divide-y divide-line-soft">
                                <li v-for="e in myEvents" :key="e.slug">
                                    <Link :href="route('events.show', e.slug)" class="flex items-center gap-3 px-5 py-3 hover:bg-surface-muted">
                                        <span class="grid size-10 shrink-0 place-items-center rounded-md bg-amber-50 text-amber-600 dark:bg-amber-500/15"><CalendarDays :size="18" /></span>
                                        <span class="min-w-0">
                                            <span class="block truncate text-sm font-medium text-ink">{{ e.title }}</span>
                                            <span class="block text-xs text-muted">{{ e.starts_at }}</span>
                                        </span>
                                    </Link>
                                </li>
                            </ul>
                        </CardPanel>

                        <CardPanel title="Latest jobs" :icon="Briefcase" flush>
                            <template #actions><AppButton v-if="latestJobs.length" variant="ghost" size="sm" :href="route('jobs.index')">All jobs</AppButton></template>
                            <EmptyState
                                v-if="latestJobs.length === 0"
                                compact
                                :icon="Briefcase"
                                :title="verified ? 'No openings right now' : 'Jobs open after verification'"
                                :description="verified ? 'Alumni post jobs and referrals here — check back soon.' : 'Verified members can see and post jobs.'"
                            />
                            <ul v-else class="divide-y divide-line-soft">
                                <li v-for="j in latestJobs" :key="j.id">
                                    <Link :href="route('jobs.show', j.id)" class="flex items-center justify-between gap-3 px-5 py-3 hover:bg-surface-muted">
                                        <span class="min-w-0">
                                            <span class="block truncate text-sm font-medium text-ink">{{ j.title }}</span>
                                            <span class="block truncate text-xs text-muted">{{ [j.organization, j.location].filter(Boolean).join(' · ') }}</span>
                                        </span>
                                        <StatusBadge :status="j.type === 'internship' ? 'upcoming' : 'open'" :label="j.type" :icon="false" />
                                    </Link>
                                </li>
                            </ul>
                        </CardPanel>
                    </div>

                    <!-- People you may know -->
                    <CardPanel v-if="suggestions.length" title="People you may know" description="From your programme and nearby batches." :icon="UserPlus">
                        <template #actions><AppButton variant="ghost" size="sm" :href="route('connections.index')">See all</AppButton></template>
                        <ul class="grid gap-3 sm:grid-cols-2">
                            <li v-for="s in suggestions" :key="s.profile_id">
                                <Link :href="route('alumni.show', s.profile_id)" class="flex items-center gap-3 rounded-md p-2.5 ring-1 ring-line ring-inset hover:bg-surface-muted">
                                    <AvatarImage :name="s.name" :src="null" />
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-medium text-ink">{{ s.name }}</span>
                                        <span class="block truncate text-xs text-muted">{{ s.subtitle }}</span>
                                    </span>
                                    <StatusBadge status="upcoming" :label="s.reason" :icon="false" />
                                </Link>
                            </li>
                        </ul>
                    </CardPanel>
                </div>

                <!-- Right column -->
                <div class="space-y-6">
                    <CardPanel v-if="profile" title="Your profile" :icon="UserRoundPen">
                        <template #actions><StatusBadge :status="profile.verification_status" /></template>
                        <div class="flex items-baseline justify-between text-sm">
                            <span class="text-muted">Profile completeness</span>
                            <span class="font-semibold text-ink tabular-nums">{{ profile.completion }}%</span>
                        </div>
                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-surface-sunken" role="progressbar" :aria-valuenow="profile.completion" aria-valuemin="0" aria-valuemax="100" aria-label="Profile completeness">
                            <div class="h-full rounded-full bg-brand-600 transition-[width]" :style="{ width: `${profile.completion}%` }" />
                        </div>
                        <p v-if="profile.completion < 100" class="mt-2 text-xs text-muted">A complete profile helps classmates and mentors find you.</p>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <AppButton size="sm" :href="route('profile.edit')">Edit profile</AppButton>
                            <AppButton v-if="profile.verification_status === 'verified'" size="sm" variant="secondary" :href="route('alumni.show', profile.id)">View public profile</AppButton>
                        </div>
                    </CardPanel>

                    <CardPanel v-if="!user.two_factor_enabled && !user.two_factor_required" title="Protect your account" :icon="ShieldCheck">
                        <p class="text-sm text-muted">Turn on two-factor authentication so a stolen password alone can't get anyone into your account.</p>
                        <AppButton size="sm" variant="outline" class="mt-4" :href="route('profile.security')">Set up 2FA</AppButton>
                    </CardPanel>

                    <CardPanel title="Recent activity" :icon="Bell" flush>
                        <template #actions><AppButton variant="ghost" size="sm" :href="route('notifications.index')">View all</AppButton></template>
                        <EmptyState v-if="activity.length === 0" compact :icon="Bell" title="You’re all caught up" description="Connection requests, replies and event updates will appear here." />
                        <ol v-else class="relative space-y-0 px-5 py-4">
                            <li v-for="(n, i) in activity" :key="n.id" class="relative flex gap-3 pb-4 last:pb-0">
                                <span v-if="i < activity.length - 1" class="absolute top-4 left-[5px] h-full w-px bg-line" aria-hidden="true" />
                                <span :class="['relative mt-1.5 size-[11px] shrink-0 rounded-full ring-4 ring-surface', n.read ? 'bg-line-strong' : 'bg-brand-500']" aria-hidden="true" />
                                <button type="button" class="min-w-0 text-left" @click="openNotification(n.id)">
                                    <span :class="['block text-sm', n.read ? 'text-ink-soft' : 'font-medium text-ink']">{{ n.title }}</span>
                                    <span class="block text-xs text-subtle">{{ n.at }}<span v-if="!n.read" class="sr-only"> (unread)</span></span>
                                </button>
                            </li>
                        </ol>
                    </CardPanel>

                    <CardPanel title="The network" :icon="ShieldCheck">
                        <p class="text-3xl font-semibold tracking-tight text-ink tabular-nums">{{ alumniCount.toLocaleString('en-IN') }}</p>
                        <p class="text-sm text-muted">verified ABV-IIITM alumni</p>
                        <AppButton v-if="canBrowseDirectory" size="sm" variant="secondary" class="mt-4" :icon="Search" :href="route('directory')">Browse directory</AppButton>
                        <p v-else class="mt-3 text-xs text-muted">The directory opens once your alumni status is verified.</p>
                    </CardPanel>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
