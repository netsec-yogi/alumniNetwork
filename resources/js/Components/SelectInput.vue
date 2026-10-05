<script setup lang="ts" generic="V extends string | number">
import { useField } from './useField';

defineOptions({ inheritAttrs: false });
defineProps<{
    options: { value: V; label: string }[];
    placeholder?: string;
}>();
const model = defineModel<V | '' | null>();
const field = useField();
</script>

<template>
    <select
        v-model="model"
        v-bind="{ ...field.attrs(), ...$attrs }"
        :class="[
            'block w-full rounded-lg border-0 py-2 pr-8 text-slate-900 shadow-sm ring-1 ring-inset focus:ring-2 focus:ring-inset sm:text-sm',
            field.invalid() ? 'ring-red-400 focus:ring-red-500' : 'ring-slate-300 focus:ring-brand-600',
        ]"
    >
        <option v-if="placeholder !== undefined" value="">{{ placeholder }}</option>
        <option v-for="o in options" :key="o.value" :value="o.value">{{ o.label }}</option>
    </select>
</template>
