<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

interface Toast {
    id: number;
    tone: 'success' | 'warning' | 'error';
    text: string;
}

const page = usePage();
const toasts = ref<Toast[]>([]);
let next = 0;

watch(
    () => page.props.flash,
    (flash) => {
        for (const tone of ['success', 'warning', 'error'] as const) {
            const text = flash?.[tone];
            if (text) {
                const id = ++next;
                toasts.value.push({ id, tone, text });
                setTimeout(() => (toasts.value = toasts.value.filter((t) => t.id !== id)), tone === 'success' ? 5000 : 9000);
            }
        }
    },
    { immediate: true, deep: true },
);
</script>

<template>
    <div class="pointer-events-none fixed inset-x-0 top-4 z-50 flex flex-col items-center gap-2 px-4 sm:items-end" aria-live="polite">
        <TransitionGroup
            enter-from-class="opacity-0 -translate-y-2"
            enter-active-class="transition duration-200"
            leave-to-class="opacity-0"
            leave-active-class="transition duration-150"
        >
            <div
                v-for="t in toasts"
                :key="t.id"
                :class="[
                    'pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-lg px-4 py-3 text-sm shadow-lg ring-1',
                    { success: 'bg-emerald-50 text-emerald-900 ring-emerald-200', warning: 'bg-amber-50 text-amber-900 ring-amber-200', error: 'bg-red-50 text-red-900 ring-red-200' }[t.tone],
                ]"
                :role="t.tone === 'error' ? 'alert' : 'status'"
            >
                <span class="flex-1">{{ t.text }}</span>
                <button type="button" class="opacity-60 hover:opacity-100" aria-label="Dismiss" @click="toasts = toasts.filter((x) => x.id !== t.id)">✕</button>
            </div>
        </TransitionGroup>
    </div>
</template>
