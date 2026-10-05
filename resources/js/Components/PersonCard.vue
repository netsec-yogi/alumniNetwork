<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{ name: string; subtitle?: string | null; profileId?: number | null }>();

const initials = (name: string) =>
    name
        .split(/\s+/)
        .slice(0, 2)
        .map((w) => w[0])
        .join('')
        .toUpperCase();
</script>

<template>
    <div class="flex items-start gap-3">
        <span class="grid size-11 shrink-0 place-items-center rounded-full bg-brand-100 text-sm font-semibold text-brand-800" aria-hidden="true">{{ initials(name) }}</span>
        <div class="min-w-0 flex-1">
            <Link v-if="profileId" :href="route('alumni.show', profileId)" class="block truncate font-medium text-slate-900 hover:underline">{{ name }}</Link>
            <p v-else class="truncate font-medium text-slate-900">{{ name }}</p>
            <p v-if="subtitle" class="truncate text-sm text-slate-500">{{ subtitle }}</p>
            <slot />
        </div>
        <div v-if="$slots.actions" class="flex shrink-0 flex-wrap justify-end gap-2"><slot name="actions" /></div>
    </div>
</template>
