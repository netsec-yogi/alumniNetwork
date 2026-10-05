<script setup lang="ts">
import AppButton from '@/Components/AppButton.vue';
import CardPanel from '@/Components/CardPanel.vue';
import CheckboxInput from '@/Components/CheckboxInput.vue';
import FormField from '@/Components/FormField.vue';
import PageHeader from '@/Components/PageHeader.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TagInput from '@/Components/TagInput.vue';
import TextArea from '@/Components/TextArea.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Option } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

interface JobInput {
    id: number;
    status: string;
    type: string;
    title: string;
    organization: string;
    location: string | null;
    work_mode: string;
    employment_type: string;
    experience_min: number | null;
    experience_max: number | null;
    skills: string[] | null;
    compensation: string | null;
    description: string;
    apply_url: string | null;
    apply_email: string | null;
    deadline: string | null;
    referral_available: boolean;
}

const props = defineProps<{ job: JobInput | null; options: { types: Option[]; workModes: Option[]; employmentTypes: Option[] } }>();
const j = props.job;

const form = useForm({
    type: j?.type ?? 'job',
    title: j?.title ?? '',
    organization: j?.organization ?? '',
    location: j?.location ?? '',
    work_mode: j?.work_mode ?? 'onsite',
    employment_type: j?.employment_type ?? 'full_time',
    experience_min: j?.experience_min ?? ('' as number | ''),
    experience_max: j?.experience_max ?? ('' as number | ''),
    skills: j?.skills ?? [],
    compensation: j?.compensation ?? '',
    description: j?.description ?? '',
    apply_url: j?.apply_url ?? '',
    apply_email: j?.apply_email ?? '',
    deadline: j?.deadline ?? '',
    referral_available: j?.referral_available ?? false,
});

watch(
    () => form.type,
    (t) => {
        if (t === 'internship') form.employment_type = 'internship';
    },
);

function submit() {
    form.transform((d) => Object.fromEntries(Object.entries(d).map(([k, v]) => [k, v === '' ? null : v])));
    if (j) form.put(route('jobs.update', j.id));
    else form.post(route('jobs.store'));
}
</script>

<template>
    <AppLayout :title="job ? 'Edit posting' : 'Post an opportunity'">
        <PageHeader :title="job ? 'Edit posting' : 'Post an opportunity'" description="Postings from members are reviewed by the career office before they’re listed.">
            <AppButton variant="ghost" :href="job ? route('jobs.show', job.id) : route('jobs.index')">Cancel</AppButton>
        </PageHeader>

        <form class="space-y-6" novalidate @submit.prevent="submit">
            <CardPanel title="The role">
                <div class="grid gap-5 sm:grid-cols-2">
                    <FormField label="Type" :error="form.errors.type" required><SelectInput v-model="form.type" :options="options.types" /></FormField>
                    <FormField label="Employment type" :error="form.errors.employment_type" required><SelectInput v-model="form.employment_type" :options="options.employmentTypes" /></FormField>
                    <FormField label="Title" :error="form.errors.title" required><TextInput v-model="form.title" required maxlength="160" /></FormField>
                    <FormField label="Organisation" :error="form.errors.organization" required><TextInput v-model="form.organization" required maxlength="160" /></FormField>
                    <FormField label="Location" :error="form.errors.location"><TextInput v-model="form.location" placeholder="e.g. Bengaluru" /></FormField>
                    <FormField label="Work mode" :error="form.errors.work_mode" required><SelectInput v-model="form.work_mode" :options="options.workModes" /></FormField>
                    <FormField label="Min. experience (years)" :error="form.errors.experience_min"><TextInput v-model.number="form.experience_min" type="number" min="0" /></FormField>
                    <FormField label="Max. experience (years)" :error="form.errors.experience_max"><TextInput v-model.number="form.experience_max" type="number" min="0" /></FormField>
                    <FormField label="Compensation" :error="form.errors.compensation" hint="Optional, e.g. ₹18–24 LPA or ₹40k/month stipend"><TextInput v-model="form.compensation" maxlength="100" /></FormField>
                    <FormField label="Skills" :error="form.errors.skills"><TagInput v-model="form.skills" /></FormField>
                    <div class="sm:col-span-2">
                        <FormField label="Description" :error="form.errors.description" required><TextArea v-model="form.description" rows="10" /></FormField>
                    </div>
                </div>
            </CardPanel>

            <CardPanel title="How to apply">
                <div class="grid gap-5 sm:grid-cols-2">
                    <FormField label="Application link" :error="form.errors.apply_url"><TextInput v-model="form.apply_url" type="url" placeholder="https://" /></FormField>
                    <FormField label="…or email" :error="form.errors.apply_email"><TextInput v-model="form.apply_email" type="email" /></FormField>
                    <FormField label="Apply by" :error="form.errors.deadline"><TextInput v-model="form.deadline" type="date" /></FormField>
                    <div class="flex items-end pb-2">
                        <CheckboxInput v-model="form.referral_available" label="I can refer suitable IIITM candidates" />
                    </div>
                </div>
            </CardPanel>

            <div class="flex justify-end"><AppButton type="submit" :loading="form.processing">{{ job ? 'Save' : 'Submit for review' }}</AppButton></div>
        </form>
    </AppLayout>
</template>
