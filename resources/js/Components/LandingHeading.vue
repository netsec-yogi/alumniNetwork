<script setup lang="ts">
import { vReveal } from '@/lib/reveal';

/** A landing-page section heading: eyebrow, title, optional subtitle and an action slot on the right. */
defineProps<{ id: string; eyebrow?: string; title: string; subtitle?: string; inverse?: boolean }>();
</script>

<template>
    <div v-reveal class="flex flex-wrap items-end justify-between gap-4">
        <div class="max-w-2xl">
            <p v-if="eyebrow" :class="['text-sm font-bold tracking-wide uppercase', inverse ? 'text-accent-400' : 'text-brand-600 dark:text-brand-300']">{{ eyebrow }}</p>
            <h2 :id="id" :class="['mt-2 text-3xl font-extrabold tracking-tight sm:text-4xl', !inverse && 'text-ink']">{{ title }}</h2>
            <p v-if="subtitle" :class="['mt-3 text-lg whitespace-pre-line', inverse ? 'text-white/70' : 'text-muted']">{{ subtitle }}</p>
        </div>
        <slot />
    </div>
</template>
