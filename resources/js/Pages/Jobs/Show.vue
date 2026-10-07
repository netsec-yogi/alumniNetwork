<script setup lang="ts">
import { ask as confirmAction } from '@/lib/confirm';
import AutoBreadcrumbs from '@/Components/AutoBreadcrumbs.vue';
import { ExternalLink } from 'lucide-vue-next';
import AlertBox from '@/Components/AlertBox.vue';
import AppButton from '@/Components/AppButton.vue';
import CardPanel from '@/Components/CardPanel.vue';
import FormField from '@/Components/FormField.vue';
import type { JobSummary } from '@/Components/JobCard.vue';
import ModalDialog from '@/Components/ModalDialog.vue';
import ReportButton from '@/Components/ReportButton.vue';
import ShareToFeed from '@/Components/ShareToFeed.vue';
import TextArea from '@/Components/TextArea.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    job: JobSummary & { description: string; apply_url: string | null; apply_email: string | null; rejection_reason: string | null; poster_profile_id: number | null };
    myReferral: { status: string; response_note: string | null } | null;
    can: { update: boolean; close: boolean; requestReferral: boolean };
    reportReasons: Record<string, string>;
}>();

const asking = ref(false);
const form = useForm({ message: '', profile_url: '' });
const ask = () => form.post(route('jobs.referrals.store', props.job.id), { preserveScroll: true, onSuccess: () => (asking.value = false) });
const close = () => confirmAction('Close this posting? It will no longer be listed.').then((ok) => ok && router.post(route('jobs.close', props.job.id)));
</script>

<template>
    <AppLayout :title="job.title">
        <AutoBreadcrumbs :title="job.title" class="mb-4" />
        <div class="mb-6 flex flex-wrap items-center justify-between gap-2">
            <AppButton variant="ghost" class="-ml-3" :href="route('jobs.index')">← All openings</AppButton>
            <div class="flex gap-2">
                <AppButton v-if="can.update" variant="secondary" :href="route('jobs.edit', job.id)">Edit</AppButton>
                <AppButton v-if="can.close" variant="secondary" @click="close">Close posting</AppButton>
            </div>
        </div>

        <AlertBox v-if="job.status === 'pending'" tone="warning" class="mb-6" title="Awaiting review">A career administrator will review this posting before it is listed.</AlertBox>
        <AlertBox v-else-if="job.status === 'rejected'" tone="danger" class="mb-6" title="Not approved">{{ job.rejection_reason }}</AlertBox>
        <AlertBox v-else-if="!job.is_live" tone="info" class="mb-6">This posting is closed.</AlertBox>

        <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
            <CardPanel>
                <p class="text-sm font-medium tracking-wide text-accent-600 uppercase">{{ job.type === 'internship' ? 'Internship' : 'Job' }}</p>
                <h1 class="mt-1 text-2xl font-semibold text-ink">{{ job.title }}</h1>
                <p class="text-muted">{{ job.organization }}<template v-if="job.location"> · {{ job.location }}</template></p>
                <div v-if="job.skills.length" class="mt-4 flex flex-wrap gap-1.5">
                    <span v-for="s in job.skills" :key="s" class="rounded bg-surface-sunken px-2 py-0.5 text-sm text-ink-soft">{{ s }}</span>
                </div>
                <div class="mt-6 text-sm leading-relaxed whitespace-pre-line text-ink-soft">{{ job.description }}</div>
                <div class="mt-6 flex items-center justify-between border-t border-line-soft pt-4">
                    <ShareToFeed v-if="job.is_live" type="job_posting" :id="job.id" :title="job.title" />
                    <ReportButton type="job_posting" :id="job.id" :reasons="reportReasons" label="Report this posting" />
                </div>
            </CardPanel>

            <aside class="space-y-4">
                <CardPanel>
                    <dl class="space-y-3 text-sm">
                        <div><dt class="text-muted">Work mode</dt><dd class="font-medium">{{ job.work_mode }} · {{ job.employment_type }}</dd></div>
                        <div v-if="job.experience"><dt class="text-muted">Experience</dt><dd class="font-medium">{{ job.experience }}</dd></div>
                        <div v-if="job.compensation"><dt class="text-muted">Compensation</dt><dd class="font-medium">{{ job.compensation }}</dd></div>
                        <div v-if="job.deadline"><dt class="text-muted">Apply by</dt><dd class="font-medium">{{ job.deadline }}</dd></div>
                        <div>
                            <dt class="text-muted">Posted by</dt>
                            <dd class="font-medium">
                                <Link v-if="job.poster_profile_id" :href="route('alumni.show', job.poster_profile_id)" class="text-brand-700 hover:underline">{{ job.posted_by }}</Link>
                                <template v-else>{{ job.posted_by }}</template>
                                · {{ job.posted_at }}
                            </dd>
                        </div>
                    </dl>
                    <div v-if="job.is_live" class="mt-5 space-y-2">
                        <AppButton v-if="job.apply_url" external :href="job.apply_url" target="_blank" rel="noopener noreferrer nofollow" :icon="ExternalLink" class="w-full">Apply</AppButton>
                        <AppButton v-else-if="job.apply_email" external :href="`mailto:${job.apply_email}`" :icon="ExternalLink" class="w-full">Apply by email</AppButton>
                    </div>
                </CardPanel>

                <CardPanel v-if="job.referral_available && (can.requestReferral || myReferral)" title="Ask for a referral">
                    <template v-if="myReferral">
                        <p class="text-sm">Your request is <strong>{{ myReferral.status }}</strong>.</p>
                        <p v-if="myReferral.response_note" class="mt-1 text-sm text-muted">“{{ myReferral.response_note }}”</p>
                    </template>
                    <template v-else>
                        <p class="text-sm text-muted">{{ job.posted_by }} has offered to refer suitable candidates. Tell them why you’re a fit.</p>
                        <AppButton class="mt-3 w-full" variant="secondary" @click="asking = true">Request referral</AppButton>
                    </template>
                </CardPanel>
            </aside>
        </div>

        <ModalDialog :show="asking" :title="`Referral for ${job.title}`" @close="asking = false">
            <form id="referral-form" class="space-y-4" @submit.prevent="ask">
                <FormField label="Why you’re a good fit" :error="form.errors.message" hint="At least 30 characters. Mention relevant experience and your batch." required>
                    <TextArea v-model="form.message" rows="5" maxlength="2000" />
                </FormField>
                <FormField label="LinkedIn or résumé link" :error="form.errors.profile_url"><TextInput v-model="form.profile_url" type="url" placeholder="https://" /></FormField>
            </form>
            <template #footer>
                <AppButton variant="secondary" @click="asking = false">Cancel</AppButton>
                <AppButton type="submit" form="referral-form" :loading="form.processing">Send request</AppButton>
            </template>
        </ModalDialog>
    </AppLayout>
</template>
