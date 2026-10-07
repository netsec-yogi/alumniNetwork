<script setup lang="ts">
import AlertBox from '@/Components/AlertBox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';

defineProps<{ amount: string; reference: string; description?: string; payUrl: string; failUrl: string }>();
</script>

<template>
    <GuestLayout title="Test payment">
        <AlertBox tone="warning">Development test gateway — no money moves. In production this is the provider’s hosted page.</AlertBox>
        <p class="mt-4 text-center text-3xl font-semibold">{{ amount }}</p>
        <p v-if="description" class="text-center text-sm text-muted">{{ description }}</p>
        <p class="text-center text-xs text-muted">{{ reference }}</p>
        <div class="mt-6 grid grid-cols-2 gap-3">
            <a :href="payUrl" class="rounded-lg bg-emerald-600 px-4 py-2 text-center text-sm font-medium text-white hover:bg-emerald-500">Simulate success</a>
            <a :href="failUrl" class="rounded-lg bg-surface px-4 py-2 text-center text-sm font-medium text-ink-soft ring-1 ring-line-strong hover:bg-surface-muted">Simulate failure</a>
        </div>
    </GuestLayout>
</template>
