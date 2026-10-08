<script setup lang="ts">
import { Check } from 'lucide-vue-next';

/** Progress through a multi-step form. Steps before `current` are done; earlier steps can be revisited. */
defineProps<{ steps: string[]; current: number }>();
const emit = defineEmits<{ go: [index: number] }>();
</script>

<template>
    <nav aria-label="Progress">
        <ol class="flex items-center gap-2">
            <li v-for="(step, i) in steps" :key="step" class="flex flex-1 items-center gap-2 last:flex-none">
                <button
                    type="button"
                    :disabled="i >= current"
                    :aria-current="i === current ? 'step' : undefined"
                    class="flex items-center gap-2 text-left disabled:cursor-default"
                    @click="emit('go', i)"
                >
                    <span
                        :class="[
                            'grid size-7 shrink-0 place-items-center rounded-full text-xs font-bold transition-colors',
                            i < current ? 'bg-brand-600 text-white' : i === current ? 'bg-brand-600 text-white ring-4 ring-brand-100' : 'bg-surface-sunken text-muted',
                        ]"
                    >
                        <Check v-if="i < current" :size="14" :stroke-width="3" aria-hidden="true" />
                        <template v-else>{{ i + 1 }}</template>
                    </span>
                    <span :class="['hidden text-sm font-semibold sm:block', i <= current ? 'text-ink' : 'text-muted']">{{ step }}</span>
                    <span class="sr-only">{{ i < current ? '(done)' : i === current ? '(current)' : '' }}</span>
                </button>
                <span v-if="i < steps.length - 1" :class="['h-0.5 flex-1 rounded-full', i < current ? 'bg-brand-600' : 'bg-line']" aria-hidden="true" />
            </li>
        </ol>
    </nav>
</template>
