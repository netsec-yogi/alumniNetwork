<script setup lang="ts">
import AppButton from '@/Components/AppButton.vue';
import AppLogo from '@/Components/AppLogo.vue';
import ConfirmHost from '@/Components/ConfirmHost.vue';
import FlashMessages from '@/Components/FlashMessages.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{ title: string }>();
</script>

<template>
    <Head :title="title" />
    <FlashMessages />
    <ConfirmHost />
    <div class="min-h-full">
        <header class="sticky top-0 z-20 bg-surface/95 shadow-[0_1px_0_var(--line)] backdrop-blur">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 py-4 sm:px-6">
                <Link :href="route('home')"><AppLogo /></Link>
                <nav class="flex items-center gap-2" aria-label="Site">
                    <Link :href="route('stories.index')" class="hidden px-3 text-sm font-medium text-muted hover:text-ink md:block">Stories</Link>
                    <Link :href="route('distinguished.index')" class="hidden px-3 text-sm font-medium text-muted hover:text-ink md:block">Distinguished alumni</Link>
                    <Link :href="route('achievements.index')" class="hidden px-3 text-sm font-medium text-muted hover:text-ink md:block">Achievements</Link>
                    <Link :href="route('events.index')" class="hidden px-3 text-sm font-medium text-muted hover:text-ink sm:block">Events</Link>
                    <Link :href="route('fundraising.index')" class="hidden px-3 text-sm font-medium text-muted hover:text-ink lg:block">Campaigns</Link>
                    <Link :href="route('giving.index')" class="hidden px-3 text-sm font-medium text-accent-600 hover:text-accent-500 sm:block">Give</Link>
                    <AppButton variant="ghost" :href="route('login')">Sign in</AppButton>
                    <AppButton :href="route('register')">Register</AppButton>
                </nav>
            </div>
        </header>
        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6"><slot /></main>
    </div>
</template>
