<script setup lang="ts">
import { provide, useId } from 'vue';

const props = defineProps<{
    label: string;
    error?: string;
    hint?: string;
    required?: boolean;
}>();

const id = useId();
provide('field', { id, describedBy: `${id}-desc`, invalid: () => !!props.error });
</script>

<template>
    <div>
        <label :for="id" class="block text-sm font-medium text-slate-700">
            {{ label }}<span v-if="required" class="text-red-600" aria-hidden="true"> *</span>
        </label>
        <div class="mt-1.5">
            <slot :id="id" />
        </div>
        <p v-if="error" :id="`${id}-desc`" class="mt-1.5 text-sm text-red-600" role="alert">{{ error }}</p>
        <p v-else-if="hint" :id="`${id}-desc`" class="mt-1.5 text-sm text-slate-500">{{ hint }}</p>
    </div>
</template>
