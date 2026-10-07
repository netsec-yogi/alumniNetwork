<script setup lang="ts">
import { Inbox } from 'lucide-vue-next';
import type { Component } from 'vue';

/** What's missing, why, and (in the slot) the action that fixes it. */
defineProps<{ title: string; description?: string; icon?: Component; compact?: boolean }>();
</script>

<template>
    <div :class="['flex flex-col items-center text-center', compact ? 'px-4 py-8' : 'card px-6 py-14']">
        <span class="grid size-12 place-items-center rounded-full bg-surface-sunken text-subtle">
            <component :is="icon ?? Inbox" :size="22" aria-hidden="true" />
        </span>
        <p class="mt-3 font-medium text-ink">{{ title }}</p>
        <p v-if="description" class="mt-1 max-w-md text-sm text-muted">{{ description }}</p>
        <div v-if="$slots.default" class="mt-5 flex flex-wrap justify-center gap-2"><slot /></div>
    </div>
</template>
