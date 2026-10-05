<script setup lang="ts">
defineProps<{ label?: string; value?: string }>();
const model = defineModel<boolean | string[]>();
</script>

<template>
    <label class="flex items-start gap-2.5 text-sm text-slate-700">
        <input v-model="model" :value="value" type="checkbox" class="mt-0.5 size-4 rounded border-slate-300 focus:ring-brand-600" />
        <span><slot>{{ label }}</slot></span>
    </label>
</template>
