<script setup lang="ts">
import AlertBox from '@/Components/AlertBox.vue';
import AppButton from '@/Components/AppButton.vue';
import AvatarImage from '@/Components/AvatarImage.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PostCard, { type FeedPost } from '@/Components/PostCard.vue';
import PostComposer from '@/Components/PostComposer.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { ArrowRight, Bell, Briefcase, CalendarDays, Flame, GraduationCap, Megaphone, MapPin, Rss, Search, ShieldCheck, Sparkles, UserPlus, UserRoundPen } from 'lucide-vue-next';
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
    feed: FeedPost[];
    announcements: { id: number; body: string; author: string; at: string }[];
    trending: { name: string; slug: string; posts: number; members: number }[];
    upcoming: { slug: string; title: string; day: string; month: string; when: string; where: string | null }[];
    reportReasons: Record<string, string>;
}>();

const page = usePage();
const user = computed(() => page.props.auth.user!);
const firstName = computed(() => user.value.name.split(' ')[0]);
const greeting = computed(() => {
    const h = new Date().getHours();
    return h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening';
});
const member = computed(() => user.value.is_member);

const quickActions = computed(() =>
    [
        { label: 'Find alumni', icon: Search, href: route('directory'), show: props.canBrowseDirectory },
        { label: 'Find a mentor', icon: GraduationCap, href: route('mentoring.find'), show: member.value },
        { label: 'Jobs', icon: Briefcase, href: route('jobs.index'), show: member.value },
        { label: 'Events', icon: CalendarDays, href: route('events.index'), show: true },
        { label: 'Edit profile', icon: UserRoundPen, href: route('profile.edit'), show: true },
    ].filter((a) => a.show),
);

// Profile completeness ring (SVG): circumference of r=18.
const C = 2 * Math.PI * 18;
const ringOffset = computed(() => C * (1 - (props.profile?.completion ?? 0) / 100));

const openNotification = (id: string) => router.post(route('notifications.open', id));
</script>

<template>
    <AppLayout title="Home">
        <h1 class="sr-only">Home</h1>
        <div class="grid grid-cols-[minmax(0,1fr)] gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <!-- Main column -->
            <div class="min-w-0 space-y-5">
                <!-- Greeting -->
                <section class="relative overflow-hidden rounded-[var(--radius-card)] bg-gradient-to-br from-deep-800 via-deep-700 to-accent-600 p-5 text-white sm:p-6">
                    <span class="pointer-events-none absolute -top-16 -right-10 size-56 rounded-full bg-white/10 blur-2xl" aria-hidden="true" />
                    <span class="pointer-events-none absolute -bottom-20 left-1/3 size-48 rounded-full bg-accent-400/30 blur-3xl" aria-hidden="true" />
                    <div class="relative flex items-center gap-4">
                        <div class="relative shrink-0">
                            <svg v-if="profile" viewBox="0 0 40 40" class="absolute -inset-1.5 size-[calc(100%+0.75rem)] -rotate-90" aria-hidden="true">
                                <circle cx="20" cy="20" r="18" fill="none" stroke="rgb(255 255 255 / 0.2)" stroke-width="2.5" />
                                <circle cx="20" cy="20" r="18" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" :stroke-dasharray="C" :stroke-dashoffset="ringOffset" />
                            </svg>
                            <AvatarImage :name="user.name" size="lg" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-white/75">{{ greeting }},</p>
                            <p class="truncate text-2xl font-extrabold tracking-tight">{{ firstName }}</p>
                            <p v-if="profile" class="mt-0.5 truncate text-sm text-white/75">{{ profile.programme }} · Batch of {{ profile.graduation_year }}</p>
                        </div>
                        <Link
                            v-if="profile && profile.completion < 100"
                            :href="route('profile.edit')"
                            class="ml-auto hidden shrink-0 rounded-full bg-white/15 px-3 py-1.5 text-xs font-semibold backdrop-blur hover:bg-white/25 sm:block"
                            >Profile {{ profile.completion }}% complete</Link
                        >
                    </div>
                    <div class="relative -mx-5 mt-5 flex gap-2 overflow-x-auto px-5 scrollbar-none sm:-mx-6 sm:px-6">
                        <Link
                            v-for="a in quickActions"
                            :key="a.label"
                            :href="a.href"
                            class="press inline-flex shrink-0 items-center gap-1.5 rounded-full bg-white/15 px-3.5 py-2 text-[13px] font-semibold ring-1 ring-white/20 backdrop-blur transition hover:bg-white/25"
                        >
                            <component :is="a.icon" :size="15" aria-hidden="true" />{{ a.label }}
                        </Link>
                    </div>
                </section>

                <!-- Needs attention -->
                <AlertBox v-if="profile?.verification_status === 'pending'" tone="warning" title="Verification in progress">
                    We couldn't match your details to the institute's records automatically, so the alumni office is reviewing them — usually within a few working days. Your feed,
                    the directory and jobs open once you're verified.
                </AlertBox>
                <AlertBox v-else-if="profile?.verification_status === 'rejected'" tone="danger" title="We couldn't verify your alumni status">
                    <p>{{ profile.rejection_reason }}</p>
                    <p class="mt-1">Please contact the alumni office if you think this is a mistake.</p>
                </AlertBox>
                <div v-if="pending.mentoring || pending.referrals" class="flex flex-wrap gap-2">
                    <Link
                        v-if="pending.mentoring"
                        :href="route('mentoring.index', { tab: 'mentoring' })"
                        class="press inline-flex items-center gap-2 rounded-full bg-brand-50 px-4 py-2 text-sm font-semibold text-brand-700"
                    >
                        <span class="size-2 rounded-full bg-accent-500" aria-hidden="true" />{{ pending.mentoring }} mentoring {{ pending.mentoring === 1 ? 'request' : 'requests' }} waiting
                    </Link>
                    <Link v-if="pending.referrals" :href="route('jobs.referrals')" class="press inline-flex items-center gap-2 rounded-full bg-brand-50 px-4 py-2 text-sm font-semibold text-brand-700">
                        <span class="size-2 rounded-full bg-accent-500" aria-hidden="true" />{{ pending.referrals }} referral {{ pending.referrals === 1 ? 'request' : 'requests' }} waiting
                    </Link>
                </div>

                <!-- Up next: event strip -->
                <section v-if="upcoming.length" aria-labelledby="upnext">
                    <div class="mb-2.5 flex items-center justify-between">
                        <h2 id="upnext" class="text-base font-bold text-ink">Up next</h2>
                        <Link :href="route('events.index')" class="text-sm font-semibold text-brand-600 hover:underline dark:text-brand-300">All events</Link>
                    </div>
                    <ul class="-mx-4 flex snap-x gap-3 overflow-x-auto px-4 pb-1 scrollbar-none sm:mx-0 sm:px-0">
                        <li v-for="e in upcoming" :key="e.slug" class="w-64 shrink-0 snap-start sm:w-auto sm:flex-1">
                            <Link :href="route('events.show', e.slug)" class="card card-hover flex h-full items-start gap-3 p-3.5">
                                <span class="grid w-12 shrink-0 place-items-center rounded-xl bg-gradient-to-b from-brand-500 to-brand-600 py-1.5 text-white">
                                    <span class="text-[10px] font-bold tracking-wide uppercase">{{ e.month }}</span>
                                    <span class="text-xl leading-none font-extrabold">{{ e.day }}</span>
                                </span>
                                <span class="min-w-0">
                                    <span class="line-clamp-2 text-sm leading-snug font-bold text-ink">{{ e.title }}</span>
                                    <span class="mt-0.5 block truncate text-xs text-muted">{{ e.when }}</span>
                                    <span v-if="e.where" class="mt-0.5 flex items-center gap-1 truncate text-xs text-muted"><MapPin :size="11" class="shrink-0" aria-hidden="true" />{{ e.where }}</span>
                                </span>
                            </Link>
                        </li>
                    </ul>
                </section>

                <template v-if="member">
                    <PostComposer :can-announce="false" />

                    <!-- Announcements -->
                    <section v-if="announcements.length" aria-label="Announcements" class="space-y-2">
                        <Link
                            v-for="a in announcements"
                            :key="a.id"
                            :href="route('posts.show', a.id)"
                            class="card card-hover flex items-start gap-3 p-4 ring-1 ring-accent-400/40"
                        >
                            <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-accent-50 text-accent-600"><Megaphone :size="17" aria-hidden="true" /></span>
                            <span class="min-w-0">
                                <span class="text-xs font-bold tracking-wide text-accent-600 uppercase">Announcement · {{ a.at }}</span>
                                <span class="mt-0.5 line-clamp-2 block text-sm text-ink">{{ a.body }}</span>
                            </span>
                        </Link>
                    </section>

                    <!-- Feed -->
                    <section aria-labelledby="feed-heading" class="space-y-4">
                        <div class="flex items-center justify-between">
                            <h2 id="feed-heading" class="text-base font-bold text-ink">Latest from your network</h2>
                            <Link :href="route('feed')" class="text-sm font-semibold text-brand-600 hover:underline dark:text-brand-300">Open feed</Link>
                        </div>
                        <EmptyState v-if="feed.length === 0" :icon="Rss" title="It’s quiet here — be the first" description="Share an update, a win or a question. Posts from your network and groups show up here." />
                        <PostCard v-for="p in feed" :key="p.id" :post="p" :report-reasons="reportReasons" />
                        <div v-if="feed.length" class="flex justify-center pt-1">
                            <AppButton variant="secondary" :href="route('feed')" :icon="ArrowRight">See more in your feed</AppButton>
                        </div>
                    </section>
                </template>

                <EmptyState
                    v-else
                    :icon="Sparkles"
                    title="Your feed opens after verification"
                    description="Once the alumni office confirms your details you’ll see posts, people and jobs from the IIITM network here."
                >
                    <AppButton :href="route('profile.edit')">Complete your profile</AppButton>
                </EmptyState>
            </div>

            <!-- Right column -->
            <aside class="space-y-5" aria-label="For you">
                <!-- Profile card -->
                <section class="card overflow-hidden">
                    <div class="h-16 bg-gradient-to-r from-brand-500 via-brand-400 to-accent-400" aria-hidden="true" />
                    <div class="-mt-8 px-5 pb-5">
                        <AvatarImage :name="user.name" size="lg" class="ring-4 ring-surface" />
                        <div class="mt-2 flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="truncate text-base font-bold text-ink">{{ user.name }}</p>
                                <p v-if="profile" class="truncate text-[13px] text-muted">{{ profile.programme }} · {{ profile.graduation_year }}</p>
                            </div>
                            <StatusBadge v-if="profile" :status="profile.verification_status" />
                        </div>
                        <dl class="mt-4 grid grid-cols-3 gap-2 text-center">
                            <Link :href="route('connections.index')" class="press rounded-2xl bg-surface-muted py-2 hover:bg-surface-sunken">
                                <dd class="text-lg font-extrabold text-ink tabular-nums">{{ stats.connections }}</dd>
                                <dt class="text-[11px] font-semibold text-muted">Connections</dt>
                            </Link>
                            <Link :href="route('events.index')" class="press rounded-2xl bg-surface-muted py-2 hover:bg-surface-sunken">
                                <dd class="text-lg font-extrabold text-ink tabular-nums">{{ stats.upcomingEvents }}</dd>
                                <dt class="text-[11px] font-semibold text-muted">Events</dt>
                            </Link>
                            <Link :href="route('mentoring.index')" class="press rounded-2xl bg-surface-muted py-2 hover:bg-surface-sunken">
                                <dd class="text-lg font-extrabold text-ink tabular-nums">{{ stats.mentorships }}</dd>
                                <dt class="text-[11px] font-semibold text-muted">Mentoring</dt>
                            </Link>
                        </dl>
                        <div v-if="profile && profile.completion < 100" class="mt-4">
                            <div class="flex items-baseline justify-between text-[13px]">
                                <span class="font-semibold text-ink-soft">Profile strength</span>
                                <span class="font-bold text-ink tabular-nums">{{ profile.completion }}%</span>
                            </div>
                            <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-surface-sunken" role="progressbar" :aria-valuenow="profile.completion" aria-valuemin="0" aria-valuemax="100" aria-label="Profile strength">
                                <div class="h-full rounded-full bg-gradient-to-r from-brand-500 to-accent-500 transition-[width]" :style="{ width: `${profile.completion}%` }" />
                            </div>
                        </div>
                        <div class="mt-4 flex gap-2">
                            <AppButton v-if="profile?.verification_status === 'verified'" size="sm" variant="secondary" class="flex-1" :href="route('alumni.show', profile.id)">View profile</AppButton>
                            <AppButton size="sm" class="flex-1" :href="route('profile.edit')">Edit profile</AppButton>
                        </div>
                    </div>
                </section>

                <section v-if="!user.two_factor_enabled && !user.two_factor_required" class="card flex items-start gap-3 p-4">
                    <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-emerald-50 text-emerald-600"><ShieldCheck :size="18" aria-hidden="true" /></span>
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-ink">Lock it down</p>
                        <p class="mt-0.5 text-[13px] text-muted">Add a passkey or two-factor so a leaked password can’t get anyone in.</p>
                        <Link :href="route('profile.security')" class="mt-2 inline-block text-[13px] font-semibold text-brand-600 hover:underline dark:text-brand-300">Secure my account</Link>
                    </div>
                </section>

                <!-- People you may know -->
                <section v-if="suggestions.length" class="card p-5" aria-labelledby="pymk">
                    <h2 id="pymk" class="flex items-center gap-2 text-base font-bold text-ink"><UserPlus :size="18" class="text-brand-600" aria-hidden="true" />People you may know</h2>
                    <ul class="mt-3 space-y-3">
                        <li v-for="s in suggestions" :key="s.profile_id" class="flex items-center gap-3">
                            <AvatarImage :name="s.name" size="sm" />
                            <div class="min-w-0 flex-1">
                                <Link :href="route('alumni.show', s.profile_id)" class="block truncate text-sm font-bold text-ink hover:underline">{{ s.name }}</Link>
                                <p class="truncate text-xs text-muted">{{ s.reason }} · {{ s.subtitle }}</p>
                            </div>
                            <AppButton size="sm" variant="secondary" :href="route('alumni.show', s.profile_id)">View</AppButton>
                        </li>
                    </ul>
                </section>

                <!-- Trending -->
                <section v-if="trending.length" class="card p-5" aria-labelledby="trending">
                    <h2 id="trending" class="flex items-center gap-2 text-base font-bold text-ink"><Flame :size="18" class="text-accent-500" aria-hidden="true" />Trending groups</h2>
                    <ol class="mt-3 space-y-1">
                        <li v-for="(t, i) in trending" :key="t.slug">
                            <Link :href="route('communities.show', t.slug)" class="-mx-2 flex items-center gap-3 rounded-xl px-2 py-2 hover:bg-surface-muted">
                                <span class="w-4 text-sm font-extrabold text-subtle tabular-nums">{{ i + 1 }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-bold text-ink">{{ t.name }}</span>
                                    <span class="block text-xs text-muted">{{ t.posts }} new {{ t.posts === 1 ? 'post' : 'posts' }} this week · {{ t.members }} members</span>
                                </span>
                            </Link>
                        </li>
                    </ol>
                </section>

                <!-- Jobs -->
                <section v-if="latestJobs.length" class="card p-5" aria-labelledby="jobs">
                    <div class="flex items-center justify-between">
                        <h2 id="jobs" class="flex items-center gap-2 text-base font-bold text-ink"><Briefcase :size="18" class="text-brand-600" aria-hidden="true" />Fresh jobs</h2>
                        <Link :href="route('jobs.index')" class="text-[13px] font-semibold text-brand-600 hover:underline dark:text-brand-300">All</Link>
                    </div>
                    <ul class="mt-3 space-y-1">
                        <li v-for="j in latestJobs" :key="j.id">
                            <Link :href="route('jobs.show', j.id)" class="-mx-2 block rounded-xl px-2 py-2 hover:bg-surface-muted">
                                <span class="flex items-center justify-between gap-2">
                                    <span class="truncate text-sm font-bold text-ink">{{ j.title }}</span>
                                    <span :class="['shrink-0 rounded-full px-2 py-0.5 text-[11px] font-bold capitalize', j.type === 'internship' ? 'bg-accent-50 text-accent-600' : 'bg-brand-50 text-brand-700']">{{ j.type }}</span>
                                </span>
                                <span class="block truncate text-xs text-muted">{{ [j.organization, j.location].filter(Boolean).join(' · ') }}</span>
                            </Link>
                        </li>
                    </ul>
                </section>

                <!-- Activity -->
                <section class="card p-5" aria-labelledby="activity">
                    <div class="flex items-center justify-between">
                        <h2 id="activity" class="flex items-center gap-2 text-base font-bold text-ink"><Bell :size="18" class="text-brand-600" aria-hidden="true" />Activity</h2>
                        <Link :href="route('notifications.index')" class="text-[13px] font-semibold text-brand-600 hover:underline dark:text-brand-300">All</Link>
                    </div>
                    <p v-if="activity.length === 0" class="mt-3 text-sm text-muted">You’re all caught up.</p>
                    <ul v-else class="mt-2 space-y-0.5">
                        <li v-for="n in activity" :key="n.id">
                            <button type="button" class="-mx-2 flex w-[calc(100%+1rem)] items-start gap-2.5 rounded-xl px-2 py-2 text-left hover:bg-surface-muted" @click="openNotification(n.id)">
                                <span :class="['mt-1.5 size-2 shrink-0 rounded-full', n.read ? 'bg-line-strong' : 'bg-accent-500']" aria-hidden="true" />
                                <span class="min-w-0">
                                    <span :class="['block text-[13px] leading-snug', n.read ? 'text-ink-soft' : 'font-bold text-ink']">{{ n.title }}</span>
                                    <span class="block text-xs text-subtle">{{ n.at }}<span v-if="!n.read" class="sr-only"> (unread)</span></span>
                                </span>
                            </button>
                        </li>
                    </ul>
                </section>

                <p class="px-1 text-xs text-subtle">{{ alumniCount.toLocaleString('en-IN') }} verified alumni · © {{ new Date().getFullYear() }} ABV-IIITM Gwalior Alumni Association</p>
            </aside>
        </div>
    </AppLayout>
</template>
