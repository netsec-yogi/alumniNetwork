<script setup lang="ts">
defineProps<{ title: string; description?: string }>();
</script>

<template>
    <div class="rounded-xl border-2 border-dashed border-slate-200 px-6 py-12 text-center">
        <p class="font-medium text-slate-900">{{ title }}</p>
        <p v-if="description" class="mt-1 text-sm text-slate-500">{{ description }}</p>
        <div v-if="$slots.default" class="mt-4"><slot /></div>
    </div>
</template>
