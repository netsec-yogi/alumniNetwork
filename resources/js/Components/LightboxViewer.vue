<script setup lang="ts">
import { ChevronLeft, ChevronRight, X } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';

/**
 * Full-screen image viewer on the native <dialog> (focus trap, Escape).
 * Arrow keys and swipes move between images.
 */
const props = defineProps<{ items: { full: string; title: string; caption?: string | null }[] }>();
const index = defineModel<number | null>({ required: true });
const dialog = ref<HTMLDialogElement>();
const current = computed(() => (index.value === null ? null : props.items[index.value]));

watch(index, async (i) => {
    await nextTick();
    if (i !== null && !dialog.value?.open) dialog.value?.showModal();
    if (i === null && dialog.value?.open) dialog.value?.close();
});

const go = (d: number) => index.value !== null && (index.value = (index.value + d + props.items.length) % props.items.length);
function onKey(e: KeyboardEvent) {
    if (e.key === 'ArrowRight') go(1);
    if (e.key === 'ArrowLeft') go(-1);
}
let touchX = 0;
const onTouchStart = (e: TouchEvent) => (touchX = e.touches[0]!.clientX);
function onTouchEnd(e: TouchEvent) {
    const dx = e.changedTouches[0]!.clientX - touchX;
    if (Math.abs(dx) > 50) go(dx < 0 ? 1 : -1);
}
</script>

<template>
    <dialog
        ref="dialog"
        class="m-0 h-dvh max-h-none w-screen max-w-none bg-slate-950/95 p-0 text-white backdrop:bg-black/60"
        :aria-label="current?.title ?? 'Photo'"
        @close="index = null"
        @cancel.prevent="index = null"
        @keydown="onKey"
        @touchstart.passive="onTouchStart"
        @touchend="onTouchEnd"
    >
        <div v-if="current" class="flex h-full flex-col">
            <div class="flex items-center justify-between gap-4 px-4 py-3 sm:px-6">
                <p class="truncate text-sm font-semibold">{{ current.title }} <span class="ml-2 font-normal text-white/50">{{ (index ?? 0) + 1 }} / {{ items.length }}</span></p>
                <button type="button" class="rounded-full p-2 hover:bg-white/10" aria-label="Close" @click="index = null"><X :size="22" /></button>
            </div>
            <div class="relative flex min-h-0 flex-1 items-center justify-center px-2 sm:px-16">
                <img :key="current.full" :src="current.full" :alt="current.title" class="max-h-full max-w-full animate-fade-up rounded-xl object-contain" />
                <button v-if="items.length > 1" type="button" class="absolute left-2 rounded-full bg-white/10 p-3 backdrop-blur hover:bg-white/20 sm:left-4" aria-label="Previous photo" @click="go(-1)"><ChevronLeft :size="22" /></button>
                <button v-if="items.length > 1" type="button" class="absolute right-2 rounded-full bg-white/10 p-3 backdrop-blur hover:bg-white/20 sm:right-4" aria-label="Next photo" @click="go(1)"><ChevronRight :size="22" /></button>
            </div>
            <p v-if="current.caption" class="px-6 py-4 text-center text-sm text-white/75">{{ current.caption }}</p>
        </div>
    </dialog>
</template>
