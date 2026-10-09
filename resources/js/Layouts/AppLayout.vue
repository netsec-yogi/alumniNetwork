<script setup lang="ts">
import AppLogo from '@/Components/AppLogo.vue';
import AvatarImage from '@/Components/AvatarImage.vue';
import ConfirmHost from '@/Components/ConfirmHost.vue';
import DropdownMenu from '@/Components/DropdownMenu.vue';
import FlashMessages from '@/Components/FlashMessages.vue';
import { accountNav, buildNavigation, isActive, type NavItem } from '@/lib/navigation';
import { isDark, openGroups, themePref, type ThemePref } from '@/lib/ui';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Bell,
    CalendarDays,
    ChevronRight,
    House,
    LayoutGrid,
    LogOut,
    MessageCircle,
    Monitor,
    Moon,
    Plus,
    Search,
    ShieldCheck,
    Sun,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

defineProps<{ title: string }>();

const page = usePage();
const user = computed(() => page.props.auth.user!);
const counts = computed(() => (page.props.counts as Record<'notifications' | 'connectionRequests' | 'messages', number> | null) ?? { notifications: 0, connectionRequests: 0, messages: 0 });

// Context-aware navigation: the admin area shows admin sections; everywhere else shows the member app.
const inAdmin = computed(() => !!route().current('admin.*'));
const allSections = computed(() => buildNavigation({ user: user.value, ai: page.props.features.ai }));
const sections = computed(() => allSections.value.filter((s) => (s.title === 'Administration') === inAdmin.value));

// Rail: full labels on wide screens, icons with tooltips/flyouts between 1024 and 1280px (like X).
const width = ref(1440);
const onResize = () => (width.value = window.innerWidth);
onMounted(() => {
    onResize();
    window.addEventListener('resize', onResize, { passive: true });
});
onBeforeUnmount(() => window.removeEventListener('resize', onResize));
const iconOnly = computed(() => width.value >= 1024 && width.value < 1280);

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

// Icon-only rail: tooltip / submenu flyout, `fixed` so the scrolling rail can't clip it.
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
const roleLabel = computed(() => (user.value.roles[0] ?? 'member').replace(/_/g, ' '));

// Search: inline on desktop, a sheet on phones.
const q = ref('');
const searchOpen = ref(false);
const mobileSearch = ref<HTMLInputElement>();
const search = () => q.value.trim() && router.get(route('directory'), { q: q.value.trim() });
async function openSearch() {
    searchOpen.value = true;
    await nextTick();
    mobileSearch.value?.focus();
}

// Phones: "Menu" opens a sheet with the full navigation.
const menuOpen = ref(false);

// Bottom tab bar (phones). The centre action composes a post.
const tabs = computed(() =>
    [
        { label: 'Home', route: 'dashboard', match: 'dashboard', icon: House },
        { label: 'Network', route: 'directory', match: '(directory|alumni.*|connections.*)', icon: Users, show: user.value.is_member },
        { label: 'Post', route: 'feed', match: '__never__', icon: Plus, create: true, show: user.value.is_member },
        { label: 'Events', route: 'events.index', match: 'events.*', icon: CalendarDays },
    ].filter((t) => t.show !== false),
);

const logout = () => router.post(route('logout'));

const themes: { value: ThemePref; label: string; icon: typeof Sun }[] = [
    { value: 'light', label: 'Light', icon: Sun },
    { value: 'dark', label: 'Dark', icon: Moon },
    { value: 'system', label: 'System', icon: Monitor },
];

const navLink = (active: boolean) => [
    'group relative flex items-center gap-3.5 rounded-2xl text-[15px] transition-colors',
    iconOnly.value ? 'justify-center p-3' : 'px-3.5 py-2.5',
    active ? 'bg-surface font-bold text-ink shadow-card' : 'font-medium text-ink-soft hover:bg-surface/70 hover:text-ink',
];
</script>

<template>
    <Head :title="title" />
    <FlashMessages />
    <ConfirmHost />
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[70] focus:rounded-full focus:bg-surface focus:px-4 focus:py-2 focus:shadow-pop">Skip to content</a>

    <div class="min-h-dvh">
        <!-- Top bar -->
        <header class="sticky top-0 z-30 border-b border-line/70 bg-topbar/85 backdrop-blur-xl">
            <div class="mx-auto flex h-16 max-w-[90rem] items-center gap-3 px-4 sm:px-6">
                <Link :href="route('dashboard')" :aria-label="`${$page.props.branding.name} home`" :class="['shrink-0', !iconOnly && 'lg:w-60']"><AppLogo :compact="iconOnly" /></Link>

                <form v-if="user.is_member" role="search" class="relative hidden w-full max-w-md md:block" @submit.prevent="search">
                    <label for="global-search" class="sr-only">Search alumni</label>
                    <Search :size="17" class="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-subtle" aria-hidden="true" />
                    <input
                        id="global-search"
                        v-model="q"
                        type="search"
                        placeholder="Search people, batches, companies…"
                        class="h-10 w-full rounded-full border-0 !bg-surface-sunken pr-4 pl-11 text-sm text-ink placeholder:text-subtle focus:!bg-surface focus:ring-2 focus:ring-brand-500"
                    />
                </form>

                <div class="ml-auto flex items-center gap-1 sm:gap-1.5">
                    <button v-if="user.is_member" type="button" class="press rounded-full p-2.5 text-ink-soft hover:bg-surface-sunken md:hidden" aria-label="Search" @click="openSearch"><Search :size="21" /></button>
                    <Link
                        v-if="user.is_member && route().has('feed')"
                        :href="route('feed')"
                        class="press mr-1 hidden h-10 items-center gap-1.5 rounded-full bg-accent-500 px-4 text-sm font-semibold text-white shadow-sm shadow-accent-500/30 hover:bg-accent-600 lg:inline-flex"
                    >
                        <Plus :size="17" :stroke-width="2.5" aria-hidden="true" />Create
                    </Link>
                    <button
                        type="button"
                        class="press hidden rounded-full p-2.5 text-ink-soft hover:bg-surface-sunken sm:block"
                        :aria-label="isDark ? 'Switch to light theme' : 'Switch to dark theme'"
                        @click="themePref = isDark ? 'light' : 'dark'"
                    >
                        <Sun v-if="isDark" :size="20" />
                        <Moon v-else :size="20" />
                    </button>
                    <Link
                        v-if="route().has('messages.index')"
                        :href="route('messages.index')"
                        class="press relative rounded-full p-2.5 text-ink-soft hover:bg-surface-sunken"
                        :aria-label="`Messages${counts.messages ? ` (${counts.messages} unread)` : ''}`"
                    >
                        <MessageCircle :size="21" />
                        <span v-if="counts.messages" class="absolute top-1 right-1 grid h-[18px] min-w-[18px] place-items-center rounded-full bg-brand-600 px-1 text-[10px] font-bold text-white ring-2 ring-topbar">{{
                            fmt(counts.messages)
                        }}</span>
                    </Link>
                    <Link
                        :href="route('notifications.index')"
                        class="press relative rounded-full p-2.5 text-ink-soft hover:bg-surface-sunken"
                        :aria-label="`Notifications${counts.notifications ? ` (${counts.notifications} unread)` : ''}`"
                    >
                        <Bell :size="21" />
                        <span v-if="counts.notifications" class="absolute top-1 right-1 grid h-[18px] min-w-[18px] place-items-center rounded-full bg-accent-500 px-1 text-[10px] font-bold text-white ring-2 ring-topbar">{{
                            fmt(counts.notifications)
                        }}</span>
                    </Link>

                    <DropdownMenu label="Account menu" width="w-64" class="ml-1">
                        <template #trigger="{ toggle, open }">
                            <button type="button" class="press rounded-full ring-2 ring-transparent transition hover:ring-brand-200" :aria-expanded="open" aria-haspopup="menu" aria-label="Account menu" @click.stop="toggle">
                                <AvatarImage :name="user.name" size="sm" />
                            </button>
                        </template>
                        <div class="flex items-center gap-3 px-4 pt-2 pb-3">
                            <AvatarImage :name="user.name" size="md" />
                            <div class="min-w-0">
                                <p class="truncate text-sm font-bold text-ink">{{ user.name }}</p>
                                <p class="truncate text-xs text-muted capitalize">{{ roleLabel }}</p>
                            </div>
                        </div>
                        <div class="border-t border-line-soft py-1.5">
                            <Link v-for="a in accountNav" :key="a.route" :href="route(a.route)" class="dropdown-item"><component :is="a.icon" :size="17" aria-hidden="true" />{{ a.label }}</Link>
                            <Link v-if="user.can_access_admin && !inAdmin" :href="route('admin.dashboard')" class="dropdown-item"><ShieldCheck :size="17" aria-hidden="true" />Administration</Link>
                        </div>
                        <div class="border-t border-line-soft px-4 py-3" @click.stop>
                            <p class="mb-2 text-xs font-semibold text-muted">Appearance</p>
                            <div class="grid grid-cols-3 gap-1 rounded-full bg-surface-sunken p-1" role="radiogroup" aria-label="Theme">
                                <button
                                    v-for="t in themes"
                                    :key="t.value"
                                    type="button"
                                    role="radio"
                                    :aria-checked="themePref === t.value"
                                    :class="['flex items-center justify-center gap-1 rounded-full px-1.5 py-1.5 text-xs font-semibold transition', themePref === t.value ? 'bg-surface text-ink shadow-card' : 'text-muted hover:text-ink']"
                                    @click="themePref = t.value"
                                >
                                    <component :is="t.icon" :size="13" aria-hidden="true" />{{ t.label }}
                                </button>
                            </div>
                        </div>
                        <div class="border-t border-line-soft pt-1.5">
                            <button type="button" class="dropdown-item" @click="logout"><LogOut :size="17" aria-hidden="true" />Sign out</button>
                        </div>
                    </DropdownMenu>
                </div>
            </div>
        </header>

        <div class="mx-auto flex max-w-[90rem] gap-6 px-4 sm:px-6">
            <!-- Rail (desktop) -->
            <aside :class="['sticky top-16 hidden h-[calc(100dvh-4rem)] shrink-0 overflow-y-auto overscroll-contain py-5 scrollbar-none lg:block', iconOnly ? 'w-16' : 'w-60']" aria-label="Main navigation">
                <Link v-if="inAdmin" :href="route('dashboard')" :class="[navLink(false), 'mb-3 text-muted']" :aria-label="iconOnly ? 'Back to Alumni Connect' : undefined">
                    <ArrowLeft :size="20" class="shrink-0" aria-hidden="true" />
                    <span v-if="!iconOnly">Back to Alumni Connect</span>
                </Link>
                <nav>
                    <div v-for="(section, si) in sections" :key="section.title" :class="si > 0 && 'mt-5'">
                        <p v-if="!iconOnly && (sections.length > 1 || inAdmin)" class="px-3.5 pb-1.5 text-[11px] font-bold tracking-[0.1em] text-subtle uppercase">{{ section.title }}</p>
                        <div v-else-if="si > 0" class="mx-3 mb-3 border-t border-line" aria-hidden="true" />
                        <ul class="space-y-0.5" :aria-label="section.title">
                            <li v-for="item in section.items" :key="item.label" @mouseenter="showFlyout(item, $event)" @mouseleave="hideFlyout" @focusin="showFlyout(item, $event)" @focusout="hideFlyout">
                                <template v-if="item.children">
                                    <component
                                        :is="iconOnly ? Link : 'button'"
                                        :href="iconOnly ? route(item.route) : undefined"
                                        :type="iconOnly ? undefined : 'button'"
                                        :aria-expanded="iconOnly ? undefined : isOpen(item)"
                                        :aria-label="iconOnly ? item.label : undefined"
                                        :class="[navLink(iconOnly && groupActive(item)), 'w-full', !iconOnly && groupActive(item) && 'font-bold text-ink']"
                                        @click="!iconOnly && toggleGroup(item)"
                                    >
                                        <component :is="item.icon" :size="21" :stroke-width="groupActive(item) ? 2.4 : 2" class="shrink-0" aria-hidden="true" />
                                        <template v-if="!iconOnly">
                                            <span class="flex-1 truncate text-left">{{ item.label }}</span>
                                            <ChevronRight :size="16" :class="['shrink-0 text-subtle transition-transform', isOpen(item) && 'rotate-90']" aria-hidden="true" />
                                        </template>
                                    </component>
                                    <ul v-if="!iconOnly && isOpen(item)" class="mt-0.5 mb-1.5 ml-[1.6rem] space-y-0.5 border-l-2 border-line pl-3">
                                        <li v-for="child in item.children" :key="child.route">
                                            <Link
                                                :href="route(child.route)"
                                                :aria-current="isActive(child) ? 'page' : undefined"
                                                :class="['relative block rounded-xl px-3 py-1.5 text-sm transition-colors', isActive(child) ? 'font-bold text-brand-600 dark:text-brand-300' : 'text-muted hover:text-ink']"
                                            >
                                                <span v-if="isActive(child)" class="absolute top-1/2 -left-[0.95rem] h-5 w-0.5 -translate-y-1/2 rounded-full bg-brand-600" aria-hidden="true" />
                                                {{ child.label }}
                                            </Link>
                                        </li>
                                    </ul>
                                </template>
                                <Link v-else :href="route(item.route)" :aria-current="isActive(item) ? 'page' : undefined" :aria-label="iconOnly ? item.label : undefined" :class="navLink(isActive(item))">
                                    <component :is="item.icon" :size="21" :stroke-width="isActive(item) ? 2.4 : 2" :class="['shrink-0', isActive(item) && 'text-brand-600 dark:text-brand-300']" aria-hidden="true" />
                                    <span v-if="!iconOnly" class="flex-1 truncate">{{ item.label }}</span>
                                    <span
                                        v-if="badge(item)"
                                        :class="['rounded-full bg-accent-500 text-[10px] font-bold text-white tabular-nums', iconOnly ? 'absolute top-1.5 right-1.5 grid size-4 place-items-center' : 'px-1.5 py-px']"
                                        >{{ fmt(badge(item)) }}</span
                                    >
                                </Link>
                            </li>
                        </ul>
                    </div>
                </nav>
            </aside>

            <!-- Page -->
            <main id="main" :key="page.url" class="min-w-0 flex-1 animate-fade-up pt-6 pb-28 lg:pb-12">
                <slot />
            </main>
        </div>

        <!-- Icon-only rail: tooltip / submenu flyout -->
        <div
            v-if="flyout && iconOnly"
            class="fixed z-50 min-w-48 rounded-2xl bg-surface py-2 shadow-pop ring-1 ring-line"
            :style="{ top: `${flyout.top}px`, left: '5.75rem' }"
            @mouseenter="keepFlyout"
            @mouseleave="hideFlyout"
        >
            <p class="px-4 py-1 text-sm font-bold text-ink">{{ flyout.item.label }}</p>
            <template v-if="flyout.item.children">
                <Link
                    v-for="child in flyout.item.children"
                    :key="child.route"
                    :href="route(child.route)"
                    :class="['block px-4 py-1.5 text-sm', isActive(child) ? 'font-bold text-brand-600' : 'text-muted hover:bg-surface-muted hover:text-ink']"
                    @focusin="keepFlyout"
                    @focusout="hideFlyout"
                    >{{ child.label }}</Link
                >
            </template>
        </div>

        <!-- Bottom tab bar (phones and tablets) -->
        <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-line/70 bg-topbar/90 pb-[env(safe-area-inset-bottom)] backdrop-blur-xl lg:hidden" aria-label="Primary">
            <ul class="mx-auto flex max-w-md items-center justify-around px-2 py-1.5">
                <li v-for="t in tabs" :key="t.label">
                    <Link
                        v-if="t.create"
                        :href="route(t.route)"
                        class="press -mt-5 grid size-12 place-items-center rounded-2xl bg-gradient-to-br from-brand-500 to-accent-500 text-white shadow-lg shadow-brand-600/30"
                        aria-label="Create a post"
                    >
                        <Plus :size="24" :stroke-width="2.5" />
                    </Link>
                    <Link
                        v-else
                        :href="route(t.route)"
                        :aria-current="route().current(t.match) ? 'page' : undefined"
                        :class="['press flex w-16 flex-col items-center gap-0.5 rounded-xl py-1 text-[11px] font-semibold', route().current(t.match) ? 'text-brand-600 dark:text-brand-300' : 'text-muted']"
                    >
                        <component :is="t.icon" :size="23" :stroke-width="route().current(t.match) ? 2.4 : 1.9" aria-hidden="true" />
                        {{ t.label }}
                    </Link>
                </li>
                <li>
                    <button type="button" :class="['press flex w-16 flex-col items-center gap-0.5 rounded-xl py-1 text-[11px] font-semibold', menuOpen ? 'text-brand-600' : 'text-muted']" :aria-expanded="menuOpen" @click="menuOpen = true">
                        <LayoutGrid :size="23" aria-hidden="true" />
                        Menu
                    </button>
                </li>
            </ul>
        </nav>

        <!-- Phones: full navigation sheet -->
        <Transition enter-from-class="opacity-0" enter-active-class="transition duration-200" leave-to-class="opacity-0" leave-active-class="transition duration-150">
            <div v-if="menuOpen" class="fixed inset-0 z-40 bg-slate-950/40 backdrop-blur-sm lg:hidden" aria-hidden="true" @click="menuOpen = false" />
        </Transition>
        <Transition enter-from-class="translate-y-full" enter-active-class="transition duration-300 ease-out" leave-to-class="translate-y-full" leave-active-class="transition duration-200 ease-in">
            <div v-if="menuOpen" class="fixed inset-x-0 bottom-0 z-50 max-h-[85dvh] overflow-y-auto rounded-t-[1.75rem] bg-surface px-4 pt-2 pb-[calc(1.5rem+env(safe-area-inset-bottom))] shadow-pop lg:hidden" role="dialog" aria-modal="true" aria-label="Menu">
                <div class="sticky top-0 -mx-4 flex items-center justify-between bg-surface px-4 pt-2 pb-3">
                    <span class="mx-auto h-1 w-10 rounded-full bg-line-strong" aria-hidden="true" />
                    <button type="button" class="absolute top-2 right-3 rounded-full p-2 text-muted hover:bg-surface-sunken" aria-label="Close menu" @click="menuOpen = false"><X :size="20" /></button>
                </div>
                <Link v-if="inAdmin" :href="route('dashboard')" class="mb-3 flex items-center gap-2 rounded-2xl bg-surface-sunken px-4 py-3 text-sm font-semibold text-ink"><ArrowLeft :size="18" />Back to Alumni Connect</Link>
                <div v-for="section in sections" :key="section.title" class="mb-4">
                    <p class="px-1 pb-2 text-[11px] font-bold tracking-[0.1em] text-subtle uppercase">{{ section.title }}</p>
                    <div class="grid grid-cols-2 gap-2">
                        <template v-for="item in section.items" :key="item.label">
                            <Link
                                v-for="leaf in item.children ?? [item]"
                                :key="leaf.route"
                                :href="route(leaf.route)"
                                :class="['press flex items-center gap-2.5 rounded-2xl px-3 py-3 text-sm font-semibold', isActive(leaf) ? 'bg-brand-50 text-brand-700' : 'bg-surface-muted text-ink-soft']"
                                @click="menuOpen = false"
                            >
                                <component :is="item.icon" :size="18" class="shrink-0" aria-hidden="true" />
                                <span class="truncate">{{ leaf.label }}</span>
                                <span v-if="badge(leaf)" class="ml-auto rounded-full bg-accent-500 px-1.5 text-[10px] font-bold text-white">{{ fmt(badge(leaf)) }}</span>
                            </Link>
                        </template>
                    </div>
                </div>
                <Link v-if="user.can_access_admin && !inAdmin" :href="route('admin.dashboard')" class="flex items-center gap-2 rounded-2xl bg-surface-sunken px-4 py-3 text-sm font-semibold text-ink"><ShieldCheck :size="18" />Administration</Link>
            </div>
        </Transition>

        <!-- Phones: search sheet -->
        <div v-if="searchOpen" class="fixed inset-0 z-50 bg-canvas/95 px-4 pt-4 backdrop-blur-xl md:hidden" role="dialog" aria-modal="true" aria-label="Search" @keydown.esc="searchOpen = false">
            <form class="flex items-center gap-2" role="search" @submit.prevent="search">
                <div class="relative flex-1">
                    <Search :size="18" class="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-subtle" aria-hidden="true" />
                    <label for="mobile-search" class="sr-only">Search alumni</label>
                    <input
                        id="mobile-search"
                        ref="mobileSearch"
                        v-model="q"
                        type="search"
                        placeholder="Search people, batches, companies…"
                        class="h-12 w-full rounded-full border-0 !bg-surface pr-4 pl-11 text-base text-ink shadow-card placeholder:text-subtle focus:ring-2 focus:ring-brand-500"
                    />
                </div>
                <button type="button" class="px-2 text-sm font-semibold text-brand-600" @click="searchOpen = false">Cancel</button>
            </form>
        </div>
    </div>
</template>
