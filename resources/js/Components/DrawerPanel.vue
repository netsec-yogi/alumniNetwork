<script setup lang="ts">
import { X } from 'lucide-vue-next';
import { nextTick, ref, watch } from 'vue';

/** Side drawer (filters, previews, quick edits) on the native <dialog>. */
const props = withDefaults(defineProps<{ show: boolean; title: string; side?: 'right' | 'left'; width?: string }>(), { side: 'right', width: 'max-w-md' });
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
const onClick = (e: MouseEvent) => e.target === dialog.value && emit('close');
</script>

<template>
    <dialog
        ref="dialog"
        :class="['fixed inset-y-0 m-0 h-dvh max-h-none w-full bg-surface p-0 text-ink-soft shadow-pop backdrop:bg-slate-950/40', width, side === 'right' ? 'right-0 left-auto' : 'left-0']"
        :aria-label="title"
        @close="emit('close')"
        @cancel.prevent="emit('close')"
        @click="onClick"
    >
        <div v-if="show" class="flex h-full flex-col">
            <header class="flex items-center justify-between border-b border-line-soft px-5 py-4">
                <h2 class="text-base font-semibold text-ink">{{ title }}</h2>
                <button type="button" class="-m-1 rounded-md p-1 text-subtle hover:bg-surface-sunken hover:text-ink" aria-label="Close" @click="emit('close')"><X :size="18" /></button>
            </header>
            <div class="flex-1 overflow-y-auto px-5 py-4"><slot /></div>
            <footer v-if="$slots.footer" class="flex justify-end gap-2 border-t border-line-soft px-5 py-3"><slot name="footer" /></footer>
        </div>
    </dialog>
</template>
