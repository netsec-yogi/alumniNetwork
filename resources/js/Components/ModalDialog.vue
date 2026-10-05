<script setup lang="ts">
import { nextTick, ref, watch } from 'vue';

const props = defineProps<{ show: boolean; title: string }>();
const emit = defineEmits<{ close: [] }>();
const dialog = ref<HTMLDialogElement>();

// The native <dialog> gives focus trapping, Escape-to-close and inert
// background for free, which is most of what accessibility needs here.
watch(
    () => props.show,
    async (open) => {
        await nextTick();
        if (open && !dialog.value?.open) dialog.value?.showModal();
        if (!open && dialog.value?.open) dialog.value?.close();
    },
    { immediate: true },
);
</script>

<template>
    <dialog
        ref="dialog"
        class="m-auto w-[calc(100%-2rem)] max-w-lg rounded-xl p-0 shadow-xl backdrop:bg-slate-900/50"
        :aria-label="title"
        @close="emit('close')"
        @cancel.prevent="emit('close')"
    >
        <div v-if="show" class="p-6">
            <h2 class="text-lg font-semibold text-slate-900">{{ title }}</h2>
            <div class="mt-3"><slot /></div>
            <div v-if="$slots.footer" class="mt-6 flex justify-end gap-2"><slot name="footer" /></div>
        </div>
    </dialog>
</template>
