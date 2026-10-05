<script setup lang="ts">
import AlertBox from '@/Components/AlertBox.vue';
import AppButton from '@/Components/AppButton.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FormField from '@/Components/FormField.vue';
import ModalDialog from '@/Components/ModalDialog.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PersonCard from '@/Components/PersonCard.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextArea from '@/Components/TextArea.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Row {
    id: number;
    other: { name: string; profile_id: number | null; subtitle: string };
    category: string;
    goals: string;
    status: string;
    mentor_note: string | null;
    match_score: number | null;
    since: string;
    contact: string | null;
}

defineProps<{
    tab: 'mentee' | 'mentoring';
    asMentee: Row[];
    asMentor: Row[];
    mentorProfile: { is_accepting: boolean; max_mentees: number } | null;
    activeMentees: number;
    canMentor: boolean;
}>();

const responding = ref<{ row: Row; decision: 'accept' | 'decline' } | null>(null);
const form = useForm({ decision: 'accept', note: '' });
const opts = { preserveScroll: true };
function open(row: Row, decision: 'accept' | 'decline') {
    responding.value = { row, decision };
    form.decision = decision;
}
const submit = () => form.post(route('mentoring.respond', responding.value!.row.id), { ...opts, onSuccess: () => ((responding.value = null), form.reset()) });
const complete = (r: Row) => confirm('Mark this mentorship as complete?') && router.post(route('mentoring.complete', r.id), {}, opts);
const cancel = (r: Row) => router.post(route('mentoring.cancel', r.id), {}, opts);
const badge = (s: string) => ({ accepted: 'active', completed: 'verified', declined: 'rejected', cancelled: 'deactivated' })[s] ?? 'pending';
</script>

<template>
    <AppLayout title="Mentoring">
        <PageHeader title="Mentoring" description="Learn from alumni who’ve walked the path — or guide those coming after you.">
            <AppButton :href="route('mentoring.find')">Find a mentor</AppButton>
        </PageHeader>

        <nav class="mb-6 flex gap-2" aria-label="Role">
            <Link
                :href="route('mentoring.index', { tab: 'mentee' })"
                :class="['rounded-full px-3 py-1.5 text-sm font-medium', tab === 'mentee' ? 'bg-brand-800 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-300']"
                >My mentors</Link
            >
            <Link
                v-if="canMentor"
                :href="route('mentoring.index', { tab: 'mentoring' })"
                :class="['rounded-full px-3 py-1.5 text-sm font-medium', tab === 'mentoring' ? 'bg-brand-800 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-300']"
                >I mentor</Link
            >
        </nav>

        <template v-if="tab === 'mentee'">
            <EmptyState v-if="asMentee.length === 0" title="No mentors yet" description="Tell us what you’re looking for and we’ll suggest alumni who fit.">
                <AppButton :href="route('mentoring.find')">Find a mentor</AppButton>
            </EmptyState>
            <ul v-else class="space-y-3">
                <li v-for="r in asMentee" :key="r.id" class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                    <PersonCard :name="r.other.name" :subtitle="`${r.category} · ${r.since}`" :profile-id="r.other.profile_id">
                        <p v-if="r.mentor_note" class="mt-2 text-sm text-slate-600">“{{ r.mentor_note }}”</p>
                        <p v-if="r.contact" class="mt-2 text-sm">Reach them at <a :href="`mailto:${r.contact}`" class="font-medium text-brand-700">{{ r.contact }}</a></p>
                        <template #actions>
                            <StatusBadge :status="badge(r.status)" :label="r.status" />
                            <AppButton v-if="r.status === 'pending'" size="sm" variant="ghost" @click="cancel(r)">Withdraw</AppButton>
                            <AppButton v-if="r.status === 'accepted'" size="sm" variant="secondary" @click="complete(r)">Mark complete</AppButton>
                        </template>
                    </PersonCard>
                </li>
            </ul>
        </template>

        <template v-else>
            <AlertBox v-if="!mentorProfile" tone="info" title="Become a mentor" class="mb-6">
                Set up a mentor profile so students and younger alumni can find you.
                <div class="mt-2"><AppButton size="sm" :href="route('mentoring.profile')">Set up mentor profile</AppButton></div>
            </AlertBox>
            <div v-else class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <p class="text-sm text-slate-700">
                    <StatusBadge :status="mentorProfile.is_accepting ? 'active' : 'deactivated'" :label="mentorProfile.is_accepting ? 'Accepting mentees' : 'Not accepting'" />
                    <span class="ml-2">{{ activeMentees }} of {{ mentorProfile.max_mentees }} slots in use</span>
                </p>
                <AppButton size="sm" variant="secondary" :href="route('mentoring.profile')">Edit mentor profile</AppButton>
            </div>

            <EmptyState v-if="asMentor.length === 0" title="No requests yet" />
            <ul v-else class="space-y-3">
                <li v-for="r in asMentor" :key="r.id" class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                    <PersonCard :name="r.other.name" :subtitle="`${r.other.subtitle} · ${r.category}`" :profile-id="r.other.profile_id">
                        <p class="mt-2 text-sm whitespace-pre-line text-slate-700">{{ r.goals }}</p>
                        <p v-if="r.match_score !== null" class="mt-1 text-xs text-slate-400">Match score {{ r.match_score }}/100 · {{ r.since }}</p>
                        <p v-if="r.contact" class="mt-2 text-sm">Reach them at <a :href="`mailto:${r.contact}`" class="font-medium text-brand-700">{{ r.contact }}</a></p>
                        <template #actions>
                            <StatusBadge :status="badge(r.status)" :label="r.status" />
                            <template v-if="r.status === 'pending'">
                                <AppButton size="sm" @click="open(r, 'accept')">Accept</AppButton>
                                <AppButton size="sm" variant="secondary" @click="open(r, 'decline')">Decline</AppButton>
                            </template>
                            <AppButton v-if="r.status === 'accepted'" size="sm" variant="secondary" @click="complete(r)">Mark complete</AppButton>
                        </template>
                    </PersonCard>
                </li>
            </ul>
        </template>

        <ModalDialog :show="responding !== null" :title="responding?.decision === 'accept' ? `Mentor ${responding?.row.other.name}` : 'Decline request'" @close="responding = null">
            <form id="mentor-respond" @submit.prevent="submit">
                <p v-if="responding?.decision === 'accept'" class="mb-3 text-sm text-slate-600">Accepting shares your email addresses with each other so you can arrange a first conversation.</p>
                <FormField label="Note (optional)" :error="form.errors.note"><TextArea v-model="form.note" rows="3" maxlength="500" /></FormField>
            </form>
            <template #footer>
                <AppButton variant="secondary" @click="responding = null">Cancel</AppButton>
                <AppButton type="submit" form="mentor-respond" :loading="form.processing">{{ responding?.decision === 'accept' ? 'Accept' : 'Decline' }}</AppButton>
            </template>
        </ModalDialog>
    </AppLayout>
</template>
