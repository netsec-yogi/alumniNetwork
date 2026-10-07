<script setup lang="ts">
import AppButton from '@/Components/AppButton.vue';
import AppLogo from '@/Components/AppLogo.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Briefcase, CalendarDays, HandHeart, Users } from 'lucide-vue-next';

defineProps<{ alumniCount: number }>();
const page = usePage();

const pillars = [
    { title: 'Connect', icon: Users, body: 'Find batchmates and alumni by programme, batch, company and city — with privacy you control.' },
    { title: 'Career', icon: Briefcase, body: 'Mentoring, jobs, internships and referrals from people who sat in the same classrooms.' },
    { title: 'Engage', icon: CalendarDays, body: 'Chapters, reunions, events and stories that keep the IIITM community close.' },
    { title: 'Give back', icon: HandHeart, body: 'Mentor, volunteer or support scholarships and research at your institute.' },
];
</script>

<template>
    <Head title="Welcome" />
    <div class="min-h-full">
        <header class="bg-surface shadow-[0_1px_0_var(--line)]"><div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-5 sm:px-6 lg:px-8">
            <AppLogo />
            <nav class="flex items-center gap-2" aria-label="Account">
                <a :href="route('stories.index')" class="hidden px-2 text-sm font-medium text-muted hover:text-ink md:block">Stories</a>
                <a :href="route('distinguished.index')" class="hidden px-2 text-sm font-medium text-muted hover:text-ink md:block">Distinguished alumni</a>
                <a :href="route('events.index')" class="hidden px-2 text-sm font-medium text-muted hover:text-ink md:block">Events</a>
                <a :href="route('giving.index')" class="hidden px-2 text-sm font-medium text-accent-600 hover:text-accent-500 md:block">Give</a>
                <template v-if="page.props.auth.user">
                    <AppButton :href="route('dashboard')">Go to dashboard</AppButton>
                </template>
                <template v-else>
                    <AppButton variant="ghost" :href="route('login')">Sign in</AppButton>
                    <AppButton :href="route('register')">Register</AppButton>
                </template>
            </nav>
        </div></header>

        <main>
            <section class="bg-gradient-to-b from-deep-900 to-deep-800 text-white">
                <div class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8 lg:py-28">
                    <p class="text-sm font-semibold tracking-wide text-accent-400 uppercase">ABV-Indian Institute of Information Technology and Management, Gwalior</p>
                    <h1 class="mt-4 max-w-3xl text-4xl font-semibold tracking-tight sm:text-5xl">A lifelong network for every IIITM graduate.</h1>
                    <p class="mt-6 max-w-2xl text-lg text-white/85">
                        Reconnect with your batch, open doors for students, and stay part of the institute's story.
                    </p>
                    <div class="mt-10 flex flex-wrap gap-3">
                        <Link :href="route('register')" class="rounded-md bg-accent-500 px-5 py-2.5 text-sm font-semibold text-brand-950 shadow-sm hover:bg-accent-400">
                            Join the network
                        </Link>
                        <Link :href="route('login')" class="rounded-md px-5 py-2.5 text-sm font-semibold text-white ring-1 ring-white/30 hover:bg-white/10">Sign in</Link>
                    </div>
                    <p v-if="alumniCount > 0" class="mt-8 text-sm text-white/70">{{ alumniCount.toLocaleString('en-IN') }} verified alumni and counting.</p>
                </div>
            </section>

            <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
                <h2 class="sr-only">What you can do</h2>
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <div v-for="p in pillars" :key="p.title" class="card card-hover p-6">
                        <span class="grid size-11 place-items-center rounded-full bg-brand-50 text-brand-600"><component :is="p.icon" :size="20" aria-hidden="true" /></span>
                        <h3 class="mt-4 font-semibold text-ink">{{ p.title }}</h3>
                        <p class="mt-2 text-sm text-muted">{{ p.body }}</p>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-line py-8 text-center text-sm text-muted">
            © {{ new Date().getFullYear() }} ABV-IIITM Gwalior · Alumni Relations
        </footer>
    </div>
</template>
