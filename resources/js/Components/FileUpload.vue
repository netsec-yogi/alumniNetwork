<script setup lang="ts">
import { CloudUpload, FileText, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useField } from './useField';

/**
 * Drop zone + picker for a single file (v-model is the File). Shows the
 * chosen file, an optional upload progress bar (`progress`, 0-100, e.g.
 * from useForm), and lets the user clear it. Server-side checks still
 * decide what is accepted; `accept`/`maxMb` only guide the user.
 */
const props = defineProps<{ accept?: string; maxMb?: number; hint?: string; progress?: number | null; disabled?: boolean }>();
const model = defineModel<File | null>();
const field = useField();
const dragging = ref(false);
const input = ref<HTMLInputElement>();
const localError = ref('');

function pick(file: File | undefined | null) {
    localError.value = '';
    if (!file) return;
    if (props.maxMb && file.size > props.maxMb * 1024 * 1024) {
        localError.value = `That file is larger than ${props.maxMb} MB.`;
        return;
    }
    model.value = file;
}
const size = computed(() => (model.value ? (model.value.size > 1048576 ? `${(model.value.size / 1048576).toFixed(1)} MB` : `${Math.ceil(model.value.size / 1024)} KB`) : ''));
function clear() {
    model.value = null;
    if (input.value) input.value.value = '';
}
</script>

<template>
    <div>
        <div v-if="model" class="flex items-center gap-3 rounded-md bg-surface-muted px-3 py-2.5 ring-1 ring-line ring-inset">
            <FileText :size="20" class="shrink-0 text-brand-600" aria-hidden="true" />
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium text-ink">{{ model.name }}</p>
                <p class="text-xs text-muted">{{ size }}<template v-if="progress != null"> · uploading {{ progress }}%</template></p>
                <div v-if="progress != null" class="mt-1.5 h-1 overflow-hidden rounded-full bg-line">
                    <div class="h-full rounded-full bg-brand-500 transition-[width]" :style="{ width: `${progress}%` }" />
                </div>
            </div>
            <button type="button" class="rounded p-1 text-subtle hover:bg-surface-sunken hover:text-ink" :disabled="disabled" aria-label="Remove file" @click="clear"><X :size="16" /></button>
        </div>
        <label
            v-else
            :class="[
                'flex cursor-pointer flex-col items-center justify-center gap-1.5 rounded-md border-2 border-dashed px-4 py-6 text-center transition-colors',
                dragging ? 'border-brand-500 bg-brand-50/50 dark:bg-brand-500/10' : field.invalid() ? 'border-red-300' : 'border-line-strong hover:border-brand-400 hover:bg-surface-muted',
                disabled && 'pointer-events-none opacity-60',
            ]"
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="(dragging = false), pick($event.dataTransfer?.files?.[0])"
        >
            <CloudUpload :size="24" class="text-subtle" aria-hidden="true" />
            <span class="text-sm text-ink-soft"><span class="font-medium text-brand-600">Choose a file</span> or drag it here</span>
            <span v-if="hint" class="text-xs text-muted">{{ hint }}</span>
            <input ref="input" type="file" class="sr-only" :accept="accept" :disabled="disabled" v-bind="field.attrs()" @change="pick(($event.target as HTMLInputElement).files?.[0])" />
        </label>
        <p v-if="localError" class="mt-1.5 text-[13px] text-red-600" role="alert">{{ localError }}</p>
    </div>
</template>
