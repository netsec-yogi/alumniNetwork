<script setup lang="ts">
import AppLogo from '@/Components/AppLogo.vue';
import DropdownMenu from '@/Components/DropdownMenu.vue';
import ConfirmHost from '@/Components/ConfirmHost.vue';
import FlashMessages from '@/Components/FlashMessages.vue';
import { accountNav, buildNavigation, isActive, type NavItem } from '@/lib/navigation';
import { isDark, openGroups, sidebarMode, themePref, type ThemePref } from '@/lib/ui';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Bell, ChevronDown, ChevronRight, LogOut, Menu, MessageSquare, Monitor, Moon, PanelLeftClose, PanelLeftOpen, Search, Sun, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

defineProps<{ title: string }>();

const page = usePage();
const user = computed(() => page.props.auth.user!);
const counts = computed(() => (page.props.counts as Record<'notifications' | 'connectionRequests' | 'messages', number> | null) ?? { notifications: 0, connectionRequests: 0, messages: 0 });
const sections = computed(() => buildNavigation({ user: user.value, ai: page.props.features.ai }));

// Desktop: expanded or icon-only (remembered). Mobile: an off-canvas drawer.
const collapsed = computed(() => sidebarMode.value === 'collapsed');
const mobileOpen = ref(false);
const isDesktop = ref(true);
let mq: MediaQueryList | undefined;
const onMq = () => (isDesktop.value = !!mq?.matches);
onMounted(() => {
    mq = window.matchMedia('(min-width: 1024px)');
    onMq();
    mq.addEventListener('change', onMq);
});
onBeforeUnmount(() => mq?.removeEventListener('change', onMq));
const iconOnly = computed(() => collapsed.value && isDesktop.value);

function toggleSidebar() {
    if (isDesktop.value) sidebarMode.value = collapsed.value ? 'expanded' : 'collapsed';
    else mobileOpen.value = !mobileOpen.value;
}

// Submenus: the active group opens itself; the user can open/close any.
const groupActive = (item: NavItem) => isActive(item) || !!item.children?.some(isActive);
const isOpen = (item: NavItem) => (openGroups.value.has(item.label) ? true : openGroups.value.has(`-${item.label}`) ? false : groupActive(item));
function toggleGroup(item: NavItem) {
    const open = isOpen(item);
    const next = new Set(openGroups.value);
    next.delete(item.label);
    next.delete(`-${item.label}`);
    next.add(open ? `-${item.label}` : item.label);
    openGroups.value = next;
}

// Icon-only mode: tooltips and submenu flyouts, positioned with `fixed` so
// the scrolling sidebar can't clip them.
const flyout = ref<{ item: NavItem; top: number } | null>(null);
let hideTimer: ReturnType<typeof setTimeout> | undefined;
function showFlyout(item: NavItem, e: Event) {
    if (!iconOnly.value) return;
    clearTimeout(hideTimer);
    flyout.value = { item, top: (e.currentTarget as HTMLElement).getBoundingClientRect().top };
}
const hideFlyout = () => (hideTimer = setTimeout(() => (flyout.value = null), 120));
const keepFlyout = () => clearTimeout(hideTimer);

const badge = (item: { badge?: 'messages' | 'connectionRequests' | 'notifications' }) => (item.badge ? counts.value[item.badge] : 0);
const fmt = (n: number) => (n > 99 ? '99+' : String(n));

const initials = computed(() =>
    user.value.name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((p) => p[0]!.toUpperCase())
        .join(''),
);
const roleLabel = computed(() => (user.value.roles[0] ?? 'member').replace(/_/g, ' '));

const q = ref('');
const search = () => q.value.trim() && router.get(route('directory'), { q: q.value.trim() });

const logout = () => router.post(route('logout'));

const themes: { value: ThemePref; label: string; icon: typeof Sun }[] = [
    { value: 'light', label: 'Light', icon: Sun },
    { value: 'dark', label: 'Dark', icon: Moon },
    { value: 'system', label: 'System', icon: Monitor },
];
</script>

<template>
    <Head :title="title" />
    <FlashMessages />
    <ConfirmHost />
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[70] focus:rounded-md focus:bg-surface focus:px-3 focus:py-2 focus:shadow-pop">Skip to content</a>

    <div class="min-h-dvh">
        <!-- Sidebar -->
        <aside
            id="sidebar"
            :class="[
                'fixed inset-y-0 left-0 z-50 flex w-[260px] flex-col border-r border-line bg-sidebar transition-[width,translate] duration-200 ease-out lg:z-30 lg:translate-x-0',
                iconOnly && 'lg:w-[72px]',
                mobileOpen ? 'translate-x-0 shadow-pop' : '-translate-x-full',
            ]"
            aria-label="Main navigation"
        >
            <div :class="['flex h-16 shrink-0 items-center border-b border-line', iconOnly ? 'justify-center px-2' : 'justify-between px-5']">
                <Link :href="route('dashboard')" aria-label="Dashboard"><AppLogo :compact="iconOnly" /></Link>
                <button type="button" class="rounded-md p-1.5 text-muted hover:bg-surface-sunken lg:hidden" aria-label="Close menu" @click="mobileOpen = false"><X :size="18" /></button>
            </div>

            <nav class="flex-1 overflow-y-auto overscroll-contain px-3 py-4" :class="iconOnly && 'px-2'">
                <div v-for="(section, si) in sections" :key="section.title" :class="si > 0 && 'mt-5'">
                    <p v-if="!iconOnly" class="px-3 pb-1.5 text-[11px] font-semibold tracking-[0.08em] text-subtle uppercase">{{ section.title }}</p>
                    <div v-else-if="si > 0" class="mx-3 mb-3 border-t border-line" aria-hidden="true" />
                    <ul class="space-y-0.5" :aria-label="section.title">
                        <li v-for="item in section.items" :key="item.label" @mouseenter="showFlyout(item, $event)" @mouseleave="hideFlyout" @focusin="showFlyout(item, $event)" @focusout="hideFlyout">
                            <!-- Group with submenu -->
                            <template v-if="item.children">
                                <component
                                    :is="iconOnly ? Link : 'button'"
                                    :href="iconOnly ? route(item.route) : undefined"
                                    :type="iconOnly ? undefined : 'button'"
                                    :aria-expanded="iconOnly ? undefined : isOpen(item)"
                                    :aria-label="iconOnly ? item.label : undefined"
                                    :class="[
                                        'group flex w-full items-center gap-3 rounded-md text-sm font-medium transition-colors',
                                        iconOnly ? 'justify-center p-2.5' : 'px-3 py-2',
                                        groupActive(item) ? 'text-brand-600 dark:text-brand-300' : 'text-ink-soft hover:bg-surface-sunken hover:text-ink',
                                        iconOnly && groupActive(item) && 'bg-brand-50 dark:bg-brand-500/15',
                                    ]"
                                    @click="!iconOnly && toggleGroup(item)"
                                >
                                    <component :is="item.icon" :size="18" class="shrink-0" aria-hidden="true" />
                                    <template v-if="!iconOnly">
                                        <span class="flex-1 truncate text-left">{{ item.label }}</span>
                                        <ChevronRight :size="15" :class="['shrink-0 text-subtle transition-transform', isOpen(item) && 'rotate-90']" aria-hidden="true" />
                                    </template>
                                </component>
                                <ul v-if="!iconOnly && isOpen(item)" class="mt-0.5 mb-1 ml-[1.35rem] space-y-0.5 border-l border-line pl-3">
                                    <li v-for="child in item.children" :key="child.route">
                                        <Link
                                            :href="route(child.route)"
                                            :aria-current="isActive(child) ? 'page' : undefined"
                                            :class="[
                                                'relative block rounded-md px-3 py-1.5 text-[13px] transition-colors',
                                                isActive(child) ? 'font-medium text-brand-600 dark:text-brand-300' : 'text-muted hover:text-ink',
                                            ]"
                                        >
                                            <span v-if="isActive(child)" class="absolute top-1/2 -left-[0.84rem] h-4 w-0.5 -translate-y-1/2 rounded-full bg-brand-600" aria-hidden="true" />
                                            {{ child.label }}
                                        </Link>
                                    </li>
                                </ul>
                            </template>
                            <!-- Single link -->
                            <Link
                                v-else
                                :href="route(item.route)"
                                :aria-current="isActive(item) ? 'page' : undefined"
                                :aria-label="iconOnly ? item.label : undefined"
                                :class="[
                                    'relative flex items-center gap-3 rounded-md text-sm font-medium transition-colors',
                                    iconOnly ? 'justify-center p-2.5' : 'px-3 py-2',
                                    isActive(item) ? 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300' : 'text-ink-soft hover:bg-surface-sunken hover:text-ink',
                                ]"
                            >
                                <component :is="item.icon" :size="18" class="shrink-0" aria-hidden="true" />
                                <span v-if="!iconOnly" class="flex-1 truncate">{{ item.label }}</span>
                                <span
                                    v-if="badge(item)"
                                    :class="[
                                        'rounded-full bg-red-500 text-[10px] font-semibold text-white tabular-nums',
                                        iconOnly ? 'absolute top-1 right-1 grid size-4 place-items-center' : 'px-1.5 py-px',
                                    ]"
                                    >{{ fmt(badge(item)) }}</span
                                >
                            </Link>
                        </li>
                    </ul>
                </div>
            </nav>
        </aside>

        <!-- Collapsed-sidebar tooltip / submenu flyout -->
        <div
            v-if="flyout && iconOnly"
            class="fixed left-[76px] z-50 min-w-44 rounded-md bg-surface py-1.5 shadow-pop ring-1 ring-line"
            :style="{ top: `${flyout.top}px` }"
            @mouseenter="keepFlyout"
            @mouseleave="hideFlyout"
        >
            <p class="px-3.5 py-1 text-[13px] font-semibold text-ink">{{ flyout.item.label }}</p>
            <template v-if="flyout.item.children">
                <Link
                    v-for="child in flyout.item.children"
                    :key="child.route"
                    :href="route(child.route)"
                    :class="['block px-3.5 py-1.5 text-[13px]', isActive(child) ? 'font-medium text-brand-600' : 'text-muted hover:bg-surface-muted hover:text-ink']"
                    @focusin="keepFlyout"
                    @focusout="hideFlyout"
                    >{{ child.label }}</Link
                >
            </template>
        </div>

        <div v-if="mobileOpen" class="fixed inset-0 z-40 bg-slate-950/40 backdrop-blur-[1px] lg:hidden" aria-hidden="true" @click="mobileOpen = false" />

        <!-- Page -->
        <div :class="['flex min-h-dvh flex-col transition-[padding] duration-200 ease-out', iconOnly ? 'lg:pl-[72px]' : 'lg:pl-[260px]']">
            <header class="sticky top-0 z-20 flex h-16 shrink-0 items-center gap-3 bg-topbar/95 px-4 shadow-[0_1px_0_var(--line)] backdrop-blur sm:px-6">
                <button
                    type="button"
                    class="rounded-md p-2 text-muted hover:bg-surface-sunken hover:text-ink"
                    :aria-label="isDesktop ? (collapsed ? 'Expand sidebar' : 'Collapse sidebar') : 'Open menu'"
                    :aria-expanded="isDesktop ? !collapsed : mobileOpen"
                    aria-controls="sidebar"
                    @click="toggleSidebar"
                >
                    <Menu v-if="!isDesktop" :size="20" />
                    <PanelLeftOpen v-else-if="collapsed" :size="20" />
                    <PanelLeftClose v-else :size="20" />
                </button>

                <form v-if="user.is_member" role="search" class="relative hidden w-full max-w-sm md:block" @submit.prevent="search">
                    <label for="global-search" class="sr-only">Search alumni</label>
                    <Search :size="16" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-subtle" aria-hidden="true" />
                    <input
                        id="global-search"
                        v-model="q"
                        type="search"
                        placeholder="Search alumni by name…"
                        class="h-9 w-full rounded-md border-0 bg-surface-sunken pr-3 pl-9 text-sm text-ink placeholder:text-subtle focus:bg-surface focus:ring-2 focus:ring-brand-500"
                    />
                </form>

                <div class="ml-auto flex items-center gap-1">
                    <button
                        type="button"
                        class="rounded-md p-2 text-muted hover:bg-surface-sunken hover:text-ink"
                        :aria-label="isDark ? 'Switch to light theme' : 'Switch to dark theme'"
                        @click="themePref = isDark ? 'light' : 'dark'"
                    >
                        <Sun v-if="isDark" :size="20" />
                        <Moon v-else :size="20" />
                    </button>
                    <Link
                        v-if="route().has('messages.index')"
                        :href="route('messages.index')"
                        class="relative rounded-md p-2 text-muted hover:bg-surface-sunken hover:text-ink"
                        :aria-label="`Messages${counts.messages ? ` (${counts.messages} unread)` : ''}`"
                    >
                        <MessageSquare :size="20" />
                        <span v-if="counts.messages" class="absolute top-1 right-1 grid h-4 min-w-4 place-items-center rounded-full bg-brand-600 px-1 text-[10px] font-semibold text-white ring-2 ring-topbar">{{
                            fmt(counts.messages)
                        }}</span>
                    </Link>
                    <Link
                        :href="route('notifications.index')"
                        class="relative rounded-md p-2 text-muted hover:bg-surface-sunken hover:text-ink"
                        :aria-label="`Notifications${counts.notifications ? ` (${counts.notifications} unread)` : ''}`"
                    >
                        <Bell :size="20" />
                        <span v-if="counts.notifications" class="absolute top-1 right-1 grid h-4 min-w-4 place-items-center rounded-full bg-red-500 px-1 text-[10px] font-semibold text-white ring-2 ring-topbar">{{
                            fmt(counts.notifications)
                        }}</span>
                    </Link>

                    <div class="mx-2 hidden h-6 w-px bg-line sm:block" aria-hidden="true" />

                    <DropdownMenu label="Account menu" width="w-60">
                        <template #trigger="{ toggle, open }">
                            <button type="button" class="flex items-center gap-2.5 rounded-md p-1 pr-2 hover:bg-surface-sunken" :aria-expanded="open" aria-haspopup="menu" @click.stop="toggle">
                                <span class="grid size-8 place-items-center rounded-full bg-brand-100 text-xs font-semibold text-brand-700 dark:bg-brand-500/20 dark:text-brand-300" aria-hidden="true">{{ initials }}</span>
                                <span class="hidden text-left leading-tight sm:block">
                                    <span class="block max-w-36 truncate text-[13px] font-medium text-ink">{{ user.name }}</span>
                                    <span class="block text-[11px] text-muted capitalize">{{ roleLabel }}</span>
                                </span>
                                <ChevronDown :size="14" class="hidden text-subtle sm:block" aria-hidden="true" />
                            </button>
                        </template>
                        <div class="border-b border-line-soft px-4 py-2.5">
                            <p class="truncate text-sm font-medium text-ink">{{ user.name }}</p>
                            <p class="truncate text-xs text-muted">{{ user.email }}</p>
                        </div>
                        <div class="py-1">
                            <Link v-for="a in accountNav" :key="a.route" :href="route(a.route)" class="dropdown-item"><component :is="a.icon" :size="16" aria-hidden="true" />{{ a.label }}</Link>
                        </div>
                        <div class="border-t border-line-soft px-4 py-2.5" @click.stop>
                            <p class="mb-1.5 text-xs font-medium text-muted">Theme</p>
                            <div class="grid grid-cols-3 gap-1 rounded-md bg-surface-sunken p-0.5" role="radiogroup" aria-label="Theme">
                                <button
                                    v-for="t in themes"
                                    :key="t.value"
                                    type="button"
                                    role="radio"
                                    :aria-checked="themePref === t.value"
                                    :class="['flex items-center justify-center gap-1 rounded px-1.5 py-1 text-xs font-medium', themePref === t.value ? 'bg-surface text-ink shadow-xs' : 'text-muted hover:text-ink']"
                                    @click="themePref = t.value"
                                >
                                    <component :is="t.icon" :size="13" aria-hidden="true" />{{ t.label }}
                                </button>
                            </div>
                        </div>
                        <div class="border-t border-line-soft py-1">
                            <button type="button" class="dropdown-item" @click="logout"><LogOut :size="16" aria-hidden="true" />Sign out</button>
                        </div>
                    </DropdownMenu>
                </div>
            </header>

            <main id="main" class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-7xl"><slot /></div>
            </main>

            <footer class="px-4 py-4 text-xs text-subtle sm:px-6 lg:px-8">
                <div class="mx-auto flex max-w-7xl flex-wrap justify-between gap-2">
                    <span>© {{ new Date().getFullYear() }} ABV-IIITM Gwalior Alumni Association</span>
                    <span class="flex gap-4">
                        <Link :href="route('stories.index')" class="hover:text-muted">Stories</Link>
                        <Link :href="route('giving.index')" class="hover:text-muted">Give</Link>
                    </span>
                </div>
            </footer>
        </div>
    </div>
</template>
