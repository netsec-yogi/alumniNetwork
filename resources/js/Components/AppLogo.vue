<script setup lang="ts">
defineProps<{ compact?: boolean }>();
</script>

<template>
    <span class="inline-flex items-center gap-2.5">
        <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-gradient-to-br from-brand-600 to-deep-800 text-[13px] font-bold tracking-tight text-white shadow-sm" aria-hidden="true">AC</span>
        <span v-if="!compact" class="leading-tight">
            <span class="block text-[15px] font-semibold text-ink">Alumni Connect</span>
            <span class="block text-xs text-muted">ABV-IIITM Gwalior</span>
        </span>
        <span v-else class="sr-only">Alumni Connect</span>
    </span>
</template>
