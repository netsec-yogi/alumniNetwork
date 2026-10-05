<script setup lang="ts">
defineProps<{ title?: string; description?: string }>();
</script>

<template>
    <section class="rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
        <header v-if="title || $slots.actions" class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <div>
                <h2 v-if="title" class="text-base font-semibold text-slate-900">{{ title }}</h2>
                <p v-if="description" class="mt-0.5 text-sm text-slate-500">{{ description }}</p>
            </div>
            <div v-if="$slots.actions" class="flex items-center gap-2"><slot name="actions" /></div>
        </header>
        <div class="px-5 py-5"><slot /></div>
    </section>
</template>
