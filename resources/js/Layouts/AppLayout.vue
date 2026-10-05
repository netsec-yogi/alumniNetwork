<script setup lang="ts">
import AppLogo from '@/Components/AppLogo.vue';
import FlashMessages from '@/Components/FlashMessages.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineProps<{ title: string }>();

const page = usePage();
const user = computed(() => page.props.auth.user!);
const can = (permission: string) => user.value.permissions.includes(permission);
const menuOpen = ref(false);

interface NavItem {
    label: string;
    route: string;
    match: string;
    show?: boolean;
}

const mainNav = computed<NavItem[]>(() => [
    { label: 'Dashboard', route: 'dashboard', match: 'dashboard' },
    { label: 'Directory', route: 'directory', match: 'directory' },
    { label: 'My profile', route: 'profile.edit', match: 'profile.edit' },
]);

const adminNav = computed<NavItem[]>(() =>
    [
        { label: 'Overview', route: 'admin.dashboard', match: 'admin.dashboard', show: true },
        { label: 'Verification', route: 'admin.verification.index', match: 'admin.verification.*', show: can('alumni.verify') },
        { label: 'Users', route: 'admin.users.index', match: 'admin.users.*', show: can('users.view') },
        { label: 'Audit log', route: 'admin.audit-logs.index', match: 'admin.audit-logs.*', show: can('audit.view') },
    ].filter((i) => i.show),
);

const isAdminArea = computed(() => route().current('admin.*'));

function logout() {
    router.post(route('logout'));
}
</script>

<template>
    <Head :title="title" />
    <FlashMessages />
    <div class="min-h-full">
        <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-white focus:px-3 focus:py-2">Skip to content</a>

        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-6 px-4 sm:px-6 lg:px-8">
                <Link :href="route('dashboard')"><AppLogo /></Link>

                <nav class="hidden flex-1 items-center gap-1 md:flex" aria-label="Main">
                    <Link
                        v-for="item in mainNav"
                        :key="item.route"
                        :href="route(item.route)"
                        :aria-current="route().current(item.match) ? 'page' : undefined"
                        :class="[
                            'rounded-md px-3 py-2 text-sm font-medium',
                            route().current(item.match) ? 'bg-brand-50 text-brand-800' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900',
                        ]"
                        >{{ item.label }}</Link
                    >
                    <Link
                        v-if="user.can_access_admin"
                        :href="route('admin.dashboard')"
                        :class="[
                            'rounded-md px-3 py-2 text-sm font-medium',
                            isAdminArea ? 'bg-brand-50 text-brand-800' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900',
                        ]"
                        >Administration</Link
                    >
                </nav>

                <div class="hidden items-center gap-3 md:flex">
                    <Link :href="route('profile.security')" class="text-sm text-slate-600 hover:text-slate-900">Security</Link>
                    <span class="text-slate-300" aria-hidden="true">|</span>
                    <span class="max-w-40 truncate text-sm text-slate-700">{{ user.name }}</span>
                    <button type="button" class="text-sm font-medium text-brand-700 hover:text-brand-900" @click="logout">Sign out</button>
                </div>

                <button
                    type="button"
                    class="rounded-md p-2 text-slate-600 md:hidden"
                    :aria-expanded="menuOpen"
                    aria-controls="mobile-menu"
                    @click="menuOpen = !menuOpen"
                >
                    <span class="sr-only">Menu</span>
                    <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>
            </div>

            <nav v-if="menuOpen" id="mobile-menu" class="space-y-1 border-t border-slate-200 px-4 py-3 md:hidden" aria-label="Mobile">
                <Link v-for="item in mainNav" :key="item.route" :href="route(item.route)" class="block rounded-md px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">{{
                    item.label
                }}</Link>
                <Link v-if="user.can_access_admin" :href="route('admin.dashboard')" class="block rounded-md px-3 py-2 text-sm text-slate-700 hover:bg-slate-50"
                    >Administration</Link
                >
                <Link :href="route('profile.security')" class="block rounded-md px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">Security</Link>
                <button type="button" class="block w-full rounded-md px-3 py-2 text-left text-sm text-brand-700 hover:bg-slate-50" @click="logout">Sign out</button>
            </nav>

            <nav v-if="isAdminArea && adminNav.length" class="border-t border-slate-100 bg-slate-50" aria-label="Administration">
                <div class="mx-auto flex max-w-7xl gap-1 overflow-x-auto px-4 sm:px-6 lg:px-8">
                    <Link
                        v-for="item in adminNav"
                        :key="item.route"
                        :href="route(item.route)"
                        :aria-current="route().current(item.match) ? 'page' : undefined"
                        :class="[
                            'border-b-2 px-3 py-2.5 text-sm font-medium whitespace-nowrap',
                            route().current(item.match) ? 'border-accent-500 text-brand-900' : 'border-transparent text-slate-600 hover:text-slate-900',
                        ]"
                        >{{ item.label }}</Link
                    >
                </div>
            </nav>
        </header>

        <main id="main" class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <slot />
        </main>
    </div>
</template>
