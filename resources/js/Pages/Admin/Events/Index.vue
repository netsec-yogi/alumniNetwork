<script setup lang="ts">
import DataTable from '@/Components/DataTable.vue';
import AppButton from '@/Components/AppButton.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PaginationNav from '@/Components/PaginationNav.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Paginated } from '@/types';
import { Link } from '@inertiajs/vue3';

interface Row {
    id: number;
    slug: string;
    title: string;
    type_label: string;
    starts_at: string;
    status: string;
    capacity: number | null;
    confirmed: number;
    waitlisted: number;
    checked_in: number;
}

defineProps<{ events: Paginated<Row>; status: string; canCreate: boolean }>();
const tabs = ['upcoming', 'past', 'draft', 'cancelled'];
</script>

<template>
    <AppLayout title="Manage events">
        <PageHeader title="Events" description="Create events, follow registrations and run check-in.">
            <AppButton v-if="canCreate" :href="route('admin.events.create')">New event</AppButton>
        </PageHeader>

        <nav class="mb-6 flex flex-wrap gap-2" aria-label="Status">
            <Link
                v-for="t in tabs"
                :key="t"
                :href="route('admin.events.index', { status: t })"
                :class="['rounded-full px-3 py-1.5 text-sm font-medium capitalize', status === t ? 'bg-brand-600 text-white shadow-sm' : 'bg-surface text-ink-soft ring-1 ring-line-strong']"
                >{{ t }}</Link
            >
        </nav>

        <DataTable :empty="events.data.length === 0" empty-title="No events">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col">Event</th>
                        <th scope="col">When</th>
                        <th scope="col">Registered</th>
                        <th scope="col">Waitlist</th>
                        <th scope="col">Checked in</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="e in events.data" :key="e.id" class="hover:bg-surface-muted">
                        <td>
                            <Link :href="route('admin.events.show', e.slug)" class="font-medium text-brand-800 hover:underline">{{ e.title }}</Link>
                            <p class="text-muted">{{ e.type_label }}</p>
                        </td>
                        <td class="whitespace-nowrap text-muted">{{ e.starts_at }}</td>
                        <td class="tabular-nums">{{ e.confirmed }}<span v-if="e.capacity" class="text-subtle"> / {{ e.capacity }}</span></td>
                        <td class="tabular-nums">{{ e.waitlisted }}</td>
                        <td class="tabular-nums">{{ e.checked_in }}</td>
                        <td><StatusBadge :status="e.status === 'published' ? 'active' : e.status" :label="e.status" /></td>
                    </tr>
                </tbody>
            </table>
            <template #footer><PaginationNav :links="events.links" :from="events.from" :to="events.to" :total="events.total" /></template>
        </DataTable>
    </AppLayout>
</template>
