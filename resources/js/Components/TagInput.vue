<script setup lang="ts">
import { ref } from 'vue';
import { useField } from './useField';

/** Free-text tags (skills, topics). Enter or comma adds; Backspace on empty removes. */
const props = withDefaults(defineProps<{ max?: number; placeholder?: string }>(), { max: 15, placeholder: 'Type and press Enter' });
const model = defineModel<string[]>({ default: () => [] });
const draft = ref('');
const field = useField();

function add() {
    const value = draft.value.trim().replace(/,$/, '');
    if (value && !model.value.some((t) => t.toLowerCase() === value.toLowerCase()) && model.value.length < props.max) {
        model.value = [...model.value, value];
    }
    draft.value = '';
}

function onKey(e: KeyboardEvent) {
    if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        add();
    } else if (e.key === 'Backspace' && draft.value === '' && model.value.length) {
        model.value = model.value.slice(0, -1);
    }
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-1.5 rounded-lg bg-white px-2 py-1.5 shadow-sm ring-1 ring-slate-300 ring-inset focus-within:ring-2 focus-within:ring-brand-600">
        <span v-for="(tag, i) in model" :key="tag" class="inline-flex items-center gap-1 rounded bg-brand-50 px-2 py-0.5 text-sm text-brand-800">
            {{ tag }}
            <button type="button" class="text-brand-500 hover:text-brand-900" :aria-label="`Remove ${tag}`" @click="model = model.filter((_, j) => j !== i)">×</button>
        </span>
        <input
            v-model="draft"
            v-bind="field.attrs()"
            :placeholder="model.length ? '' : placeholder"
            class="min-w-32 flex-1 border-0 p-1 text-sm focus:ring-0"
            @keydown="onKey"
            @blur="add"
        />
    </div>
</template>
