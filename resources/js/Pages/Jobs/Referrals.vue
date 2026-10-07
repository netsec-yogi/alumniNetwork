<script setup lang="ts">
import AppButton from '@/Components/AppButton.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FormField from '@/Components/FormField.vue';
import ModalDialog from '@/Components/ModalDialog.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PaginationNav from '@/Components/PaginationNav.vue';
import PersonCard from '@/Components/PersonCard.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextArea from '@/Components/TextArea.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Paginated } from '@/types';
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Item {
    id: number;
    job: { id: number; title: string; organization: string; poster: string };
    requester: { name: string; subtitle: string; profile_id: number | null };
    message: string;
    profile_url: string | null;
    status: string;
    response_note: string | null;
    at: string;
}

defineProps<{ tab: 'received' | 'sent'; requests: Paginated<Item> }>();

const responding = ref<{ item: Item; decision: 'accept' | 'decline' } | null>(null);
const form = useForm({ decision: 'accept', note: '' });
function open(item: Item, decision: 'accept' | 'decline') {
    responding.value = { item, decision };
    form.decision = decision;
}
const submit = () =>
    form.post(route('jobs.referrals.respond', responding.value!.item.id), {
        preserveScroll: true,
        onSuccess: () => {
            responding.value = null;
            form.reset();
        },
    });
const badge = (s: string) => (s === 'accepted' ? 'active' : s === 'declined' ? 'rejected' : 'pending');
</script>

<template>
    <AppLayout title="Referral requests">
        <PageHeader title="Referral requests" description="Requests for referrals on postings you shared, and the ones you’ve sent.">
            <AppButton variant="ghost" :href="route('jobs.index')">← Jobs</AppButton>
        </PageHeader>

        <nav class="mb-6 flex gap-2" aria-label="Direction">
            <Link
                v-for="t in ['received', 'sent']"
                :key="t"
                :href="route('jobs.referrals', { tab: t })"
                :class="['rounded-full px-3 py-1.5 text-sm font-medium capitalize', tab === t ? 'bg-brand-600 text-white shadow-sm' : 'bg-surface text-ink-soft ring-1 ring-line-strong']"
                >{{ t }}</Link
            >
        </nav>

        <EmptyState v-if="requests.data.length === 0" title="Nothing here yet" />
        <ul v-else class="space-y-3">
            <li v-for="r in requests.data" :key="r.id" class="card p-5">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <Link :href="route('jobs.show', r.job.id)" class="font-medium text-brand-800 hover:underline">{{ r.job.title }} · {{ r.job.organization }}</Link>
                    <StatusBadge :status="badge(r.status)" :label="r.status" />
                </div>
                <div class="mt-3">
                    <PersonCard v-if="tab === 'received'" :name="r.requester.name" :subtitle="r.requester.subtitle" :profile-id="r.requester.profile_id" />
                    <p v-else class="text-sm text-muted">To {{ r.job.poster }} · {{ r.at }}</p>
                </div>
                <p class="mt-3 text-sm whitespace-pre-line text-ink-soft">{{ r.message }}</p>
                <a v-if="r.profile_url" :href="r.profile_url" target="_blank" rel="noopener noreferrer nofollow" class="mt-2 inline-block text-sm text-brand-700 hover:underline">Profile / résumé ↗</a>
                <p v-if="r.response_note" class="mt-2 text-sm text-muted">Reply: “{{ r.response_note }}”</p>
                <div v-if="tab === 'received' && r.status === 'pending'" class="mt-4 flex gap-2">
                    <AppButton size="sm" @click="open(r, 'accept')">I’ll refer</AppButton>
                    <AppButton size="sm" variant="secondary" @click="open(r, 'decline')">Decline</AppButton>
                </div>
            </li>
        </ul>
        <PaginationNav class="mt-4" :links="requests.links" :from="requests.from" :to="requests.to" :total="requests.total" />

        <ModalDialog :show="responding !== null" :title="responding?.decision === 'accept' ? 'Agree to refer' : 'Decline request'" @close="responding = null">
            <form id="respond-form" @submit.prevent="submit">
                <FormField label="Note to the candidate (optional)" :error="form.errors.note" :hint="responding?.decision === 'accept' ? 'e.g. next steps or what you need from them.' : undefined">
                    <TextArea v-model="form.note" rows="3" maxlength="500" />
                </FormField>
            </form>
            <template #footer>
                <AppButton variant="secondary" @click="responding = null">Cancel</AppButton>
                <AppButton type="submit" form="respond-form" :loading="form.processing">Send</AppButton>
            </template>
        </ModalDialog>
    </AppLayout>
</template>
