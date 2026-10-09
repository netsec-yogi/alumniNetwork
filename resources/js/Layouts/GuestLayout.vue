<script setup lang="ts">
import AppLogo from '@/Components/AppLogo.vue';
import FlashMessages from '@/Components/FlashMessages.vue';
import { Head, Link } from '@inertiajs/vue3';
import { CalendarDays, GraduationCap, Handshake, ShieldCheck } from 'lucide-vue-next';

defineProps<{ title: string; description?: string; wide?: boolean }>();

const points = [
    { icon: Handshake, text: 'Reconnect with classmates across every batch and programme' },
    { icon: GraduationCap, text: 'Mentor students or find a mentor for your next step' },
    { icon: CalendarDays, text: 'Reunions, chapter meets and webinars in one place' },
    { icon: ShieldCheck, text: 'Verified alumni only, with privacy you control field by field' },
];
</script>

<template>
    <Head :title="title" />
    <FlashMessages />
    <div class="grid min-h-dvh lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]">
        <!-- Brand panel -->
        <aside class="relative hidden overflow-hidden bg-gradient-to-br from-deep-800 via-deep-900 to-deep-950 p-12 text-white lg:flex lg:flex-col">
            <div class="pointer-events-none absolute -top-24 -right-24 size-80 rounded-full bg-brand-500/20 blur-3xl" aria-hidden="true" />
            <div class="pointer-events-none absolute -bottom-32 -left-20 size-96 rounded-full bg-accent-500/10 blur-3xl" aria-hidden="true" />
            <Link :href="route('home')" class="relative w-fit"><AppLogo place="login" inverse /></Link>
            <div class="relative mt-auto max-w-md">
                <h2 class="text-3xl leading-tight font-semibold tracking-tight">A lifelong network for every IIITM graduate.</h2>
                <ul class="mt-8 space-y-4">
                    <li v-for="p in points" :key="p.text" class="flex items-start gap-3 text-sm text-white/85">
                        <span class="grid size-8 shrink-0 place-items-center rounded-md bg-white/10 ring-1 ring-white/15"><component :is="p.icon" :size="16" /></span>
                        <span class="pt-1.5">{{ p.text }}</span>
                    </li>
                </ul>
            </div>
            <p class="relative mt-12 text-xs text-white/60">© {{ new Date().getFullYear() }} ABV-IIITM Gwalior Alumni Association</p>
        </aside>

        <!-- Form -->
        <div class="flex flex-col justify-center px-4 py-10 sm:px-8">
            <div :class="['mx-auto w-full', wide ? 'max-w-xl' : 'max-w-md']">
                <Link :href="route('home')" class="mb-8 block w-fit lg:hidden"><AppLogo place="login" /></Link>
                <h1 class="text-2xl font-semibold tracking-tight text-ink">{{ title }}</h1>
                <p v-if="description" class="mt-1.5 text-sm text-muted">{{ description }}</p>
                <main class="card mt-6 p-6 sm:p-8">
                    <slot />
                </main>
                <div v-if="$slots.footer" class="mt-6 text-center text-sm text-muted"><slot name="footer" /></div>
            </div>
        </div>
    </div>
</template>
