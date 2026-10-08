<script setup lang="ts">
import { computed } from 'vue';

/** Profile photo, or initials when there is none. Decorative: the name is always shown beside it. */
const props = withDefaults(defineProps<{ name: string; src?: string | null; size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl' }>(), { size: 'md' });

const initials = computed(() =>
    props.name
        .split(/\s+/)
        .slice(0, 2)
        .map((w) => w[0])
        .join('')
        .toUpperCase(),
);
// A stable gradient per name, so initials avatars feel personal rather than uniform.
const gradients = [
    'from-brand-500 to-accent-400',
    'from-sky-500 to-brand-500',
    'from-emerald-500 to-sky-500',
    'from-amber-400 to-accent-500',
    'from-fuchsia-500 to-brand-600',
    'from-teal-500 to-emerald-400',
];
const gradient = computed(() => gradients[[...props.name].reduce((h, c) => (h * 31 + c.charCodeAt(0)) >>> 0, 7) % gradients.length]);
const box = computed(() => ({ xs: 'size-6 text-[10px]', sm: 'size-8 text-xs', md: 'size-11 text-sm', lg: 'size-14 text-base', xl: 'size-28 text-3xl' })[props.size]);
</script>

<template>
    <img v-if="src" :src="src" alt="" :class="[box, 'shrink-0 rounded-full object-cover ring-1 ring-line']" loading="lazy" />
    <span v-else :class="[box, gradient, 'grid shrink-0 place-items-center rounded-full bg-gradient-to-br font-bold text-white']" aria-hidden="true">{{ initials }}</span>
</template>
