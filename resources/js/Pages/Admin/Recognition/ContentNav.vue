<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

const tabs = [
    { label: 'Achievements', route: 'admin.achievements.index', match: 'admin.achievements.*' },
    { label: 'Stories', route: 'admin.stories.*', match: 'admin.stories.*', href: 'admin.stories.index' },
    { label: 'Distinguished alumni', route: 'admin.distinguished.index', match: 'admin.distinguished.*' },
];
</script>

<template>
    <nav class="mb-6 flex flex-wrap gap-2 border-b border-line" aria-label="Content">
        <Link
            v-for="t in tabs"
            :key="t.label"
            :href="route(t.href ?? t.route)"
            :class="['-mb-px border-b-2 px-3 py-2 text-sm font-medium', route().current(t.match) ? 'border-accent-500 text-brand-900' : 'border-transparent text-muted hover:text-ink']"
            >{{ t.label }}</Link
        >
    </nav>
</template>
