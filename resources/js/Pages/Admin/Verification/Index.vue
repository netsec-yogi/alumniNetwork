<script setup lang="ts">
import AppButton from '@/Components/AppButton.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FormField from '@/Components/FormField.vue';
import ModalDialog from '@/Components/ModalDialog.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PaginationNav from '@/Components/PaginationNav.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextArea from '@/Components/TextArea.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Paginated } from '@/types';
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Person {
    name: string;
    roll_number: string;
    programme: string;
    admission_year: number | null;
    graduation_year: number;
}

interface Request {
    id: number;
    submitted_at: string;
    method: string;
    note: string | null;
    claim: Person & { email: string; email_verified: boolean };
    record: Person | null;
    decision: { by: string; at: string; reason: string | null } | null;
    can_decide: boolean;
}

defineProps<{ requests: Paginated<Request>; status: string; counts: Record<string, number> }>();

const tabs = ['pending', 'verified', 'rejected'];
const deciding = ref<{ request: Request; action: 'approve' | 'reject' } | null>(null);
const form = useForm({ reason: '' });

const fields: { key: keyof Person; label: string }[] = [
    { key: 'name', label: 'Name' },
    { key: 'roll_number', label: 'Roll number' },
    { key: 'programme', label: 'Programme' },
    { key: 'admission_year', label: 'Admission' },
    { key: 'graduation_year', label: 'Graduation' },
];

const differs = (r: Request, key: keyof Person) => r.record !== null && String(r.record[key] ?? '').toLowerCase() !== String(r.claim[key] ?? '').toLowerCase();

function submit() {
    if (!deciding.value) return;
    const { request, action } = deciding.value;
    form.post(route(`admin.verification.${action}`, request.id), { preserveScroll: true, onSuccess: close });
}

function close() {
    deciding.value = null;
    form.reset();
    form.clearErrors();
}
</script>

<template>
    <AppLayout title="Verification">
        <PageHeader title="Alumni verification" description="Claims that could not be matched to the institute's records automatically." />

        <nav class="mb-6 flex gap-2" aria-label="Status">
            <Link
                v-for="t in tabs"
                :key="t"
                :href="route('admin.verification.index', { status: t })"
                :aria-current="status === t ? 'page' : undefined"
                :class="['rounded-full px-3 py-1.5 text-sm font-medium capitalize', status === t ? 'bg-brand-800 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50']"
                >{{ t }} <span class="tabular-nums opacity-75">({{ counts[t] ?? 0 }})</span></Link
            >
        </nav>

        <EmptyState v-if="requests.data.length === 0" :title="status === 'pending' ? 'The queue is empty' : `No ${status} requests`" description="Nothing to review right now." />

        <div v-else class="space-y-4">
            <article v-for="r in requests.data" :key="r.id" class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <header class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-slate-900">{{ r.claim.name }}</h2>
                        <p class="text-sm text-slate-500">
                            {{ r.claim.email }}
                            <StatusBadge :status="r.claim.email_verified ? 'verified' : 'pending'" :label="r.claim.email_verified ? 'email verified' : 'email unverified'" class="ml-1" />
                        </p>
                        <p class="text-xs text-slate-400">Submitted {{ r.submitted_at }}</p>
                    </div>
                    <div v-if="r.can_decide" class="flex gap-2">
                        <AppButton size="sm" @click="deciding = { request: r, action: 'approve' }">Verify</AppButton>
                        <AppButton size="sm" variant="danger" @click="deciding = { request: r, action: 'reject' }">Reject</AppButton>
                    </div>
                    <p v-else-if="status === 'pending'" class="text-xs text-slate-500">You can't decide your own claim.</p>
                </header>

                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-slate-500">
                                <th class="py-1 pr-4 font-medium"></th>
                                <th class="py-1 pr-4 font-medium">Claimed</th>
                                <th class="py-1 font-medium">Institute record</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="f in fields" :key="f.key" class="border-t border-slate-100">
                                <th class="py-1.5 pr-4 text-left font-normal text-slate-500">{{ f.label }}</th>
                                <td class="py-1.5 pr-4">{{ r.claim[f.key] ?? '—' }}</td>
                                <td :class="['py-1.5', differs(r, f.key) ? 'font-medium text-red-700' : '']">
                                    <template v-if="r.record">{{ r.record[f.key] ?? '—' }}</template>
                                    <span v-else-if="f.key === 'name'" class="text-slate-400">No record for this roll number</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p v-if="r.note" class="mt-3 rounded-lg bg-slate-50 p-3 text-sm text-slate-700"><span class="font-medium">Applicant's note:</span> {{ r.note }}</p>
                <p v-if="r.decision" class="mt-3 text-sm text-slate-600">
                    Decided by <span class="font-medium">{{ r.decision.by }}</span> on {{ r.decision.at }}<template v-if="r.decision.reason"> — "{{ r.decision.reason }}"</template>
                </p>
            </article>

            <PaginationNav :links="requests.links" :from="requests.from" :to="requests.to" :total="requests.total" />
        </div>

        <ModalDialog :show="deciding !== null" :title="deciding?.action === 'approve' ? `Verify ${deciding?.request.claim.name}?` : `Reject ${deciding?.request.claim.name}?`" @close="close">
            <form id="decision-form" @submit.prevent="submit">
                <FormField
                    :label="deciding?.action === 'approve' ? 'Note (optional)' : 'Reason (sent to the applicant)'"
                    :error="form.errors.reason"
                    :required="deciding?.action === 'reject'"
                    :hint="deciding?.action === 'reject' ? 'Explain what they can do, e.g. which document to send.' : undefined"
                >
                    <TextArea v-model="form.reason" rows="3" />
                </FormField>
            </form>
            <template #footer>
                <AppButton variant="secondary" @click="close">Cancel</AppButton>
                <AppButton type="submit" form="decision-form" :variant="deciding?.action === 'reject' ? 'danger' : 'primary'" :loading="form.processing">
                    {{ deciding?.action === 'approve' ? 'Verify' : 'Reject' }}
                </AppButton>
            </template>
        </ModalDialog>
    </AppLayout>
</template>
