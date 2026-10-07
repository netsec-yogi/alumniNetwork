<script setup lang="ts">
import DataTable from '@/Components/DataTable.vue';
import { ask } from '@/lib/confirm';
import AppButton from '@/Components/AppButton.vue';
import ConfirmsPassword from '@/Components/ConfirmsPassword.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FormField from '@/Components/FormField.vue';
import ModalDialog from '@/Components/ModalDialog.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextArea from '@/Components/TextArea.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Row {
    id: number;
    slug: string;
    title: string;
    type: string;
    status: string;
    stage: string;
    organizer: string;
    organizer_email: string;
    goal: number;
    raised: number;
    dates: string;
}

defineProps<{ campaigns: Row[] }>();
const rejecting = ref<Row | null>(null);
const form = useForm({ decision: 'reject', reason: '' });
const approve = (c: Row) => router.post(route('admin.fundraising.review', c.slug), { decision: 'approve' }, { preserveScroll: true });
const reject = () => form.post(route('admin.fundraising.review', rejecting.value!.slug), { preserveScroll: true, onSuccess: () => ((rejecting.value = null), form.reset('reason')) });
const cancel = (c: Row) => ask(`Cancel “${c.title}”?`).then((ok) => ok && router.post(route('admin.fundraising.cancel', c.slug), {}, { preserveScroll: true }));
const inr = (n: number) => '₹' + n.toLocaleString('en-IN');
const badge = (s: string) => ({ active: 'active', completed: 'verified', pending_approval: 'pending', rejected: 'rejected', cancelled: 'deactivated', upcoming: 'pending' })[s] ?? 'deactivated';
</script>

<template>
    <AppLayout title="Fundraising">
        <PageHeader title="Fundraising campaigns" description="Approve alumni crowdfunding and oversee institute campaigns and Giving Days.">
            <AppButton :href="route('fundraising.create')">New campaign</AppButton>
        </PageHeader>
        <DataTable :empty="campaigns.length === 0" empty-title="No campaigns">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col">Campaign</th>
                        <th scope="col">Organiser</th>
                        <th scope="col">Raised / goal</th>
                        <th scope="col">Status</th>
                        <th scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="c in campaigns" :key="c.id">
                        <td><Link :href="route('fundraising.show', c.slug)" class="font-medium text-brand-800 hover:underline">{{ c.title }}</Link><p class="text-muted">{{ c.type }} · {{ c.dates }}</p></td>
                        <td><p>{{ c.organizer }}</p><p class="text-muted">{{ c.organizer_email }}</p></td>
                        <td class="tabular-nums">{{ inr(c.raised) }} / {{ inr(c.goal) }}</td>
                        <td><StatusBadge :status="badge(c.status === 'published' ? c.stage : c.status)" :label="c.status === 'published' ? c.stage : c.status.replace('_', ' ')" /></td>
                        <td class="text-right whitespace-nowrap">
                            <template v-if="c.status === 'pending_approval'">
                                <AppButton size="sm" @click="approve(c)">Approve</AppButton>
                                <AppButton size="sm" variant="ghost" @click="rejecting = c">Send back</AppButton>
                            </template>
                            <ConfirmsPassword v-else-if="c.status === 'published' && c.stage !== 'completed'" @confirmed="cancel(c)"><AppButton size="sm" variant="danger-ghost">Cancel</AppButton></ConfirmsPassword>
                        </td>
                    </tr>
                </tbody>
            </table>
        </DataTable>
        <ModalDialog :show="rejecting !== null" title="Send back for changes" @close="rejecting = null">
            <form id="reject-campaign" @submit.prevent="reject">
                <FormField label="What needs to change (sent to the organiser)" :error="form.errors.reason" required><TextArea v-model="form.reason" rows="3" /></FormField>
            </form>
            <template #footer>
                <AppButton variant="secondary" @click="rejecting = null">Cancel</AppButton>
                <AppButton type="submit" form="reject-campaign" :loading="form.processing">Send back</AppButton>
            </template>
        </ModalDialog>
    </AppLayout>
</template>
