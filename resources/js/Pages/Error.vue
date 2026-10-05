<script setup lang="ts">
import AppLogo from '@/Components/AppLogo.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{ status: number; requestId?: string | null }>();

const copy = computed(
    () =>
        ({
            403: ['Not allowed', "You don't have permission to view this page."],
            404: ['Page not found', "The page you're looking for doesn't exist or has moved."],
            419: ['Session expired', 'Your session expired. Please refresh the page and try again.'],
            429: ['Too many requests', 'Please slow down and try again in a minute.'],
            503: ['Down for maintenance', "We're doing some maintenance. Please check back shortly."],
        })[props.status] ?? ['Something went wrong', 'Something went wrong. Please try again later.'],
);
</script>

<template>
    <Head :title="copy[0]" />
    <main class="grid min-h-full place-items-center px-4 py-24">
        <div class="text-center">
            <AppLogo />
            <p class="mt-10 text-sm font-semibold text-brand-700">{{ status }}</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">{{ copy[0] }}</h1>
            <p class="mt-4 text-slate-600">{{ copy[1] }}</p>
            <p v-if="requestId" class="mt-2 text-xs text-slate-400">Reference: {{ requestId }}</p>
            <Link :href="route('home')" class="mt-8 inline-block text-sm font-medium text-brand-700 hover:text-brand-900">← Back to home</Link>
        </div>
    </main>
</template>
