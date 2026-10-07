<script setup lang="ts">
import type { Component } from 'vue';
import { controlBase, controlTone } from './inputClasses';
import { useField } from './useField';

/** Text input. `icon` adds a leading icon; `prefix`/`suffix` make an input group (e.g. ₹, .com). */
defineOptions({ inheritAttrs: false });
defineProps<{ icon?: Component; prefix?: string; suffix?: string }>();
const model = defineModel<string | number | null>();
const field = useField();
</script>

<template>
    <div v-if="icon || prefix || suffix" class="relative flex">
        <span v-if="prefix" class="inline-flex items-center rounded-l-md bg-surface-muted px-3 text-sm text-muted ring-1 ring-line-strong ring-inset">{{ prefix }}</span>
        <component :is="icon" v-if="icon" :size="16" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-subtle" aria-hidden="true" />
        <input
            v-model="model"
            v-bind="{ ...field.attrs(), ...$attrs }"
            :class="[controlBase, controlTone(field.invalid()), 'h-9.5 py-2', icon && 'pl-9', prefix && '-ml-px rounded-l-none', suffix && '-mr-px rounded-r-none']"
        />
        <span v-if="suffix" class="inline-flex items-center rounded-r-md bg-surface-muted px-3 text-sm text-muted ring-1 ring-line-strong ring-inset">{{ suffix }}</span>
    </div>
    <input v-else v-model="model" v-bind="{ ...field.attrs(), ...$attrs }" :class="[controlBase, controlTone(field.invalid()), 'h-9.5 py-2']" />
</template>
