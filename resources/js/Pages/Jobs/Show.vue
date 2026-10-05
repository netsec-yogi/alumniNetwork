<script setup lang="ts">
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
const close = () => confirm('Close this posting? It will no longer be listed.') && router.post(route('jobs.close', props.job.id));
</script>

<template>
    <AppLayout :title="job.title">
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
                <h1 class="mt-1 text-2xl font-semibold text-slate-900">{{ job.title }}</h1>
                <p class="text-slate-600">{{ job.organization }}<template v-if="job.location"> · {{ job.location }}</template></p>
                <div v-if="job.skills.length" class="mt-4 flex flex-wrap gap-1.5">
                    <span v-for="s in job.skills" :key="s" class="rounded bg-slate-100 px-2 py-0.5 text-sm text-slate-700">{{ s }}</span>
                </div>
                <div class="mt-6 text-sm leading-relaxed whitespace-pre-line text-slate-700">{{ job.description }}</div>
                <div class="mt-6 flex items-center justify-between border-t border-slate-100 pt-4">
                    <ShareToFeed v-if="job.is_live" type="job_posting" :id="job.id" :title="job.title" />
                    <ReportButton type="job_posting" :id="job.id" :reasons="reportReasons" label="Report this posting" />
                </div>
            </CardPanel>

            <aside class="space-y-4">
                <CardPanel>
                    <dl class="space-y-3 text-sm">
                        <div><dt class="text-slate-500">Work mode</dt><dd class="font-medium">{{ job.work_mode }} · {{ job.employment_type }}</dd></div>
                        <div v-if="job.experience"><dt class="text-slate-500">Experience</dt><dd class="font-medium">{{ job.experience }}</dd></div>
                        <div v-if="job.compensation"><dt class="text-slate-500">Compensation</dt><dd class="font-medium">{{ job.compensation }}</dd></div>
                        <div v-if="job.deadline"><dt class="text-slate-500">Apply by</dt><dd class="font-medium">{{ job.deadline }}</dd></div>
                        <div>
                            <dt class="text-slate-500">Posted by</dt>
                            <dd class="font-medium">
                                <Link v-if="job.poster_profile_id" :href="route('alumni.show', job.poster_profile_id)" class="text-brand-700 hover:underline">{{ job.posted_by }}</Link>
                                <template v-else>{{ job.posted_by }}</template>
                                · {{ job.posted_at }}
                            </dd>
                        </div>
                    </dl>
                    <div v-if="job.is_live" class="mt-5 space-y-2">
                        <a v-if="job.apply_url" :href="job.apply_url" target="_blank" rel="noopener noreferrer nofollow" class="block rounded-lg bg-brand-800 px-4 py-2 text-center text-sm font-medium text-white hover:bg-brand-700">Apply ↗</a>
                        <a v-else-if="job.apply_email" :href="`mailto:${job.apply_email}`" class="block rounded-lg bg-brand-800 px-4 py-2 text-center text-sm font-medium text-white hover:bg-brand-700">Apply by email</a>
                    </div>
                </CardPanel>

                <CardPanel v-if="job.referral_available && (can.requestReferral || myReferral)" title="Ask for a referral">
                    <template v-if="myReferral">
                        <p class="text-sm">Your request is <strong>{{ myReferral.status }}</strong>.</p>
                        <p v-if="myReferral.response_note" class="mt-1 text-sm text-slate-600">“{{ myReferral.response_note }}”</p>
                    </template>
                    <template v-else>
                        <p class="text-sm text-slate-600">{{ job.posted_by }} has offered to refer suitable candidates. Tell them why you’re a fit.</p>
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
