<script setup lang="ts">
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
                :class="['rounded-full px-3 py-1.5 text-sm font-medium capitalize', status === t ? 'bg-brand-800 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-300']"
                >{{ t }}</Link
            >
        </nav>

        <EmptyState v-if="events.data.length === 0" title="No events" />
        <div v-else class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-600">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-medium">Event</th>
                        <th scope="col" class="px-4 py-3 font-medium">When</th>
                        <th scope="col" class="px-4 py-3 font-medium">Registered</th>
                        <th scope="col" class="px-4 py-3 font-medium">Waitlist</th>
                        <th scope="col" class="px-4 py-3 font-medium">Checked in</th>
                        <th scope="col" class="px-4 py-3 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="e in events.data" :key="e.id" class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <Link :href="route('admin.events.show', e.slug)" class="font-medium text-brand-800 hover:underline">{{ e.title }}</Link>
                            <p class="text-slate-500">{{ e.type_label }}</p>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-slate-600">{{ e.starts_at }}</td>
                        <td class="px-4 py-3 tabular-nums">{{ e.confirmed }}<span v-if="e.capacity" class="text-slate-400"> / {{ e.capacity }}</span></td>
                        <td class="px-4 py-3 tabular-nums">{{ e.waitlisted }}</td>
                        <td class="px-4 py-3 tabular-nums">{{ e.checked_in }}</td>
                        <td class="px-4 py-3"><StatusBadge :status="e.status === 'published' ? 'active' : e.status" :label="e.status" /></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <PaginationNav class="mt-4" :links="events.links" :from="events.from" :to="events.to" :total="events.total" />
    </AppLayout>
</template>
