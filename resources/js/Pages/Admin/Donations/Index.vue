<script setup lang="ts">
import DataTable from '@/Components/DataTable.vue';
import { Download } from 'lucide-vue-next';
import AppButton from '@/Components/AppButton.vue';
import BarList from '@/Components/BarList.vue';
import CardPanel from '@/Components/CardPanel.vue';
import ConfirmsPassword from '@/Components/ConfirmsPassword.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FormField from '@/Components/FormField.vue';
import ModalDialog from '@/Components/ModalDialog.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PaginationNav from '@/Components/PaginationNav.vue';
import SelectInput from '@/Components/SelectInput.vue';
import StatCard from '@/Components/StatCard.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextArea from '@/Components/TextArea.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Option, Paginated } from '@/types';
import { router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

interface Row {
    id: number;
    reference: string;
    donor: string;
    email: string;
    amount: string;
    category: string;
    status: string;
    date: string;
    receipt_number: string | null;
    receipt_url: string | null;
    pan: string | null;
    wants_80g: boolean;
    refund_reason: string | null;
}

const props = defineProps<{
    donations: Paginated<Row>;
    byCategory: { label: string; value: number; gifts: number }[];
    totals: { raised: number; donors: number; thisFy: number };
    filters: Record<string, string | undefined>;
    categories: Option[];
    can: { refund: boolean; export: boolean };
}>();

const f = reactive({ status: props.filters.status ?? '', category: props.filters.category ?? '', q: props.filters.q ?? '' });
const query = computed(() => Object.fromEntries(Object.entries(f).filter(([, v]) => v)) as Record<string, string>);
const apply = () => router.get(route('admin.donations.index'), query.value, { preserveState: true });
const inr = (n: number) => '₹' + n.toLocaleString('en-IN');
const bars = computed(() => props.byCategory.map((c) => ({ label: c.label, value: c.value, hint: `· ${c.gifts} gifts` })));
const statusOptions = ['pending', 'paid', 'failed', 'refunded'].map((s) => ({ value: s, label: s[0].toUpperCase() + s.slice(1) }));

const refunding = ref<Row | null>(null);
const form = useForm({ reason: '' });
const refund = () => form.post(route('admin.donations.refund', refunding.value!.id), { preserveScroll: true, onSuccess: () => ((refunding.value = null), form.reset()) });
const badge = (s: string) => ({ paid: 'verified', failed: 'rejected', refunded: 'deactivated' })[s] ?? 'pending';
</script>

<template>
    <AppLayout title="Donations">
        <PageHeader title="Donations" description="Financial records. Refunds and exports need your password and are audited.">
            <AppButton v-if="can.export" external :href="route('admin.donations.export', query)" :icon="Download">Export CSV</AppButton>
        </PageHeader>

        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            <StatCard label="Raised (all time)" :value="inr(totals.raised)" />
            <StatCard label="This financial year" :value="inr(totals.thisFy)" />
            <StatCard label="Donors" :value="totals.donors" />
        </div>

        <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
            <section>
                <DataTable :empty="donations.data.length === 0" empty-title="No donations">
                    <template #toolbar>
                        <form class="grid w-full gap-2 gap-3 sm:grid-cols-4" role="search" @submit.prevent="apply">
                            <TextInput v-model="f.q" type="search" placeholder="Name, email, reference" aria-label="Search" />
                            <SelectInput v-model="f.status" :options="statusOptions" placeholder="Any status" aria-label="Status" />
                            <SelectInput v-model="f.category" :options="categories" placeholder="Any category" aria-label="Category" />
                            <AppButton type="submit">Filter</AppButton>
                        </form>
                    </template>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th scope="col">Donor</th>
                                <th scope="col">Amount</th>
                                <th scope="col">Receipt</th>
                                <th scope="col">Status</th>
                                <th scope="col"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="d in donations.data" :key="d.id">
                                <td><p class="font-medium">{{ d.donor }}</p><p class="text-muted">{{ d.email }}<template v-if="d.pan"> · PAN {{ d.pan }}</template></p></td>
                                <td><p class="font-medium tabular-nums">{{ d.amount }}</p><p class="text-muted">{{ d.category }} · {{ d.date }}</p></td>
                                <td>
                                    <a v-if="d.receipt_url" :href="d.receipt_url" class="text-brand-700 hover:underline">{{ d.receipt_number }}</a>
                                    <span v-else class="text-muted">{{ d.receipt_number ?? d.reference }}</span>
                                </td>
                                <td><StatusBadge :status="badge(d.status)" :label="d.status" /><p v-if="d.refund_reason" class="mt-1 text-xs text-muted">{{ d.refund_reason }}</p></td>
                                <td class="text-right">
                                    <ConfirmsPassword v-if="can.refund && d.status === 'paid'" @confirmed="refunding = d"><AppButton size="sm" variant="danger-ghost">Refund</AppButton></ConfirmsPassword>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <template #footer><PaginationNav :links="donations.links" :from="donations.from" :to="donations.to" :total="donations.total" /></template>
                </DataTable>
            </section>
            <CardPanel title="By category (paid)"><BarList :items="bars" /></CardPanel>
        </div>

        <ModalDialog :show="refunding !== null" :title="`Refund ${refunding?.amount} to ${refunding?.donor}?`" @close="refunding = null">
            <form id="refund-form" @submit.prevent="refund">
                <p class="mb-3 text-sm text-muted">The amount goes back through the payment provider. The receipt number stays on record, marked refunded.</p>
                <FormField label="Reason" :error="form.errors.reason" required><TextArea v-model="form.reason" rows="3" /></FormField>
            </form>
            <template #footer>
                <AppButton variant="secondary" @click="refunding = null">Cancel</AppButton>
                <AppButton type="submit" form="refund-form" variant="danger" :loading="form.processing">Refund</AppButton>
            </template>
        </ModalDialog>
    </AppLayout>
</template>
