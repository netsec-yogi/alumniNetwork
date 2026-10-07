<script setup lang="ts">
import DataTable from '@/Components/DataTable.vue';
import { ask } from '@/lib/confirm';
import AppButton from '@/Components/AppButton.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PaginationNav from '@/Components/PaginationNav.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Paginated } from '@/types';
import { Link, router, usePoll } from '@inertiajs/vue3';

interface Row {
    id: number;
    name: string;
    subject: string;
    status: string;
    channels: string[];
    recipients: number;
    emails: number;
    by: string | null;
    when: string | null;
}

const props = defineProps<{ campaigns: Paginated<Row> }>();
// Refresh while anything is sending.
usePoll(5000, { only: ['campaigns'] }, { autoStart: props.campaigns.data.some((c) => c.status === 'sending') });
const cancel = (c: Row) => ask('Cancel this scheduled send?').then((ok) => ok && router.post(route('admin.communications.cancel', c.id), {}, { preserveScroll: true }));
const badge = (s: string) => ({ sent: 'verified', sending: 'pending', scheduled: 'pending', cancelled: 'deactivated' })[s] ?? 'active';
</script>

<template>
    <AppLayout title="Communications">
        <PageHeader title="Communications" description="Email and in-app messages to a chosen audience. Email only reaches people who opted in.">
            <AppButton :href="route('admin.communications.create')">New message</AppButton>
        </PageHeader>
        <DataTable :empty="campaigns.data.length === 0" empty-title="Nothing sent yet">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col">Message</th>
                        <th scope="col">Channels</th>
                        <th scope="col">Reach</th>
                        <th scope="col">When</th>
                        <th scope="col">Status</th>
                        <th scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="c in campaigns.data" :key="c.id">
                        <td><p class="font-medium text-ink">{{ c.name }}</p><p class="text-muted">{{ c.subject }}</p></td>
                        <td class="text-muted">{{ c.channels.map((ch) => (ch === 'in_app' ? 'In-app' : 'Email')).join(', ') }}</td>
                        <td class="tabular-nums">{{ c.status === 'sent' ? `${c.recipients} people · ${c.emails} emails` : '—' }}</td>
                        <td class="text-muted">{{ c.when ?? '—' }}</td>
                        <td><StatusBadge :status="badge(c.status)" :label="c.status" /></td>
                        <td class="text-right whitespace-nowrap">
                            <Link v-if="c.status === 'draft' || c.status === 'scheduled'" :href="route('admin.communications.edit', c.id)" class="text-brand-700 hover:underline">Edit</Link>
                            <button v-if="c.status === 'scheduled'" type="button" class="ml-3 text-red-700" @click="cancel(c)">Cancel</button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <template #footer><PaginationNav :links="campaigns.links" :from="campaigns.from" :to="campaigns.to" :total="campaigns.total" /></template>
        </DataTable>
    </AppLayout>
</template>
