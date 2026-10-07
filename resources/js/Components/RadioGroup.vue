<script setup lang="ts" generic="V extends string | number">
import { useId } from 'vue';

/** Radio buttons, as a list or as selectable cards (`cards`). */
defineProps<{ options: { value: V; label: string; description?: string }[]; cards?: boolean; legend?: string }>();
const model = defineModel<V | null>();
const name = useId();
</script>

<template>
    <fieldset>
        <legend v-if="legend" class="mb-1.5 text-[13px] font-medium text-ink-soft">{{ legend }}</legend>
        <div :class="cards ? 'grid gap-2 sm:grid-cols-2' : 'space-y-2'">
            <label
                v-for="o in options"
                :key="o.value"
                :class="[
                    'flex cursor-pointer items-start gap-2.5 text-sm text-ink-soft',
                    cards && ['rounded-md p-3 ring-1 ring-inset transition-colors', model === o.value ? 'bg-brand-50/60 ring-brand-500 dark:bg-brand-500/10' : 'ring-line-strong hover:bg-surface-muted'],
                ]"
            >
                <input v-model="model" type="radio" :name="name" :value="o.value" class="mt-0.5 size-4 focus:ring-brand-500" />
                <span>
                    <span :class="cards && 'font-medium text-ink'">{{ o.label }}</span>
                    <span v-if="o.description" class="block text-[13px] text-muted">{{ o.description }}</span>
                </span>
            </label>
        </div>
    </fieldset>
</template>
