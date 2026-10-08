<script setup lang="ts">
import { useId } from 'vue';

/**
 * Tooltip for icon-only controls and truncated text. Shown on hover and on
 * keyboard focus; the text is linked with aria-describedby.
 */
withDefaults(defineProps<{ text: string; side?: 'top' | 'bottom' }>(), { side: 'top' });
const id = useId();
</script>

<template>
    <span class="group/tip relative inline-flex" :aria-describedby="id">
        <slot />
        <span
            :id="id"
            role="tooltip"
            :class="[
                'pointer-events-none absolute left-1/2 z-50 -translate-x-1/2 rounded-lg bg-ink px-2 py-1 text-xs font-medium whitespace-nowrap text-canvas opacity-0 shadow-pop transition-opacity duration-150 group-focus-within/tip:opacity-100 group-hover/tip:opacity-100',
                side === 'top' ? 'bottom-full mb-2' : 'top-full mt-2',
            ]"
            >{{ text }}</span
        >
    </span>
</template>
