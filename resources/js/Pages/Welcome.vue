<script setup lang="ts">
import AppButton from '@/Components/AppButton.vue';
import AppLogo from '@/Components/AppLogo.vue';
import AvatarImage from '@/Components/AvatarImage.vue';
import CountUp from '@/Components/CountUp.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FlashMessages from '@/Components/FlashMessages.vue';
import LandingHeading from '@/Components/LandingHeading.vue';
import LightboxViewer from '@/Components/LightboxViewer.vue';
import { vReveal } from '@/lib/reveal';
import { isDark, themePref } from '@/lib/ui';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    Award,
    BookOpen,
    Briefcase,
    Building2,
    CalendarDays,
    Clock,
    Earth,
    Factory,
    FlaskConical,
    Globe2,
    GraduationCap,
    HandHeart,
    Handshake,
    Images,
    Lightbulb,
    MapPin,
    Megaphone,
    Menu,
    MessagesSquare,
    Moon,
    Newspaper,
    Quote,
    Rocket,
    School,
    Search,
    Sparkles,
    Star,
    Sun,
    Trophy,
    UserCheck,
    Users,
    UsersRound,
    X,
} from 'lucide-vue-next';
import { computed, ref, type Component } from 'vue';

interface EventCard {
    slug: string;
    title: string;
    category: string;
    summary: string;
    date: string;
    time: string;
    day: string;
    month: string;
    where: string | null;
    featured: boolean;
    cover: string | null;
    registration: 'open' | 'waitlist' | 'opens_soon' | 'closed';
    closes: string | null;
}
interface StoryCard {
    slug: string;
    title: string;
    type: string;
    excerpt: string;
    date: string;
    featured: boolean;
    cover: string | null;
    author: string | null;
    alumnus: { name: string; batch: number; programme: string | null; organization: string | null; photo: string | null } | null;
}

const props = defineProps<{
    sections: string[];
    stats: { key: string; label: string; value: number }[];
    events: EventCard[];
    news: StoryCard[];
    distinguished: { name: string; programme: string | null; batch: number; category: string; year: number; citation: string; designation: string | null; organization: string | null; photo: string | null }[];
    stories: StoryCard[];
    gallery: { items: { title: string; caption: string | null; category: string; thumb: string; full: string }[]; categories: Record<string, string> };
    chapters: { slug: string; name: string; city: string | null; country: string | null; members: number; upcoming_events: number; coordinator: string | null }[];
    network: { countries: { country: string; count: number }[]; other: number };
    heroPhotos: string[];
    /** Published text (Admin → Landing page text); rich fields arrive as sanitised HTML. */
    copy: Record<string, string>;
    preview?: boolean;
}>();

/** Text for a key below "landing.". */
const t = (key: string) => props.copy[`landing.${key}`] ?? '';

const page = usePage();
const signedIn = computed(() => !!page.props.auth.user);
const menuOpen = ref(false);

const nav = computed(() =>
    [
        { label: 'Events', href: '#events', show: props.sections.includes('events') },
        { label: 'Stories', href: '#stories', show: props.sections.includes('stories') || props.sections.includes('news') },
        { label: 'Alumni', href: '#distinguished', show: props.sections.includes('distinguished') },
        { label: 'Gallery', href: '#gallery', show: props.sections.includes('gallery') },
        { label: 'Chapters', href: '#chapters', show: props.sections.includes('chapters') },
    ].filter((n) => n.show),
);

const statIcons: Record<string, Component> = {
    alumni: Users,
    active: UserCheck,
    countries: Earth,
    cities: MapPin,
    companies: Building2,
    chapters: UsersRound,
    startups: Rocket,
    distinguished: Trophy,
};
const heroStats = computed(() => props.stats.slice(0, 3));

// Icons are fixed; the text is editable.
const features = computed(() =>
    [Handshake, Search, GraduationCap, Briefcase, MessagesSquare, CalendarDays, UsersRound, Lightbulb, Rocket].map((icon, i) => ({
        icon,
        title: t(`community.feature_${i + 1}_title`),
        body: t(`community.feature_${i + 1}_text`),
    })),
);
const supportAreas = computed(() => [GraduationCap, Users, FlaskConical, School, Handshake, Rocket, Factory].map((icon, i) => ({ icon, label: t(`support.area_${i + 1}`) })));

const registrationLabel: Record<EventCard['registration'], { text: string; tone: string }> = {
    open: { text: 'Registration open', tone: 'bg-emerald-50 text-emerald-700' },
    waitlist: { text: 'Waitlist open', tone: 'bg-amber-50 text-amber-800' },
    opens_soon: { text: 'Opens soon', tone: 'bg-brand-50 text-brand-700' },
    closed: { text: 'Registration closed', tone: 'bg-surface-sunken text-muted' },
};

// Gallery: category filter + lightbox.
const galleryFilter = ref<string | null>(null);
const galleryItems = computed(() => props.gallery.items.filter((g) => !galleryFilter.value || g.category === galleryFilter.value));
const lightbox = ref<number | null>(null);

const maxCountry = computed(() => Math.max(1, ...props.network.countries.map((c) => c.count)));
const featuredNews = computed(() => props.news[0] ?? null);
const moreNews = computed(() => props.news.slice(1));
</script>

<template>
    <Head :title="t('seo.title')" />
    <FlashMessages />
    <div v-if="preview" class="fixed inset-x-0 bottom-4 z-[60] flex justify-center px-4" role="status">
        <p class="flex items-center gap-3 rounded-full bg-amber-400 py-2 pr-2 pl-4 text-sm font-bold text-deep-950 shadow-pop">
            Preview of the draft — visitors don’t see this yet
            <a :href="route('admin.landing.content')" class="rounded-full bg-deep-950/10 px-3 py-1 hover:bg-deep-950/20">Back to editor</a>
        </p>
    </div>
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[70] focus:rounded-full focus:bg-surface focus:px-4 focus:py-2 focus:shadow-pop">Skip to content</a>

    <!-- Header -->
    <header class="fixed inset-x-0 top-0 z-40 border-b border-white/10 bg-deep-950/70 text-white backdrop-blur-xl">
        <div class="mx-auto flex h-16 max-w-7xl items-center gap-4 px-4 sm:px-6">
            <Link :href="route('home')" :aria-label="`${$page.props.branding.name} home`"><AppLogo inverse /></Link>
            <nav class="ml-6 hidden items-center gap-1 lg:flex" aria-label="Sections">
                <a v-for="n in nav" :key="n.href" :href="n.href" class="rounded-full px-3 py-1.5 text-sm font-medium text-white/80 transition hover:bg-white/10 hover:text-white">{{ n.label }}</a>
                <Link :href="route('giving.index')" class="rounded-full px-3 py-1.5 text-sm font-semibold text-accent-400 hover:bg-white/10">Give</Link>
            </nav>
            <div class="ml-auto flex items-center gap-2">
                <button type="button" class="press rounded-full p-2 text-white/80 hover:bg-white/10 hover:text-white" :aria-label="isDark ? 'Switch to light theme' : 'Switch to dark theme'" @click="themePref = isDark ? 'light' : 'dark'">
                    <Sun v-if="isDark" :size="19" />
                    <Moon v-else :size="19" />
                </button>
                <template v-if="signedIn">
                    <Link :href="route('dashboard')" class="press hidden h-10 items-center rounded-full bg-white px-5 text-sm font-bold text-brand-700 sm:inline-flex">Open the app</Link>
                </template>
                <template v-else>
                    <Link :href="route('login')" class="hidden rounded-full px-4 py-2 text-sm font-semibold text-white/90 hover:bg-white/10 sm:block">Sign in</Link>
                    <Link :href="route('register')" class="press hidden h-10 items-center rounded-full bg-white px-5 text-sm font-bold text-brand-700 sm:inline-flex">Join</Link>
                </template>
                <button type="button" class="press rounded-full p-2 hover:bg-white/10 lg:hidden" :aria-expanded="menuOpen" aria-controls="landing-menu" aria-label="Menu" @click="menuOpen = !menuOpen">
                    <X v-if="menuOpen" :size="22" />
                    <Menu v-else :size="22" />
                </button>
            </div>
        </div>
        <Transition enter-from-class="opacity-0 -translate-y-2" enter-active-class="transition duration-200" leave-to-class="opacity-0" leave-active-class="transition duration-150">
            <nav v-if="menuOpen" id="landing-menu" class="border-t border-white/10 px-4 pt-2 pb-5 lg:hidden" aria-label="Menu">
                <a v-for="n in nav" :key="n.href" :href="n.href" class="block rounded-xl px-3 py-3 text-base font-semibold hover:bg-white/10" @click="menuOpen = false">{{ n.label }}</a>
                <Link :href="route('giving.index')" class="block rounded-xl px-3 py-3 text-base font-semibold text-accent-400 hover:bg-white/10">Give back</Link>
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <template v-if="signedIn"><Link :href="route('dashboard')" class="col-span-2 rounded-full bg-white py-3 text-center font-bold text-brand-700">Open the app</Link></template>
                    <template v-else>
                        <Link :href="route('login')" class="rounded-full bg-white/10 py-3 text-center font-semibold">Sign in</Link>
                        <Link :href="route('register')" class="rounded-full bg-white py-3 text-center font-bold text-brand-700">Join</Link>
                    </template>
                </div>
            </nav>
        </Transition>
    </header>

    <main id="main">
        <!-- Hero -->
        <section class="relative isolate overflow-hidden bg-deep-950 pt-28 pb-20 text-white sm:pt-36 sm:pb-28" aria-labelledby="hero-title">
            <div class="absolute inset-0 -z-10 bg-gradient-to-br from-deep-950 via-deep-900 to-deep-800" aria-hidden="true" />
            <div class="absolute -top-40 -right-32 -z-10 size-[36rem] rounded-full bg-brand-500/40 blur-[120px]" aria-hidden="true" />
            <div class="absolute -bottom-48 -left-24 -z-10 size-[30rem] rounded-full bg-accent-500/30 blur-[120px]" aria-hidden="true" />
            <div class="absolute inset-0 -z-10 bg-[radial-gradient(rgb(255_255_255/0.08)_1px,transparent_1px)] [background-size:22px_22px] [mask-image:linear-gradient(to_bottom,black,transparent)]" aria-hidden="true" />

            <div class="mx-auto grid max-w-7xl items-center gap-12 px-4 sm:px-6 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
                <div class="animate-fade-up">
                    <p v-if="t('hero.badge')" class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3.5 py-1.5 text-xs font-semibold tracking-wide text-white/85 ring-1 ring-white/15 backdrop-blur">
                        <Sparkles :size="14" class="text-accent-400" aria-hidden="true" />{{ t('hero.badge') }}
                    </p>
                    <h1 id="hero-title" class="mt-6 text-4xl leading-[1.05] font-extrabold tracking-tight sm:text-6xl">
                        {{ t('hero.title') }}<template v-if="t('hero.subtitle')"
                            ><br /><span class="bg-gradient-to-r from-brand-300 via-white to-accent-400 bg-clip-text text-transparent">{{ t('hero.subtitle') }}</span></template
                        >
                    </h1>
                    <!-- Server-sanitised rich text (LandingCopy::richHtml). -->
                    <div class="rich-copy mt-6 max-w-xl text-lg leading-relaxed text-white/75" v-html="t('hero.description')" />
                    <div class="mt-9 flex flex-wrap gap-3">
                        <Link
                            :href="signedIn ? route('dashboard') : route('register')"
                            class="press inline-flex h-12 items-center gap-2 rounded-full bg-gradient-to-r from-brand-500 to-accent-500 px-7 font-bold shadow-lg shadow-accent-500/30 transition hover:brightness-110"
                        >
                            {{ signedIn ? t('hero.primary_button_member') : t('hero.primary_button') }}<ArrowRight :size="18" aria-hidden="true" />
                        </Link>
                        <Link :href="route('directory')" class="press inline-flex h-12 items-center gap-2 rounded-full bg-white/10 px-7 font-semibold ring-1 ring-white/20 backdrop-blur hover:bg-white/15">
                            <Search :size="18" aria-hidden="true" />{{ t('hero.secondary_button') }}
                        </Link>
                    </div>
                    <dl v-if="heroStats.length" class="mt-12 flex flex-wrap gap-x-10 gap-y-4">
                        <div v-for="s in heroStats" :key="s.key">
                            <dd class="text-3xl font-extrabold"><CountUp :value="s.value" />+</dd>
                            <dt class="text-sm text-white/60">{{ s.label }}</dt>
                        </div>
                    </dl>
                </div>

                <!-- Collage: featured public gallery photos, or an abstract community graphic -->
                <div class="relative hidden h-[30rem] lg:block" aria-hidden="true">
                    <template v-if="heroPhotos.length">
                        <img
                            v-for="(src, i) in heroPhotos.slice(0, 4)"
                            :key="src"
                            :src="src"
                            alt=""
                            :class="[
                                'absolute rounded-[1.75rem] object-cover shadow-2xl ring-1 ring-white/20 transition duration-700 hover:z-10 hover:scale-[1.03]',
                                ['top-0 left-6 h-64 w-56 -rotate-3', 'top-10 right-0 h-56 w-64 rotate-3', 'bottom-0 left-0 h-52 w-60 rotate-2', 'right-8 bottom-6 h-60 w-52 -rotate-2'][i],
                            ]"
                        />
                    </template>
                    <template v-else>
                        <div class="absolute inset-8 rounded-[2.5rem] bg-gradient-to-br from-white/10 to-white/0 ring-1 ring-white/15 backdrop-blur" />
                        <div v-for="i in 9" :key="i" :class="['absolute grid size-16 place-items-center rounded-full bg-gradient-to-br text-white/90 shadow-xl ring-4 ring-deep-950/40', ['from-brand-400 to-accent-400', 'from-sky-400 to-brand-500', 'from-emerald-400 to-sky-500', 'from-amber-300 to-accent-500', 'from-fuchsia-400 to-brand-500'][i % 5], ['top-6 left-16', 'top-20 right-12', 'top-44 left-1/3', 'bottom-24 left-10', 'right-24 bottom-16', 'top-1/2 right-1/3', 'bottom-4 left-1/2', 'top-4 right-1/3', 'top-1/3 left-4'][i - 1]]">
                            <component :is="[Users, GraduationCap, Briefcase, Globe2, Rocket, Award, Handshake, CalendarDays, Lightbulb][i - 1]" :size="24" />
                        </div>
                    </template>
                </div>
            </div>
        </section>

        <template v-for="key in sections" :key="key">
            <!-- Statistics -->
            <section v-if="key === 'stats' && stats.length" class="relative -mt-10 px-4 sm:px-6" :aria-label="t('stats.title')">
                <div class="mx-auto grid max-w-7xl grid-cols-2 gap-3 sm:gap-4 md:grid-cols-4">
                    <div v-for="(s, i) in stats" :key="s.key" v-reveal="i * 60" class="card card-hover p-5 sm:p-6">
                        <span class="grid size-11 place-items-center rounded-2xl bg-gradient-to-br from-brand-50 to-accent-50 text-brand-600 dark:text-brand-300"><component :is="statIcons[s.key] ?? Users" :size="20" aria-hidden="true" /></span>
                        <p class="mt-4 text-3xl font-extrabold tracking-tight text-ink sm:text-4xl"><CountUp :value="s.value" /></p>
                        <p class="mt-1 text-sm font-medium text-muted">{{ s.label }}</p>
                    </div>
                </div>
            </section>

            <!-- Community -->
            <section v-else-if="key === 'community'" class="mx-auto max-w-7xl px-4 py-20 sm:px-6 sm:py-24" aria-labelledby="community-title">
                <LandingHeading id="community-title" :eyebrow="t('community.eyebrow')" :title="t('community.title')" :subtitle="t('community.subtitle')" />
                <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="(f, i) in features" :key="i" v-reveal="(i % 3) * 70" class="card card-hover group p-6">
                        <span class="grid size-12 place-items-center rounded-2xl bg-surface-sunken text-brand-600 transition group-hover:scale-110 group-hover:bg-gradient-to-br group-hover:from-brand-500 group-hover:to-accent-500 group-hover:text-white dark:text-brand-300"
                            ><component :is="f.icon" :size="22" aria-hidden="true"
                        /></span>
                        <h3 class="mt-4 text-lg font-bold text-ink">{{ f.title }}</h3>
                        <p class="mt-1 text-[15px] leading-relaxed text-muted">{{ f.body }}</p>
                    </div>
                </div>
            </section>

            <!-- Events -->
            <section v-else-if="key === 'events'" id="events" class="scroll-mt-20 bg-surface py-20 sm:py-24" aria-labelledby="events-title">
                <div class="mx-auto max-w-7xl px-4 sm:px-6">
                    <LandingHeading id="events-title" :eyebrow="t('events.eyebrow')" :title="t('events.title')" :subtitle="t('events.subtitle')">
                        <AppButton variant="secondary" :href="route('events.index')" :icon="CalendarDays">{{ t('events.button') }}</AppButton>
                    </LandingHeading>
                    <EmptyState v-if="events.length === 0" class="mt-10" :icon="CalendarDays" :title="t('events.empty_title')" :description="t('events.empty_text')" />
                    <div v-else class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        <article v-for="(e, i) in events" :key="e.slug" v-reveal="(i % 3) * 70" class="card card-hover group flex flex-col overflow-hidden">
                            <div class="relative h-44 overflow-hidden bg-gradient-to-br from-deep-800 via-brand-500 to-accent-400">
                                <img v-if="e.cover" :src="e.cover" :alt="`${e.title} cover`" loading="lazy" class="size-full object-cover transition duration-500 group-hover:scale-105" />
                                <CalendarDays v-else :size="64" class="absolute right-5 bottom-4 text-white/25" aria-hidden="true" />
                                <span class="absolute top-3 left-3 grid w-14 place-items-center rounded-2xl bg-white/95 py-1.5 text-ink shadow-lg">
                                    <span class="text-[11px] font-bold tracking-wide text-accent-600 uppercase">{{ e.month }}</span>
                                    <span class="text-2xl leading-none font-extrabold">{{ e.day }}</span>
                                </span>
                                <span v-if="e.featured" class="absolute top-3 right-3 inline-flex items-center gap-1 rounded-full bg-accent-500 px-2.5 py-1 text-xs font-bold text-white shadow"><Star :size="12" fill="currentColor" />Featured</span>
                            </div>
                            <div class="flex flex-1 flex-col p-5">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-bold text-brand-700">{{ e.category }}</span>
                                    <span :class="['rounded-full px-2.5 py-0.5 text-xs font-bold', registrationLabel[e.registration].tone]">{{ registrationLabel[e.registration].text }}</span>
                                </div>
                                <h3 class="mt-3 text-lg leading-snug font-bold text-ink">{{ e.title }}</h3>
                                <p class="mt-2 flex items-center gap-1.5 text-sm text-muted"><Clock :size="14" aria-hidden="true" />{{ e.date }} · {{ e.time }}</p>
                                <p v-if="e.where" class="mt-1 flex items-center gap-1.5 text-sm text-muted"><MapPin :size="14" aria-hidden="true" />{{ e.where }}</p>
                                <p v-if="e.summary" class="mt-3 line-clamp-3 text-sm text-ink-soft">{{ e.summary }}</p>
                                <p v-if="e.closes && e.registration === 'open'" class="mt-3 text-xs font-semibold text-accent-600">Register by {{ e.closes }}</p>
                                <div class="mt-auto flex gap-2 pt-5">
                                    <AppButton variant="secondary" size="sm" class="flex-1" :href="route('events.show', e.slug)">View event</AppButton>
                                    <AppButton v-if="e.registration === 'open' || e.registration === 'waitlist'" size="sm" class="flex-1" :href="route('events.show', e.slug)">{{
                                        e.registration === 'open' ? 'Register' : 'Join waitlist'
                                    }}</AppButton>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>
            </section>

            <!-- News & announcements -->
            <section v-else-if="key === 'news'" id="news" class="mx-auto max-w-7xl scroll-mt-20 px-4 py-20 sm:px-6 sm:py-24" aria-labelledby="news-title">
                <LandingHeading id="news-title" :eyebrow="t('news.eyebrow')" :title="t('news.title')" :subtitle="t('news.subtitle')">
                        <AppButton variant="secondary" :href="route('stories.index')" :icon="Newspaper">{{ t('news.button') }}</AppButton>
                    </LandingHeading>
                <EmptyState v-if="!featuredNews" class="mt-10" :icon="Megaphone" :title="t('news.empty_title')" :description="t('news.empty_text')" />
                <div v-else class="mt-10 grid gap-5 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)]">
                    <Link v-reveal :href="route('stories.show', featuredNews.slug)" class="card card-hover group overflow-hidden">
                        <div class="relative h-64 overflow-hidden bg-gradient-to-br from-deep-900 via-brand-600 to-accent-500">
                            <img v-if="featuredNews.cover" :src="featuredNews.cover" :alt="featuredNews.title" loading="lazy" class="size-full object-cover transition duration-500 group-hover:scale-105" />
                            <Newspaper v-else :size="80" class="absolute right-6 bottom-6 text-white/20" aria-hidden="true" />
                        </div>
                        <div class="p-6">
                            <p class="flex flex-wrap items-center gap-2 text-xs font-bold">
                                <span class="rounded-full bg-accent-50 px-2.5 py-0.5 text-accent-600">{{ featuredNews.type }}</span>
                                <span v-if="featuredNews.featured" class="rounded-full bg-brand-50 px-2.5 py-0.5 text-brand-700">Featured</span>
                                <span class="font-medium text-muted">{{ featuredNews.date }}<template v-if="featuredNews.author"> · {{ featuredNews.author }}</template></span>
                            </p>
                            <h3 class="mt-3 text-2xl leading-tight font-extrabold text-ink group-hover:underline">{{ featuredNews.title }}</h3>
                            <p class="mt-2 line-clamp-3 text-ink-soft">{{ featuredNews.excerpt }}</p>
                            <span class="mt-4 inline-flex items-center gap-1 text-sm font-bold text-brand-600 dark:text-brand-300">Read more<ArrowRight :size="15" aria-hidden="true" /></span>
                        </div>
                    </Link>
                    <div class="space-y-4">
                        <Link v-for="(n, i) in moreNews" :key="n.slug" v-reveal="i * 80" :href="route('stories.show', n.slug)" class="card card-hover group flex gap-4 p-4">
                            <div class="relative size-24 shrink-0 overflow-hidden rounded-2xl bg-gradient-to-br from-brand-500 to-accent-400">
                                <img v-if="n.cover" :src="n.cover" :alt="n.title" loading="lazy" class="size-full object-cover" />
                                <Megaphone v-else :size="28" class="absolute inset-0 m-auto text-white/60" aria-hidden="true" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-bold text-accent-600">{{ n.type }} <span class="font-medium text-muted">· {{ n.date }}</span></p>
                                <h3 class="mt-1 line-clamp-2 font-bold text-ink group-hover:underline">{{ n.title }}</h3>
                                <p class="mt-1 line-clamp-2 text-sm text-muted">{{ n.excerpt }}</p>
                            </div>
                        </Link>
                    </div>
                </div>
            </section>

            <!-- Distinguished alumni -->
            <section v-else-if="key === 'distinguished'" id="distinguished" class="scroll-mt-20 bg-surface py-20 sm:py-24" aria-labelledby="dist-title">
                <div class="mx-auto max-w-7xl px-4 sm:px-6">
                    <LandingHeading id="dist-title" :eyebrow="t('distinguished.eyebrow')" :title="t('distinguished.title')" :subtitle="t('distinguished.subtitle')">
                        <AppButton variant="secondary" :href="route('distinguished.index')" :icon="Trophy">{{ t('distinguished.button') }}</AppButton>
                    </LandingHeading>
                    <EmptyState v-if="distinguished.length === 0" class="mt-10" :icon="Trophy" :title="t('distinguished.empty_title')" :description="t('distinguished.empty_text')" />
                    <div v-else class="mt-10 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        <article v-for="(d, i) in distinguished" :key="`${d.name}-${d.year}`" v-reveal="(i % 4) * 60" class="card card-hover group relative overflow-hidden p-6 text-center">
                            <span class="absolute inset-x-0 top-0 h-20 bg-gradient-to-br from-amber-100 via-accent-50 to-brand-50 dark:from-amber-500/15 dark:via-accent-500/10 dark:to-brand-500/15" aria-hidden="true" />
                            <div class="relative mx-auto w-fit">
                                <img v-if="d.photo" :src="d.photo" :alt="d.name" loading="lazy" class="size-24 rounded-full object-cover ring-4 ring-surface" />
                                <AvatarImage v-else :name="d.name" size="xl" class="!size-24 !text-2xl ring-4 ring-surface" />
                                <span class="absolute -right-1 -bottom-1 grid size-8 place-items-center rounded-full bg-gradient-to-br from-amber-400 to-accent-500 text-white ring-4 ring-surface"><Star :size="14" fill="currentColor" aria-hidden="true" /></span>
                            </div>
                            <h3 class="mt-4 text-lg font-bold text-ink">{{ d.name }}</h3>
                            <p class="text-sm text-muted">{{ [d.programme, d.batch].filter(Boolean).join(' · ') }}</p>
                            <p v-if="d.designation || d.organization" class="mt-1 text-sm font-semibold text-ink-soft">{{ [d.designation, d.organization].filter(Boolean).join(', ') }}</p>
                            <p class="mt-3 inline-flex rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-800">{{ d.category }} · {{ d.year }}</p>
                            <p class="mt-3 line-clamp-3 text-sm text-muted">{{ d.citation }}</p>
                        </article>
                    </div>
                </div>
            </section>

            <!-- Alumni stories -->
            <section v-else-if="key === 'stories'" id="stories" class="mx-auto max-w-7xl scroll-mt-20 px-4 py-20 sm:px-6 sm:py-24" aria-labelledby="stories-title">
                <LandingHeading id="stories-title" :eyebrow="t('stories.eyebrow')" :title="t('stories.title')" :subtitle="t('stories.subtitle')">
                        <AppButton variant="secondary" :href="route('stories.index')" :icon="BookOpen">{{ t('stories.button') }}</AppButton>
                    </LandingHeading>
                <EmptyState v-if="stories.length === 0" class="mt-10" :icon="BookOpen" :title="t('stories.empty_title')" :description="t('stories.empty_text')" />
                <div v-else class="mt-10 grid gap-5 md:grid-cols-3">
                    <Link v-for="(s, i) in stories" :key="s.slug" v-reveal="i * 80" :href="route('stories.show', s.slug)" class="card card-hover group flex flex-col overflow-hidden">
                        <div class="relative h-48 overflow-hidden bg-gradient-to-br from-brand-600 via-brand-500 to-accent-400">
                            <img v-if="s.cover" :src="s.cover" :alt="s.title" loading="lazy" class="size-full object-cover transition duration-500 group-hover:scale-105" />
                            <Quote v-else :size="64" class="absolute top-5 left-5 text-white/25" aria-hidden="true" />
                            <span class="absolute bottom-3 left-3 rounded-full bg-black/40 px-2.5 py-1 text-xs font-bold text-white backdrop-blur">{{ s.type }}</span>
                        </div>
                        <div class="flex flex-1 flex-col p-5">
                            <h3 class="text-lg leading-snug font-bold text-ink group-hover:underline">{{ s.title }}</h3>
                            <p class="mt-2 line-clamp-3 text-sm text-ink-soft">{{ s.excerpt }}</p>
                            <div v-if="s.alumnus" class="mt-auto flex items-center gap-3 pt-5">
                                <img v-if="s.alumnus.photo" :src="s.alumnus.photo" :alt="s.alumnus.name" class="size-10 rounded-full object-cover" />
                                <AvatarImage v-else :name="s.alumnus.name" size="sm" class="!size-10" />
                                <span class="min-w-0 text-left">
                                    <span class="block truncate text-sm font-bold text-ink">{{ s.alumnus.name }}</span>
                                    <span class="block truncate text-xs text-muted">{{ [s.alumnus.programme, s.alumnus.batch].filter(Boolean).join(' · ') }}<template v-if="s.alumnus.organization"> · {{ s.alumnus.organization }}</template></span>
                                </span>
                            </div>
                            <span v-else class="mt-auto pt-5 text-sm font-bold text-brand-600 dark:text-brand-300">Read story →</span>
                        </div>
                    </Link>
                </div>
            </section>

            <!-- Gallery -->
            <section v-else-if="key === 'gallery'" id="gallery" class="scroll-mt-20 bg-surface py-20 sm:py-24" aria-labelledby="gallery-title">
                <div class="mx-auto max-w-7xl px-4 sm:px-6">
                    <LandingHeading id="gallery-title" :eyebrow="t('gallery.eyebrow')" :title="t('gallery.title')" :subtitle="t('gallery.subtitle')" />
                    <EmptyState v-if="gallery.items.length === 0" class="mt-10" :icon="Images" :title="t('gallery.empty_title')" :description="t('gallery.empty_text')" />
                    <template v-else>
                        <div v-if="Object.keys(gallery.categories).length > 1" class="-mx-4 mt-8 flex gap-2 overflow-x-auto px-4 scrollbar-none sm:mx-0 sm:px-0" role="group" aria-label="Filter photos">
                            <button
                                type="button"
                                :aria-pressed="galleryFilter === null"
                                :class="['press shrink-0 rounded-full px-4 py-2 text-sm font-semibold', galleryFilter === null ? 'bg-brand-600 text-white' : 'bg-surface-sunken text-ink-soft hover:bg-line']"
                                @click="galleryFilter = null"
                            >
                                All
                            </button>
                            <button
                                v-for="(label, k) in gallery.categories"
                                :key="k"
                                type="button"
                                :aria-pressed="galleryFilter === k"
                                :class="['press shrink-0 rounded-full px-4 py-2 text-sm font-semibold', galleryFilter === k ? 'bg-brand-600 text-white' : 'bg-surface-sunken text-ink-soft hover:bg-line']"
                                @click="galleryFilter = String(k)"
                            >
                                {{ label }}
                            </button>
                        </div>
                        <ul class="mt-8 columns-2 gap-3 sm:gap-4 md:columns-3 lg:columns-4">
                            <li v-for="(g, i) in galleryItems" :key="g.full" class="mb-3 break-inside-avoid sm:mb-4">
                                <button type="button" class="group relative block w-full overflow-hidden rounded-2xl bg-surface-sunken" :aria-label="`Open photo: ${g.title}`" @click="lightbox = i">
                                    <img :src="g.thumb" :alt="g.title" loading="lazy" class="w-full object-cover transition duration-500 group-hover:scale-105" />
                                    <span class="absolute inset-0 flex items-end bg-gradient-to-t from-black/70 via-black/0 p-3 text-left opacity-0 transition group-hover:opacity-100 group-focus-visible:opacity-100">
                                        <span class="text-sm font-semibold text-white">{{ g.title }}</span>
                                    </span>
                                </button>
                            </li>
                        </ul>
                        <LightboxViewer v-model="lightbox" :items="galleryItems" />
                    </template>
                </div>
            </section>

            <!-- Chapters -->
            <section v-else-if="key === 'chapters'" id="chapters" class="mx-auto max-w-7xl scroll-mt-20 px-4 py-20 sm:px-6 sm:py-24" aria-labelledby="chapters-title">
                <LandingHeading id="chapters-title" :eyebrow="t('chapters.eyebrow')" :title="t('chapters.title')" :subtitle="t('chapters.subtitle')">
                        <AppButton variant="secondary" :href="route('communities.index')" :icon="UsersRound">{{ t('chapters.button') }}</AppButton>
                    </LandingHeading>
                <EmptyState v-if="chapters.length === 0" class="mt-10" :icon="UsersRound" :title="t('chapters.empty_title')" :description="t('chapters.empty_text')" />
                <div v-else class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <article v-for="(c, i) in chapters" :key="c.slug" v-reveal="(i % 4) * 60" class="card card-hover relative overflow-hidden p-5">
                        <MapPin :size="88" class="absolute -top-3 -right-3 text-brand-100 dark:text-brand-500/15" aria-hidden="true" />
                        <div class="relative">
                            <p class="flex items-center gap-1.5 text-xs font-bold tracking-wide text-brand-600 uppercase dark:text-brand-300"><MapPin :size="13" aria-hidden="true" />{{ [c.city, c.country].filter(Boolean).join(', ') || 'Chapter' }}</p>
                            <h3 class="mt-2 text-lg font-bold text-ink">{{ c.name }}</h3>
                            <dl class="mt-4 flex gap-5 text-sm">
                                <div><dd class="text-xl font-extrabold text-ink tabular-nums">{{ c.members }}</dd><dt class="text-muted">members</dt></div>
                                <div><dd class="text-xl font-extrabold text-ink tabular-nums">{{ c.upcoming_events }}</dd><dt class="text-muted">upcoming events</dt></div>
                            </dl>
                            <p v-if="c.coordinator" class="mt-3 text-xs text-muted">Coordinator: <span class="font-semibold text-ink-soft">{{ c.coordinator }}</span></p>
                        </div>
                    </article>
                </div>
            </section>

            <!-- Global network -->
            <section v-else-if="key === 'network' && network.countries.length" class="relative isolate overflow-hidden bg-deep-950 py-20 text-white sm:py-24" aria-labelledby="network-title">
                <div class="absolute top-0 left-1/2 -z-10 size-[40rem] -translate-x-1/2 rounded-full bg-brand-500/25 blur-[120px]" aria-hidden="true" />
                <div class="mx-auto grid max-w-7xl items-center gap-12 px-4 sm:px-6 lg:grid-cols-2">
                    <div v-reveal>
                        <p v-if="t('network.eyebrow')" class="text-sm font-bold tracking-wide text-accent-400 uppercase">{{ t('network.eyebrow') }}</p>
                        <h2 id="network-title" class="mt-2 text-3xl font-extrabold tracking-tight sm:text-4xl">{{ t('network.title') }}</h2>
                        <div v-if="t('network.description')" class="rich-copy mt-3 max-w-md text-lg text-white/70" v-html="t('network.description')" />
                        <Globe2 :size="180" class="mt-6 hidden text-white/10 lg:block" aria-hidden="true" />
                    </div>
                    <ul v-reveal class="space-y-3" aria-label="Verified alumni by country">
                        <li v-for="c in network.countries" :key="c.country">
                            <div class="flex items-baseline justify-between text-sm">
                                <span class="font-semibold">{{ c.country }}</span>
                                <span class="font-bold text-white/80 tabular-nums">{{ c.count.toLocaleString('en-IN') }}</span>
                            </div>
                            <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-white/10" aria-hidden="true">
                                <div class="h-full rounded-full bg-gradient-to-r from-brand-400 to-accent-400" :style="{ width: `${Math.max(3, (c.count / maxCountry) * 100)}%` }" />
                            </div>
                        </li>
                        <li v-if="network.other" class="pt-1 text-sm text-white/60">+ {{ network.other.toLocaleString('en-IN') }} alumni in other countries</li>
                    </ul>
                </div>
            </section>

            <!-- Join -->
            <section v-else-if="key === 'join'" class="px-4 py-20 sm:px-6 sm:py-24" aria-labelledby="join-title">
                <div v-reveal class="relative mx-auto max-w-5xl overflow-hidden rounded-[2rem] bg-gradient-to-br from-brand-600 via-brand-500 to-accent-500 px-6 py-14 text-center text-white sm:px-12 sm:py-16">
                    <span class="absolute -top-20 -left-10 size-64 rounded-full bg-white/15 blur-3xl" aria-hidden="true" />
                    <span class="absolute -right-10 -bottom-24 size-72 rounded-full bg-deep-900/40 blur-3xl" aria-hidden="true" />
                    <h2 id="join-title" class="relative text-3xl font-extrabold tracking-tight sm:text-4xl">{{ t('join.title') }}</h2>
                    <div v-if="t('join.description')" class="rich-copy relative mx-auto mt-3 max-w-xl text-lg text-white/80" v-html="t('join.description')" />
                    <div class="relative mt-8 flex flex-wrap justify-center gap-3">
                        <template v-if="signedIn">
                            <Link :href="route('dashboard')" class="press inline-flex h-12 items-center rounded-full bg-white px-7 font-bold text-brand-700">Open your network</Link>
                            <Link :href="route('profile.edit')" class="press inline-flex h-12 items-center rounded-full bg-white/15 px-7 font-semibold ring-1 ring-white/30 hover:bg-white/25">Update your profile</Link>
                        </template>
                        <template v-else>
                            <Link :href="route('register')" class="press inline-flex h-12 items-center rounded-full bg-white px-7 font-bold text-brand-700">{{ t('join.primary_button') }}</Link>
                            <Link :href="route('register')" class="press inline-flex h-12 items-center rounded-full bg-white/15 px-7 font-semibold ring-1 ring-white/30 hover:bg-white/25">{{ t('join.secondary_button') }}</Link>
                            <Link :href="route('login')" class="press inline-flex h-12 items-center rounded-full bg-white/15 px-7 font-semibold ring-1 ring-white/30 hover:bg-white/25">{{ t('join.signin_button') }}</Link>
                        </template>
                        <Link :href="route('directory')" class="press inline-flex h-12 items-center rounded-full px-5 font-semibold underline-offset-4 hover:underline">{{ t('join.explore_link') }} →</Link>
                    </div>
                </div>
            </section>

            <!-- Support -->
            <section v-else-if="key === 'support'" id="support" class="scroll-mt-20 bg-surface py-20 sm:py-24" aria-labelledby="support-title">
                <div class="mx-auto grid max-w-7xl items-center gap-12 px-4 sm:px-6 lg:grid-cols-2">
                    <div v-reveal>
                        <p v-if="t('support.eyebrow')" class="text-sm font-bold tracking-wide text-accent-600 uppercase">{{ t('support.eyebrow') }}</p>
                        <h2 id="support-title" class="mt-2 text-3xl font-extrabold tracking-tight text-ink sm:text-4xl">{{ t('support.title') }}</h2>
                        <div v-if="t('support.description')" class="rich-copy mt-3 max-w-lg text-lg text-muted" v-html="t('support.description')" />
                        <div class="mt-8 flex flex-wrap gap-3">
                            <AppButton size="lg" variant="accent" :href="route('giving.index')" :icon="HandHeart">{{ t('support.primary_button') }}</AppButton>
                            <AppButton size="lg" variant="secondary" :href="route('fundraising.index')">{{ t('support.secondary_button') }}</AppButton>
                        </div>
                    </div>
                    <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        <li v-for="(a, i) in supportAreas" :key="i" v-reveal="(i % 3) * 60" class="card card-hover flex flex-col items-start gap-3 p-4">
                            <span class="grid size-10 place-items-center rounded-xl bg-accent-50 text-accent-600"><component :is="a.icon" :size="19" aria-hidden="true" /></span>
                            <span class="text-sm font-bold text-ink">{{ a.label }}</span>
                        </li>
                    </ul>
                </div>
            </section>
        </template>
    </main>

    <footer class="border-t border-line bg-canvas">
        <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 py-10 sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <AppLogo place="footer" />
            <nav class="flex flex-wrap gap-x-5 gap-y-2 text-sm font-medium text-muted" aria-label="Footer">
                <Link :href="route('events.index')" class="hover:text-ink">Events</Link>
                <Link :href="route('stories.index')" class="hover:text-ink">Stories</Link>
                <Link :href="route('distinguished.index')" class="hover:text-ink">Distinguished alumni</Link>
                <Link :href="route('achievements.index')" class="hover:text-ink">Achievements</Link>
                <Link :href="route('giving.index')" class="hover:text-ink">Give</Link>
            </nav>
            <div class="text-xs text-subtle sm:text-right">
                <p>{{ t('footer.copyright') }}</p>
                <p v-if="t('footer.text')" class="mt-1 whitespace-pre-line">{{ t('footer.text') }}</p>
            </div>
        </div>
    </footer>
</template>
