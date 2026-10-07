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

const props = defineProps<{
    profile: {
        is_accepting: boolean;
        categories: string[];
        expertise: string[] | null;
        bio: string | null;
        preferred_mentee: string;
        max_mentees: number;
        availability: string | null;
        preferred_mode: string;
    } | null;
    categories: Option[];
    modes: Option[];
    menteeTypes: Option[];
}>();

const p = props.profile;
const form = useForm({
    is_accepting: p?.is_accepting ?? true,
    categories: p?.categories ?? ([] as string[]),
    expertise: p?.expertise ?? ([] as string[]),
    bio: p?.bio ?? '',
    preferred_mentee: p?.preferred_mentee ?? 'both',
    max_mentees: p?.max_mentees ?? 3,
    availability: p?.availability ?? '',
    preferred_mode: p?.preferred_mode ?? 'video',
});
</script>

<template>
    <AppLayout title="Mentor profile">
        <PageHeader title="Mentor profile" description="What you can help with, and how much time you have.">
            <AppButton variant="ghost" :href="route('mentoring.index', { tab: 'mentoring' })">Cancel</AppButton>
        </PageHeader>

        <form class="space-y-6" @submit.prevent="form.put(route('mentoring.profile.update'))">
            <CardPanel>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="sm:col-span-2"><CheckboxInput v-model="form.is_accepting" label="I’m accepting new mentees" /></div>
                    <fieldset class="sm:col-span-2">
                        <legend class="text-sm font-medium text-ink-soft">Areas I can help with <span class="text-red-600">*</span></legend>
                        <div class="mt-2 grid gap-2 sm:grid-cols-3">
                            <CheckboxInput v-for="c in categories" :key="c.value" v-model="form.categories" :value="c.value" :label="c.label" />
                        </div>
                        <p v-if="form.errors.categories" class="mt-1 text-sm text-red-600">{{ form.errors.categories }}</p>
                    </fieldset>
                    <div class="sm:col-span-2">
                        <FormField label="Expertise" :error="form.errors.expertise" hint="Skills and topics students can search for."><TagInput v-model="form.expertise" :max="20" /></FormField>
                    </div>
                    <div class="sm:col-span-2">
                        <FormField label="About you as a mentor" :error="form.errors.bio"><TextArea v-model="form.bio" rows="4" maxlength="2000" /></FormField>
                    </div>
                    <FormField label="I’d like to mentor" :error="form.errors.preferred_mentee"><SelectInput v-model="form.preferred_mentee" :options="menteeTypes" /></FormField>
                    <FormField label="Maximum mentees at a time" :error="form.errors.max_mentees"><TextInput v-model.number="form.max_mentees" type="number" min="1" max="20" /></FormField>
                    <FormField label="Availability" :error="form.errors.availability" hint="e.g. Two evenings a month"><TextInput v-model="form.availability" maxlength="255" /></FormField>
                    <FormField label="Preferred way to meet" :error="form.errors.preferred_mode"><SelectInput v-model="form.preferred_mode" :options="modes" /></FormField>
                </div>
            </CardPanel>
            <div class="flex justify-end"><AppButton type="submit" :loading="form.processing">Save</AppButton></div>
        </form>
    </AppLayout>
</template>
