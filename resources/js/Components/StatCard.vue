<script setup lang="ts">
defineProps<{ label: string; value: number | string; tone?: 'default' | 'warning' | 'danger'; hint?: string }>();
</script>

<template>
    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <p class="text-sm text-slate-500">{{ label }}</p>
        <p :class="['mt-1 text-3xl font-semibold tabular-nums', tone === 'danger' ? 'text-red-600' : tone === 'warning' ? 'text-amber-600' : 'text-slate-900']">
            {{ typeof value === 'number' ? value.toLocaleString('en-IN') : value }}
        </p>
        <p v-if="hint" class="mt-1 text-xs text-slate-500">{{ hint }}</p>
    </div>
</template>
