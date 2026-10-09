<script setup lang="ts">
import { Bold, Italic, Link2, List, ListOrdered } from 'lucide-vue-next';
import { nextTick, ref } from 'vue';
import AppTooltip from './AppTooltip.vue';
import { useField } from './useField';

/**
 * Safe rich text: a Markdown textarea with a formatting toolbar (bold,
 * italic, link, lists). The server renders and sanitises it; raw HTML is
 * stripped, so nothing typed here can inject markup or script.
 */
defineOptions({ inheritAttrs: false });
const model = defineModel<string | null>();
const field = useField();
const area = ref<HTMLTextAreaElement>();

function wrap(before: string, after = before, placeholder = 'text') {
    const el = area.value!;
    const value = model.value ?? '';
    const [start, end] = [el.selectionStart, el.selectionEnd];
    const selected = value.slice(start, end) || placeholder;
    model.value = value.slice(0, start) + before + selected + after + value.slice(end);
    nextTick(() => {
        el.focus();
        el.setSelectionRange(start + before.length, start + before.length + selected.length);
    });
}

function list(ordered: boolean) {
    const el = area.value!;
    const value = model.value ?? '';
    const start = value.lastIndexOf('\n', el.selectionStart - 1) + 1;
    const end = el.selectionEnd;
    const lines = (value.slice(start, end) || 'Item').split('\n').map((l, i) => `${ordered ? `${i + 1}.` : '-'} ${l.replace(/^(\d+\.|-)\s+/, '')}`);
    const block = (start > 0 && value[start - 1] !== '\n' ? '\n' : '') + lines.join('\n');
    model.value = value.slice(0, start) + block + value.slice(end);
    nextTick(() => el.focus());
}

const tools = [
    { label: 'Bold', icon: Bold, run: () => wrap('**') },
    { label: 'Italic', icon: Italic, run: () => wrap('_') },
    { label: 'Link', icon: Link2, run: () => wrap('[', '](https://)', 'link text') },
    { label: 'Bulleted list', icon: List, run: () => list(false) },
    { label: 'Numbered list', icon: ListOrdered, run: () => list(true) },
];
</script>

<template>
    <div :class="['overflow-hidden rounded-xl bg-surface-muted text-sm ring-1 ring-inset focus-within:bg-surface focus-within:ring-2', field.invalid() ? 'ring-red-400 focus-within:ring-red-500' : 'ring-line focus-within:ring-brand-500']">
        <div class="flex items-center gap-0.5 border-b border-line bg-surface-muted px-1.5 py-1" role="toolbar" aria-label="Formatting">
            <AppTooltip v-for="tool in tools" :key="tool.label" :text="tool.label">
                <button type="button" class="rounded-md p-1.5 text-muted hover:bg-surface-sunken hover:text-ink" :aria-label="tool.label" @click="tool.run">
                    <component :is="tool.icon" :size="15" />
                </button>
            </AppTooltip>
            <span class="ml-auto pr-1.5 text-[11px] text-subtle">Markdown · blank line = new paragraph</span>
        </div>
        <textarea ref="area" v-model="model" v-bind="{ ...field.attrs(), ...$attrs }" rows="4" class="block w-full resize-y bg-transparent px-3 py-2 leading-relaxed text-ink outline-none" />
    </div>
</template>
