<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{ status: string; label?: string }>();

const tone = computed(
    () =>
        ({
            verified: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
            active: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
            pending: 'bg-amber-50 text-amber-800 ring-amber-600/20',
            locked: 'bg-red-50 text-red-700 ring-red-600/20',
            rejected: 'bg-red-50 text-red-700 ring-red-600/20',
            suspended: 'bg-red-50 text-red-700 ring-red-600/20',
            deactivated: 'bg-slate-100 text-slate-600 ring-slate-500/20',
            archived: 'bg-slate-100 text-slate-600 ring-slate-500/20',
        })[props.status] ?? 'bg-brand-50 text-brand-700 ring-brand-600/20',
);
</script>

<template>
    <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize ring-1 ring-inset', tone]">
        {{ label ?? status.replace(/_/g, ' ') }}
    </span>
</template>
