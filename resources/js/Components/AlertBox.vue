<script setup lang="ts">
withDefaults(defineProps<{ tone?: 'info' | 'success' | 'warning' | 'danger'; title?: string }>(), { tone: 'info' });
</script>

<template>
    <div
        :role="tone === 'danger' ? 'alert' : 'status'"
        :class="[
            'rounded-lg border-l-4 p-4 text-sm',
            {
                info: 'border-brand-500 bg-brand-50 text-brand-900',
                success: 'border-emerald-500 bg-emerald-50 text-emerald-900',
                warning: 'border-amber-500 bg-amber-50 text-amber-900',
                danger: 'border-red-500 bg-red-50 text-red-900',
            }[tone],
        ]"
    >
        <p v-if="title" class="font-semibold">{{ title }}</p>
        <div :class="{ 'mt-1': title }"><slot /></div>
    </div>
</template>
