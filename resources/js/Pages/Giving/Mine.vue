<script setup lang="ts">
import AppButton from '@/Components/AppButton.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps<{ donations: { reference: string; amount: string; category: string; status: string; date: string; receipt_number: string | null; receipt_url: string | null }[] }>();
const badge = (s: string) => ({ paid: 'verified', failed: 'rejected', refunded: 'deactivated' })[s] ?? 'pending';
</script>

<template>
    <AppLayout title="My donations">
        <PageHeader title="My donations"><AppButton :href="route('giving.index')">Give again</AppButton></PageHeader>
        <EmptyState v-if="donations.length === 0" title="No donations yet" />
        <ul v-else class="card divide-y divide-line-soft">
            <li v-for="d in donations" :key="d.reference" class="flex flex-wrap items-center justify-between gap-3 px-5 py-3 text-sm">
                <div>
                    <p class="font-medium text-ink">{{ d.amount }} · {{ d.category }}</p>
                    <p class="text-muted">{{ d.date }} · {{ d.receipt_number ?? d.reference }}</p>
                </div>
                <span class="flex items-center gap-3">
                    <a v-if="d.receipt_url" :href="d.receipt_url" class="text-brand-700 hover:underline">Receipt (PDF)</a>
                    <StatusBadge :status="badge(d.status)" :label="d.status" />
                </span>
            </li>
        </ul>
    </AppLayout>
</template>
