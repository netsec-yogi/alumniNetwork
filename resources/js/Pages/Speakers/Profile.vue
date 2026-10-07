<script setup lang="ts">
import AppButton from '@/Components/AppButton.vue';
import CardPanel from '@/Components/CardPanel.vue';
import CheckboxInput from '@/Components/CheckboxInput.vue';
import FormField from '@/Components/FormField.vue';
import PageHeader from '@/Components/PageHeader.vue';
import TagInput from '@/Components/TagInput.vue';
import TextArea from '@/Components/TextArea.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Option } from '@/types';
import { useForm } from '@inertiajs/vue3';

const props = defineProps<{ profile: { is_available: boolean; topics: string[]; formats: string[]; bio: string | null; languages: string | null; remote: boolean; in_person: boolean } | null; formats: Option[] }>();
const p = props.profile;
const form = useForm({
    is_available: p?.is_available ?? true,
    topics: p?.topics ?? ([] as string[]),
    formats: p?.formats ?? (['talk'] as string[]),
    bio: p?.bio ?? '',
    languages: p?.languages ?? 'English, Hindi',
    remote: p?.remote ?? true,
    in_person: p?.in_person ?? false,
});
</script>

<template>
    <AppLayout title="Speaker profile">
        <PageHeader title="Speaker profile" description="Event organisers, faculty and chapters search these.">
            <AppButton variant="ghost" :href="route('speakers.index')">Cancel</AppButton>
        </PageHeader>
        <form class="space-y-6" @submit.prevent="form.put(route('speakers.profile.update'))">
            <CardPanel>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="sm:col-span-2"><CheckboxInput v-model="form.is_available" label="I’m available for speaking invitations" /></div>
                    <div class="sm:col-span-2"><FormField label="Topics" :error="form.errors.topics" required><TagInput v-model="form.topics" placeholder="e.g. Product management, LLMs" /></FormField></div>
                    <fieldset class="sm:col-span-2">
                        <legend class="text-sm font-medium text-ink-soft">Formats</legend>
                        <div class="mt-2 flex flex-wrap gap-4"><CheckboxInput v-for="f in formats" :key="f.value" v-model="form.formats" :value="f.value" :label="f.label" /></div>
                        <p v-if="form.errors.formats" class="mt-1 text-sm text-red-600">{{ form.errors.formats }}</p>
                    </fieldset>
                    <div class="sm:col-span-2"><FormField label="Speaker bio" :error="form.errors.bio"><TextArea v-model="form.bio" rows="4" maxlength="2000" /></FormField></div>
                    <FormField label="Languages" :error="form.errors.languages"><TextInput v-model="form.languages" /></FormField>
                    <div class="flex items-end gap-6 pb-2">
                        <CheckboxInput v-model="form.remote" label="Remote" />
                        <CheckboxInput v-model="form.in_person" label="In person" />
                    </div>
                </div>
            </CardPanel>
            <div class="flex justify-end"><AppButton type="submit" :loading="form.processing">Save</AppButton></div>
        </form>
    </AppLayout>
</template>
