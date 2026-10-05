<script setup lang="ts">
import AppLogo from '@/Components/AppLogo.vue';
import FlashMessages from '@/Components/FlashMessages.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineProps<{ title: string }>();

const page = usePage();
const user = computed(() => page.props.auth.user!);
const counts = computed(() => (page.props.counts as { notifications: number; connectionRequests: number } | null) ?? { notifications: 0, connectionRequests: 0 });
const can = (permission: string) => user.value.permissions.includes(permission);
const menuOpen = ref(false);

interface NavItem {
    label: string;
    route: string;
    match: string;
    badge?: number;
    show?: boolean;
}

// Modules appear as their routes ship (route().has), so the nav never links
// to a page that does not exist yet.
const mainNav = computed<NavItem[]>(() =>
    [
        { label: 'Dashboard', route: 'dashboard', match: 'dashboard' },
        { label: 'Feed', route: 'feed', match: 'feed' },
        { label: 'Directory', route: 'directory', match: 'directory' },
        { label: 'Connections', route: 'connections.index', match: 'connections.*', badge: counts.value.connectionRequests },
        { label: 'Communities', route: 'communities.index', match: 'communities.*' },
        { label: 'Events', route: 'events.index', match: 'events.*' },
        { label: 'Jobs & internships', route: 'jobs.index', match: 'jobs.*' },
        { label: 'Mentoring', route: 'mentoring.index', match: 'mentoring.*' },
    ].filter((i) => route().has(i.route)),
);

const accountNav = computed<NavItem[]>(() => [
    { label: 'My profile', route: 'profile.edit', match: 'profile.edit' },
    { label: 'Security', route: 'profile.security', match: 'profile.security' },
    { label: 'Sessions', route: 'profile.sessions', match: 'profile.sessions' },
]);

const adminNav = computed<NavItem[]>(() =>
    [
        { label: 'Overview', route: 'admin.dashboard', match: 'admin.dashboard', show: true },
        { label: 'Alumni', route: 'admin.alumni.index', match: 'admin.alumni.*', show: can('alumni.view') },
        { label: 'Verification', route: 'admin.verification.index', match: 'admin.verification.*', show: can('alumni.verify') },
        { label: 'Events', route: 'admin.events.index', match: 'admin.events.*', show: can('events.view') },
        { label: 'Jobs', route: 'admin.jobs.index', match: 'admin.jobs.*', show: can('jobs.moderate') },
        { label: 'Communities', route: 'admin.communities.index', match: 'admin.communities.*', show: can('communities.moderate') || can('chapters.manage') },
        { label: 'Moderation', route: 'admin.moderation.index', match: 'admin.moderation.*', show: ['communities.moderate', 'jobs.moderate', 'users.manage'].some(can) },
        { label: 'Reports', route: 'admin.reports.index', match: 'admin.reports.*', show: can('reports.view') },
        { label: 'Users', route: 'admin.users.index', match: 'admin.users.*', show: can('users.view') },
        { label: 'Programmes', route: 'admin.programmes.index', match: 'admin.programmes.*', show: can('alumni.update') },
        { label: 'Audit log', route: 'admin.audit-logs.index', match: 'admin.audit-logs.*', show: can('audit.view') },
    ].filter((i) => i.show && route().has(i.route)),
);

const isAdminArea = computed(() => route().current('admin.*'));
const linkClass = (item: NavItem) => [
    'flex items-center justify-between rounded-md px-3 py-2 text-sm font-medium',
    route().current(item.match) ? 'bg-brand-50 text-brand-800' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900',
];

const logout = () => router.post(route('logout'));
</script>

<template>
    <Head :title="title" />
    <FlashMessages />
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-50 focus:rounded focus:bg-white focus:px-3 focus:py-2">Skip to content</a>

    <div class="min-h-full">
        <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur">
            <div class="flex h-16 items-center justify-between gap-4 px-4 sm:px-6">
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        class="rounded-md p-2 text-slate-600 lg:hidden"
                        :aria-expanded="menuOpen"
                        aria-controls="sidebar"
                        @click="menuOpen = !menuOpen"
                    >
                        <span class="sr-only">Menu</span>
                        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                    </button>
                    <Link :href="route('dashboard')"><AppLogo /></Link>
                </div>

                <div class="flex items-center gap-2 sm:gap-4">
                    <Link
                        v-if="user.can_access_admin"
                        :href="route(isAdminArea ? 'dashboard' : 'admin.dashboard')"
                        class="hidden rounded-md px-3 py-1.5 text-sm font-medium text-brand-700 ring-1 ring-brand-200 hover:bg-brand-50 sm:block"
                        >{{ isAdminArea ? 'Leave admin' : 'Administration' }}</Link
                    >
                    <Link
                        :href="route('notifications.index')"
                        class="relative rounded-full p-2 text-slate-600 hover:bg-slate-100"
                        :aria-label="`Notifications${counts.notifications ? ` (${counts.notifications} unread)` : ''}`"
                    >
                        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"
                            />
                        </svg>
                        <span v-if="counts.notifications" class="absolute top-1 right-1 grid min-w-4 place-items-center rounded-full bg-accent-500 px-1 text-[10px] font-bold text-brand-950">{{
                            counts.notifications > 99 ? '99+' : counts.notifications
                        }}</span>
                    </Link>
                    <span class="hidden max-w-40 truncate text-sm text-slate-700 sm:block">{{ user.name }}</span>
                    <button type="button" class="text-sm font-medium text-brand-700 hover:text-brand-900" @click="logout">Sign out</button>
                </div>
            </div>
        </header>

        <div class="flex">
            <aside
                id="sidebar"
                :class="[
                    'fixed inset-y-16 left-0 z-20 w-64 shrink-0 overflow-y-auto border-r border-slate-200 bg-white px-3 py-5 lg:sticky lg:top-16 lg:block lg:h-[calc(100vh-4rem)]',
                    menuOpen ? 'block' : 'hidden',
                ]"
            >
                <nav class="space-y-6" aria-label="Sidebar">
                    <div v-if="isAdminArea && adminNav.length">
                        <p class="px-3 pb-2 text-xs font-semibold tracking-wide text-slate-400 uppercase">Administration</p>
                        <Link v-for="item in adminNav" :key="item.route" :href="route(item.route)" :class="linkClass(item)" :aria-current="route().current(item.match) ? 'page' : undefined">
                            {{ item.label }}
                        </Link>
                    </div>
                    <div>
                        <p class="px-3 pb-2 text-xs font-semibold tracking-wide text-slate-400 uppercase">Network</p>
                        <Link v-for="item in mainNav" :key="item.route" :href="route(item.route)" :class="linkClass(item)" :aria-current="route().current(item.match) ? 'page' : undefined">
                            {{ item.label }}
                            <span v-if="item.badge" class="rounded-full bg-accent-500 px-1.5 text-xs font-bold text-brand-950">{{ item.badge }}</span>
                        </Link>
                    </div>
                    <div>
                        <p class="px-3 pb-2 text-xs font-semibold tracking-wide text-slate-400 uppercase">Account</p>
                        <Link v-for="item in accountNav" :key="item.route" :href="route(item.route)" :class="linkClass(item)" :aria-current="route().current(item.match) ? 'page' : undefined">
                            {{ item.label }}
                        </Link>
                        <Link v-if="user.can_access_admin && !isAdminArea" :href="route('admin.dashboard')" class="mt-1 flex rounded-md px-3 py-2 text-sm font-medium text-brand-700 hover:bg-brand-50 sm:hidden"
                            >Administration</Link
                        >
                    </div>
                </nav>
            </aside>
            <div v-if="menuOpen" class="fixed inset-0 top-16 z-10 bg-slate-900/30 lg:hidden" aria-hidden="true" @click="menuOpen = false" />

            <main id="main" class="min-w-0 flex-1 px-4 py-8 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-6xl"><slot /></div>
            </main>
        </div>
    </div>
</template>
