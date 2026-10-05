<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        variant?: 'primary' | 'secondary' | 'danger' | 'ghost';
        size?: 'sm' | 'md';
        type?: 'button' | 'submit';
        href?: string;
        loading?: boolean;
        disabled?: boolean;
    }>(),
    { variant: 'primary', size: 'md', type: 'button' },
);

const classes = computed(() => [
    'inline-flex items-center justify-center gap-2 rounded-lg font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-60',
    props.size === 'sm' ? 'px-3 py-1.5 text-sm' : 'px-4 py-2 text-sm',
    {
        primary: 'bg-brand-800 text-white shadow-sm hover:bg-brand-700',
        secondary: 'bg-white text-slate-700 shadow-sm ring-1 ring-slate-300 ring-inset hover:bg-slate-50',
        danger: 'bg-red-600 text-white shadow-sm hover:bg-red-500',
        ghost: 'text-brand-700 hover:bg-brand-50',
    }[props.variant],
]);
</script>

<template>
    <Link v-if="href" :href="href" :class="classes"><slot /></Link>
    <button v-else :type="type" :class="classes" :disabled="disabled || loading" :aria-busy="loading || undefined">
        <svg v-if="loading" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25" />
            <path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="3" />
        </svg>
        <slot />
    </button>
</template>
