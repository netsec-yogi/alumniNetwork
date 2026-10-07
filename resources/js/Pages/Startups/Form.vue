<script setup lang="ts">
import FileUpload from '@/Components/FileUpload.vue';
import AppButton from '@/Components/AppButton.vue';
import CardPanel from '@/Components/CardPanel.vue';
import CheckboxInput from '@/Components/CheckboxInput.vue';
import FormField from '@/Components/FormField.vue';
import PageHeader from '@/Components/PageHeader.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextArea from '@/Components/TextArea.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Option } from '@/types';
import { useForm } from '@inertiajs/vue3';

const props = defineProps<{ startup: Record<string, any> | null; stages: Option[] }>();
const s = props.startup;
const form = useForm({
    name: s?.name ?? '',
    tagline: s?.tagline ?? '',
    description: s?.description ?? '',
    industry: s?.industry ?? '',
    website_url: s?.website_url ?? '',
    location: s?.location ?? '',
    founded_year: s?.founded_year ?? ('' as number | ''),
    funding_stage: s?.funding_stage ?? 'bootstrapped',
    is_hiring: s?.is_hiring ?? false,
    my_role: s?.my_role ?? '',
    cofounders: s?.cofounders ?? '',
    logo: null as File | null,
});
const submit = () =>
    form
        .transform((d) => Object.fromEntries(Object.entries(d).map(([k, v]) => [k, v === '' ? null : v])))
        .post(s ? route('startups.update', s.slug) : route('startups.store'), { forceFormData: true });
</script>

<template>
    <AppLayout :title="startup ? 'Edit startup' : 'Add your startup'">
        <PageHeader :title="startup ? `Edit ${startup.name}` : 'Add your startup'">
            <AppButton variant="ghost" :href="startup ? route('startups.show', startup.slug) : route('startups.index')">Cancel</AppButton>
        </PageHeader>
        <form class="space-y-6" @submit.prevent="submit">
            <CardPanel>
                <div class="grid gap-5 sm:grid-cols-2">
                    <FormField label="Name" :error="form.errors.name" required><TextInput v-model="form.name" maxlength="140" /></FormField>
                    <FormField label="Industry" :error="form.errors.industry" required><TextInput v-model="form.industry" placeholder="e.g. Fintech" /></FormField>
                    <div class="sm:col-span-2"><FormField label="Tagline" :error="form.errors.tagline"><TextInput v-model="form.tagline" maxlength="200" /></FormField></div>
                    <div class="sm:col-span-2"><FormField label="What you do" :error="form.errors.description" required><TextArea v-model="form.description" rows="6" /></FormField></div>
                    <FormField label="Website" :error="form.errors.website_url"><TextInput v-model="form.website_url" type="url" placeholder="https://" /></FormField>
                    <FormField label="Location" :error="form.errors.location"><TextInput v-model="form.location" /></FormField>
                    <FormField label="Founded" :error="form.errors.founded_year"><TextInput v-model.number="form.founded_year" type="number" /></FormField>
                    <FormField label="Stage" :error="form.errors.funding_stage"><SelectInput v-model="form.funding_stage" :options="stages" /></FormField>
                    <FormField label="Your role" :error="form.errors.my_role"><TextInput v-model="form.my_role" placeholder="e.g. CEO" /></FormField>
                    <FormField label="IIITM co-founders (roll numbers)" :error="form.errors.cofounders" hint="Comma-separated; they must be verified alumni."><TextInput v-model="form.cofounders" /></FormField>
                    <FormField label="Logo" :error="form.errors.logo"><FileUpload v-model="form.logo" accept="image/jpeg,image/png,image/webp" :max-mb="10" hint="JPEG, PNG or WebP, up to 10 MB" :progress="form.progress?.percentage ?? null" /></FormField>
                    <div class="flex items-end pb-2"><CheckboxInput v-model="form.is_hiring" label="We’re hiring" /></div>
                </div>
            </CardPanel>
            <div class="flex justify-end"><AppButton type="submit" :loading="form.processing">Save</AppButton></div>
        </form>
    </AppLayout>
</template>
