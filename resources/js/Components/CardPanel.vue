<script setup lang="ts">
import type { Component } from 'vue';

/**
 * Panel with optional header (icon, title, description, actions) and
 * footer. `flush` drops body padding for edge-to-edge tables and lists.
 */
defineProps<{ title?: string; description?: string; icon?: Component; flush?: boolean }>();
</script>

<template>
    <section class="card">
        <header v-if="title || $slots.actions" class="flex flex-wrap items-center justify-between gap-3 border-b border-line-soft px-5 py-3.5">
            <div class="flex min-w-0 items-center gap-3">
                <span v-if="icon" class="grid size-8 shrink-0 place-items-center rounded-md bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                    <component :is="icon" :size="16" aria-hidden="true" />
                </span>
                <div class="min-w-0">
                    <h2 v-if="title" class="text-[15px] font-semibold text-ink">{{ title }}</h2>
                    <p v-if="description" class="mt-0.5 text-[13px] text-muted">{{ description }}</p>
                </div>
            </div>
            <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2"><slot name="actions" /></div>
        </header>
        <div :class="flush ? '' : 'p-5'"><slot /></div>
        <footer v-if="$slots.footer" class="flex flex-wrap items-center justify-end gap-2 border-t border-line-soft bg-surface-muted/60 px-5 py-3 rounded-b-[var(--radius-card)]">
            <slot name="footer" />
        </footer>
    </section>
</template>
