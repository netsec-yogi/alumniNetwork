<script setup lang="ts">
import { computed } from 'vue';

/**
 * Labelled horizontal bars for one measure across categories. Single hue,
 * value printed beside every bar (so identity never relies on colour), and
 * the bar list doubles as the table view.
 */
const props = defineProps<{ items: { label: string; value: number; hint?: string }[]; suffix?: string; max?: number }>();
const top = computed(() => props.max ?? Math.max(1, ...props.items.map((i) => i.value)));
</script>

<template>
    <ul class="space-y-3">
        <li v-for="item in items" :key="item.label" :title="`${item.label}: ${item.value}${suffix ?? ''}`">
            <div class="flex justify-between gap-3 text-sm">
                <span class="truncate text-ink-soft">{{ item.label }}</span>
                <span class="shrink-0 font-medium text-ink tabular-nums">{{ item.value.toLocaleString('en-IN') }}{{ suffix }}<span v-if="item.hint" class="ml-1 font-normal text-muted">{{ item.hint }}</span></span>
            </div>
            <div class="mt-1 h-2 rounded-full bg-surface-sunken" aria-hidden="true">
                <div class="h-2 rounded-full bg-brand-600" :style="{ width: `${Math.max(item.value > 0 ? 2 : 0, (item.value / top) * 100)}%` }" />
            </div>
        </li>
    </ul>
</template>
