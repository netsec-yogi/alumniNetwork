<script setup lang="ts">
import { ask } from '@/lib/confirm';
import AppButton from '@/Components/AppButton.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';

const props = defineProps<{
    opportunity: { id: number; title: string; category: string; dates: string | null; status: string; slots: number | null; taken: number | null };
    signups: { id: number; name: string; email: string; status: string; motivation: string | null; hours: number | null; outcome: string | null }[];
}>();
const review = (id: number, decision: 'approve' | 'reject') => router.post(route('volunteering.review', id), { decision }, { preserveScroll: true });
const close = () => ask('Close to new sign-ups?').then((ok) => ok && router.post(route('volunteering.close', props.opportunity.id)));
const badge = (s: string) => ({ completed: 'verified', hours_submitted: 'pending', rejected: 'rejected', withdrawn: 'deactivated' })[s] ?? 'active';
</script>

<template>
    <AppLayout :title="opportunity.title">
        <PageHeader :title="opportunity.title" :description="`${opportunity.category}${opportunity.dates ? ' · ' + opportunity.dates : ''} · ${opportunity.taken ?? 0}${opportunity.slots ? '/' + opportunity.slots : ''} volunteers`">
            <AppButton variant="ghost" :href="route('volunteering.index')">← Volunteering</AppButton>
            <AppButton v-if="opportunity.status === 'open'" variant="secondary" @click="close">Close sign-ups</AppButton>
        </PageHeader>
        <EmptyState v-if="signups.length === 0" title="No volunteers yet" />
        <ul v-else class="space-y-3">
            <li v-for="s in signups" :key="s.id" class="card p-5">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <p class="font-medium text-ink">{{ s.name }}</p>
                        <a :href="`mailto:${s.email}`" class="text-sm text-brand-700">{{ s.email }}</a>
                    </div>
                    <StatusBadge :status="badge(s.status)" :label="s.status.replace('_', ' ')" />
                </div>
                <p v-if="s.motivation" class="mt-2 text-sm text-muted">“{{ s.motivation }}”</p>
                <div v-if="s.status === 'hours_submitted' || s.status === 'completed'" class="mt-3 rounded-lg bg-surface-muted p-3 text-sm">
                    <p class="font-medium">{{ s.hours }} hours</p>
                    <p class="mt-1 whitespace-pre-line text-ink-soft">{{ s.outcome }}</p>
                    <div v-if="s.status === 'hours_submitted'" class="mt-3 flex gap-2">
                        <AppButton size="sm" @click="review(s.id, 'approve')">Approve hours</AppButton>
                        <AppButton size="sm" variant="secondary" @click="review(s.id, 'reject')">Reject</AppButton>
                    </div>
                </div>
            </li>
        </ul>
    </AppLayout>
</template>
