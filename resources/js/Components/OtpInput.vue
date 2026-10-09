<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue';

/**
 * One box per digit: numeric keyboard on phones, auto-advance, backspace
 * steps back, arrow keys move, and pasting a whole code fills every box.
 * The first box carries autocomplete="one-time-code" so iOS/Android can
 * offer the code from the email or SMS bar.
 */
const props = withDefaults(defineProps<{ length?: number; invalid?: boolean; disabled?: boolean; label?: string }>(), { length: 6, label: 'One-time password' });
const model = defineModel<string>({ default: '' });
const emit = defineEmits<{ complete: [code: string] }>();

const boxes = ref<HTMLInputElement[]>([]);
const digits = computed(() => Array.from({ length: props.length }, (_, i) => model.value[i] ?? ''));

function focus(i: number) {
    boxes.value[Math.max(0, Math.min(i, props.length - 1))]?.focus();
}

// Digits stay contiguous (no gaps), like a text field split into boxes.
function setFrom(i: number, text: string) {
    const clean = text.replace(/\D/g, '');
    if (!clean) return;
    const at = Math.min(i, model.value.length);
    // The model reads back the parent's prop, which updates on the next render; use the local value.
    const next = (model.value.slice(0, at) + clean + model.value.slice(at + clean.length)).slice(0, props.length);
    model.value = next;
    nextTick(() => {
        focus(Math.min(at + clean.length, props.length - 1));
        if (next.length === props.length) emit('complete', next);
    });
}

function onInput(i: number, e: Event) {
    const el = e.target as HTMLInputElement;
    const value = el.value;
    el.value = digits.value[i] ?? '';
    // A full code typed or autofilled into one box replaces everything.
    if (value.replace(/\D/g, '').length >= props.length) return setFrom(0, value.replace(/\D/g, '').slice(-props.length));
    setFrom(i, value.replace(digits.value[i] ?? '', '').slice(-1) || value.slice(-1));
}

function onKeydown(i: number, e: KeyboardEvent) {
    if (e.key === 'Backspace') {
        e.preventDefault();
        const at = model.value[i] !== undefined ? i : i - 1;
        if (at < 0) return;
        model.value = model.value.slice(0, at) + model.value.slice(at + 1);
        focus(at);
    } else if (e.key === 'ArrowLeft') {
        e.preventDefault();
        focus(i - 1);
    } else if (e.key === 'ArrowRight') {
        e.preventDefault();
        focus(Math.min(i + 1, model.value.length));
    }
}

function onPaste(i: number, e: ClipboardEvent) {
    e.preventDefault();
    setFrom(i, e.clipboardData?.getData('text') ?? '');
}

// Boxes past the typed digits forward focus to the first empty one.
function onFocus(i: number, e: FocusEvent) {
    if (i > model.value.length) return focus(model.value.length);
    (e.target as HTMLInputElement).select();
}

onMounted(() => focus(model.value.length));
defineExpose({ focus: () => focus(model.value.length) });
</script>

<template>
    <div class="flex justify-center gap-2 sm:gap-3" role="group" :aria-label="label">
        <input
            v-for="(d, i) in digits"
            :key="i"
            :ref="(el) => (boxes[i] = el as HTMLInputElement)"
            :value="d"
            type="text"
            inputmode="numeric"
            pattern="[0-9]*"
            :autocomplete="i === 0 ? 'one-time-code' : 'off'"
            :maxlength="i === 0 ? length : 1"
            :disabled="disabled"
            :aria-label="`Digit ${i + 1} of ${length}`"
            :aria-invalid="invalid || undefined"
            :class="[
                'size-11 rounded-xl bg-surface text-center text-xl font-bold text-ink tabular-nums ring-1 transition outline-none focus:ring-2 sm:size-13',
                invalid ? 'ring-red-500 focus:ring-red-500' : 'ring-line-strong focus:ring-brand-500',
                disabled && 'opacity-60',
            ]"
            @input="onInput(i, $event)"
            @keydown="onKeydown(i, $event)"
            @paste="onPaste(i, $event)"
            @focus="onFocus(i, $event)"
        />
    </div>
</template>
