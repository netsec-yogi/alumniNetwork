<script setup lang="ts">
import { computed } from 'vue';

/** Profile photo, or initials when there is none. Decorative: the name is always shown beside it. */
const props = withDefaults(defineProps<{ name: string; src?: string | null; size?: 'sm' | 'md' | 'lg' | 'xl' }>(), { size: 'md' });

const initials = computed(() =>
    props.name
        .split(/\s+/)
        .slice(0, 2)
        .map((w) => w[0])
        .join('')
        .toUpperCase(),
);
const box = computed(() => ({ sm: 'size-8 text-xs', md: 'size-11 text-sm', lg: 'size-14 text-base', xl: 'size-24 text-2xl' })[props.size]);
</script>

<template>
    <img v-if="src" :src="src" alt="" :class="[box, 'shrink-0 rounded-full object-cover ring-1 ring-line']" loading="lazy" />
    <span v-else :class="[box, 'grid shrink-0 place-items-center rounded-full bg-brand-100 font-semibold text-brand-800']" aria-hidden="true">{{ initials }}</span>
</template>
