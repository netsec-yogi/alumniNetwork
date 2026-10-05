<script setup lang="ts">
import AppButton from '@/Components/AppButton.vue';
import AppLogo from '@/Components/AppLogo.vue';
import FlashMessages from '@/Components/FlashMessages.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{ title: string }>();
</script>

<template>
    <Head :title="title" />
    <FlashMessages />
    <div class="min-h-full">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4 sm:px-6">
                <Link :href="route('home')"><AppLogo /></Link>
                <nav class="flex items-center gap-2" aria-label="Site">
                    <Link :href="route('events.index')" class="hidden px-3 text-sm font-medium text-slate-600 hover:text-slate-900 sm:block">Events</Link>
                    <AppButton variant="ghost" :href="route('login')">Sign in</AppButton>
                    <AppButton :href="route('register')">Register</AppButton>
                </nav>
            </div>
        </header>
        <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6"><slot /></main>
    </div>
</template>
