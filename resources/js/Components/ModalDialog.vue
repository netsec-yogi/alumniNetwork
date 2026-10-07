<script setup lang="ts">
import { X } from 'lucide-vue-next';
import { nextTick, ref, watch } from 'vue';

/**
 * Modal on the native <dialog>: focus trap, Escape and inert background
 * for free. Header with close button, scrollable body, footer for actions.
 */
const props = withDefaults(defineProps<{ show: boolean; title: string; description?: string; size?: 'sm' | 'md' | 'lg' | 'xl' }>(), { size: 'md' });
const emit = defineEmits<{ close: [] }>();
const dialog = ref<HTMLDialogElement>();

watch(
    () => props.show,
    async (open) => {
        await nextTick();
        if (open && !dialog.value?.open) dialog.value?.showModal();
        if (!open && dialog.value?.open) dialog.value?.close();
    },
    { immediate: true },
);

// Clicking the backdrop (the dialog element itself, outside the panel) closes.
const onClick = (e: MouseEvent) => {
    if (e.target === dialog.value) emit('close');
};
</script>

<template>
    <dialog
        ref="dialog"
        :class="[
            'm-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] overflow-hidden rounded-[var(--radius-card)] bg-surface p-0 text-ink-soft shadow-pop backdrop:bg-slate-950/50 backdrop:backdrop-blur-[2px]',
            { sm: 'max-w-md', md: 'max-w-lg', lg: 'max-w-2xl', xl: 'max-w-4xl' }[size],
        ]"
        :aria-label="title"
        @close="emit('close')"
        @cancel.prevent="emit('close')"
        @click="onClick"
    >
        <div v-if="show" class="flex max-h-[calc(100dvh-2rem)] flex-col">
            <header class="flex items-start justify-between gap-4 border-b border-line-soft px-5 py-4">
                <div>
                    <h2 class="text-base font-semibold text-ink">{{ title }}</h2>
                    <p v-if="description" class="mt-0.5 text-[13px] text-muted">{{ description }}</p>
                </div>
                <button type="button" class="-m-1 rounded-md p-1 text-subtle hover:bg-surface-sunken hover:text-ink" aria-label="Close" @click="emit('close')">
                    <X :size="18" />
                </button>
            </header>
            <div class="overflow-y-auto px-5 py-4"><slot /></div>
            <footer v-if="$slots.footer" class="flex flex-wrap justify-end gap-2 border-t border-line-soft bg-surface-muted/60 px-5 py-3"><slot name="footer" /></footer>
        </div>
    </dialog>
</template>
