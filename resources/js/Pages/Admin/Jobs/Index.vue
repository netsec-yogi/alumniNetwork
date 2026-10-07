<script setup lang="ts">
import AppButton from '@/Components/AppButton.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FormField from '@/Components/FormField.vue';
import ModalDialog from '@/Components/ModalDialog.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PaginationNav from '@/Components/PaginationNav.vue';
import TextArea from '@/Components/TextArea.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Paginated } from '@/types';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Row {
    id: number;
    title: string;
    organization: string;
    type: string;
    location: string | null;
    apply: string | null;
    excerpt: string;
    poster: { name: string; email: string; roles: string[] };
    submitted_at: string;
    rejection_reason: string | null;
    referrals: number;
}

defineProps<{ jobs: Paginated<Row>; status: string; counts: Record<string, number> }>();

const rejecting = ref<Row | null>(null);
const form = useForm({ reason: '' });
const approve = (j: Row) => router.post(route('admin.jobs.approve', j.id), {}, { preserveScroll: true });
const reject = () => form.post(route('admin.jobs.reject', rejecting.value!.id), { preserveScroll: true, onSuccess: () => ((rejecting.value = null), form.reset()) });
</script>

<template>
    <AppLayout title="Job moderation">
        <PageHeader title="Job moderation" description="Check that postings are genuine, relevant and link somewhere safe before they go live." />

        <nav class="mb-6 flex flex-wrap gap-2" aria-label="Status">
            <Link
                v-for="s in ['pending', 'approved', 'rejected', 'closed']"
                :key="s"
                :href="route('admin.jobs.index', { status: s })"
                :class="['rounded-full px-3 py-1.5 text-sm font-medium capitalize', status === s ? 'bg-brand-600 text-white shadow-sm' : 'bg-surface text-ink-soft ring-1 ring-line-strong']"
                >{{ s }} <span class="tabular-nums opacity-75">({{ counts[s] ?? 0 }})</span></Link
            >
        </nav>

        <EmptyState v-if="jobs.data.length === 0" :title="status === 'pending' ? 'Nothing waiting for review' : 'Nothing here'" />
        <ul v-else class="space-y-3">
            <li v-for="j in jobs.data" :key="j.id" class="card p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <Link :href="route('jobs.show', j.id)" class="font-semibold text-brand-800 hover:underline">{{ j.title }}</Link>
                        <p class="text-sm text-muted">{{ j.organization }} · {{ j.type }}<template v-if="j.location"> · {{ j.location }}</template></p>
                        <p class="mt-2 text-sm text-ink-soft">{{ j.excerpt }}</p>
                        <p class="mt-2 text-xs break-all text-muted">Apply: {{ j.apply }}</p>
                        <p class="mt-1 text-xs text-subtle">By {{ j.poster.name }} ({{ j.poster.email }}, {{ j.poster.roles.join(', ') }}) · {{ j.submitted_at }}</p>
                        <p v-if="j.rejection_reason" class="mt-2 text-sm text-red-700">{{ j.rejection_reason }}</p>
                    </div>
                    <div v-if="status === 'pending'" class="flex gap-2">
                        <AppButton size="sm" @click="approve(j)">Approve</AppButton>
                        <AppButton size="sm" variant="danger" @click="rejecting = j">Reject</AppButton>
                    </div>
                </div>
            </li>
        </ul>
        <PaginationNav class="mt-4" :links="jobs.links" :from="jobs.from" :to="jobs.to" :total="jobs.total" />

        <ModalDialog :show="rejecting !== null" :title="`Reject “${rejecting?.title}”`" @close="rejecting = null">
            <form id="reject-job" @submit.prevent="reject">
                <FormField label="Reason (sent to the poster)" :error="form.errors.reason" required><TextArea v-model="form.reason" rows="3" /></FormField>
            </form>
            <template #footer>
                <AppButton variant="secondary" @click="rejecting = null">Cancel</AppButton>
                <AppButton type="submit" form="reject-job" variant="danger" :loading="form.processing">Reject</AppButton>
            </template>
        </ModalDialog>
    </AppLayout>
</template>
