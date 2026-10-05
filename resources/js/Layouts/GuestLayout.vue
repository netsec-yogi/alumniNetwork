<script setup lang="ts">
import AppLogo from '@/Components/AppLogo.vue';
import FlashMessages from '@/Components/FlashMessages.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{ title: string; description?: string }>();
</script>

<template>
    <Head :title="title" />
    <FlashMessages />
    <div class="flex min-h-full flex-col justify-center px-4 py-12 sm:px-6">
        <div class="mx-auto w-full max-w-md">
            <Link :href="route('home')" class="mx-auto block w-fit"><AppLogo /></Link>
            <h1 class="mt-8 text-center text-2xl font-semibold tracking-tight text-slate-900">{{ title }}</h1>
            <p v-if="description" class="mt-2 text-center text-sm text-slate-600">{{ description }}</p>
        </div>
        <main class="mx-auto mt-8 w-full max-w-md">
            <div class="rounded-xl bg-white px-6 py-8 shadow-sm ring-1 ring-slate-200 sm:px-8">
                <slot />
            </div>
            <div v-if="$slots.footer" class="mt-6 text-center text-sm text-slate-600"><slot name="footer" /></div>
        </main>
    </div>
</template>
