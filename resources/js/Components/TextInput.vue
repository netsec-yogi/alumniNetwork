<script setup lang="ts">
import { useField } from './useField';

defineOptions({ inheritAttrs: false });
const model = defineModel<string | number | null>();
const field = useField();
</script>

<template>
    <input
        v-model="model"
        v-bind="{ ...field.attrs(), ...$attrs }"
        :class="[
            'block w-full rounded-lg border-0 py-2 text-slate-900 shadow-sm ring-1 ring-inset placeholder:text-slate-400 focus:ring-2 focus:ring-inset sm:text-sm',
            field.invalid() ? 'ring-red-400 focus:ring-red-500' : 'ring-slate-300 focus:ring-brand-600',
        ]"
    />
</template>
