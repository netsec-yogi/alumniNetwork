<script setup lang="ts">
import AppButton from '@/Components/AppButton.vue';
import AppLogo from '@/Components/AppLogo.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

defineProps<{ alumniCount: number }>();
const page = usePage();

const pillars = [
    { title: 'Connect', body: 'Find batchmates and alumni by programme, batch, company and city — with privacy you control.' },
    { title: 'Career', body: 'Mentoring, jobs, internships and referrals from people who sat in the same classrooms.' },
    { title: 'Engage', body: 'Chapters, reunions, events and stories that keep the IIITM community close.' },
    { title: 'Give back', body: 'Mentor, volunteer or support scholarships and research at your institute.' },
];
</script>

<template>
    <Head title="Welcome" />
    <div class="min-h-full bg-white">
        <header class="mx-auto flex max-w-7xl items-center justify-between px-4 py-5 sm:px-6 lg:px-8">
            <AppLogo />
            <nav class="flex items-center gap-2" aria-label="Account">
                <template v-if="page.props.auth.user">
                    <AppButton :href="route('dashboard')">Go to dashboard</AppButton>
                </template>
                <template v-else>
                    <AppButton variant="ghost" :href="route('login')">Sign in</AppButton>
                    <AppButton :href="route('register')">Register</AppButton>
                </template>
            </nav>
        </header>

        <main>
            <section class="bg-gradient-to-b from-brand-900 to-brand-800 text-white">
                <div class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8 lg:py-28">
                    <p class="text-sm font-semibold tracking-wide text-accent-400 uppercase">ABV-Indian Institute of Information Technology and Management, Gwalior</p>
                    <h1 class="mt-4 max-w-3xl text-4xl font-semibold tracking-tight sm:text-5xl">A lifelong network for every IIITM graduate.</h1>
                    <p class="mt-6 max-w-2xl text-lg text-brand-100">
                        Reconnect with your batch, open doors for students, and stay part of the institute's story.
                    </p>
                    <div class="mt-10 flex flex-wrap gap-3">
                        <Link :href="route('register')" class="rounded-lg bg-accent-500 px-5 py-2.5 text-sm font-semibold text-brand-950 shadow-sm hover:bg-accent-400">
                            Join the network
                        </Link>
                        <Link :href="route('login')" class="rounded-lg px-5 py-2.5 text-sm font-semibold text-white ring-1 ring-white/30 hover:bg-white/10">Sign in</Link>
                    </div>
                    <p v-if="alumniCount > 0" class="mt-8 text-sm text-brand-200">{{ alumniCount.toLocaleString('en-IN') }} verified alumni and counting.</p>
                </div>
            </section>

            <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
                <h2 class="sr-only">What you can do</h2>
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <div v-for="p in pillars" :key="p.title" class="rounded-xl p-6 ring-1 ring-slate-200">
                        <h3 class="font-semibold text-brand-800">{{ p.title }}</h3>
                        <p class="mt-2 text-sm text-slate-600">{{ p.body }}</p>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-slate-200 py-8 text-center text-sm text-slate-500">
            © {{ new Date().getFullYear() }} ABV-IIITM Gwalior · Alumni Relations
        </footer>
    </div>
</template>
