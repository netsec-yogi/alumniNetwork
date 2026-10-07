<script setup lang="ts">
/** Placeholder shimmer while content loads. */
withDefaults(defineProps<{ lines?: number; class?: string }>(), { lines: 1 });
</script>

<template>
    <div class="animate-pulse space-y-2" aria-hidden="true">
        <div v-for="i in lines" :key="i" :class="['h-3.5 rounded bg-surface-sunken', i === lines && lines > 1 ? 'w-2/3' : 'w-full', $props.class]" />
    </div>
</template>
