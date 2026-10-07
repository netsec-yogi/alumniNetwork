<script setup lang="ts">
import AppButton from '@/Components/AppButton.vue';
import CardPanel from '@/Components/CardPanel.vue';
import CheckboxInput from '@/Components/CheckboxInput.vue';
import FormField from '@/Components/FormField.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import PageHeader from '@/Components/PageHeader.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TagInput from '@/Components/TagInput.vue';
import TextArea from '@/Components/TextArea.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Option } from '@/types';
import { useForm } from '@inertiajs/vue3';

interface Q { type: string; prompt: string; options: string[] | null; required: boolean }

const props = defineProps<{
    survey: { slug: string; title: string; description: string | null; event_id: number | null; is_anonymous: boolean; audience: Record<string, any>; closes_at: string | null; questions: Q[] } | null;
    types: Option[];
    events: Option<number>[];
    programmes: Option<number>[];
}>();
const s = props.survey;
const form = useForm({
    title: s?.title ?? '',
    description: s?.description ?? '',
    target: s?.event_id ? 'event' : 'audience',
    event_id: s?.event_id ?? ('' as number | ''),
    audience: { roles: s?.audience.roles ?? ['alumni'], programmes: s?.audience.programmes ?? [], graduation_from: s?.audience.graduation_from ?? '', graduation_to: s?.audience.graduation_to ?? '' },
    is_anonymous: s?.is_anonymous ?? true,
    closes_at: s?.closes_at ?? '',
    questions: (s?.questions ?? [{ type: 'nps', prompt: 'How likely are you to recommend IIITM alumni events to a batchmate?', options: null, required: true }]).map((q) => ({ ...q, options: q.options ?? [] })),
});
const roles = [{ value: 'alumni', label: 'Alumni' }, { value: 'student', label: 'Students' }, { value: 'faculty', label: 'Faculty' }];
const add = () => form.questions.push({ type: 'single', prompt: '', options: [], required: true });
const remove = (i: number) => form.questions.splice(i, 1);
const move = (i: number, d: number) => form.questions.splice(i + d, 0, form.questions.splice(i, 1)[0]);
const err = (k: string) => (form.errors as Record<string, string>)[k];

function save() {
    form.transform((d) => ({
        title: d.title, description: d.description || null, is_anonymous: d.is_anonymous, closes_at: d.closes_at || null,
        event_id: d.target === 'event' ? d.event_id || null : null,
        audience: d.target === 'audience' ? Object.fromEntries(Object.entries(d.audience).filter(([, v]) => (Array.isArray(v) ? v.length : v !== ''))) : null,
        questions: d.questions,
    }));
    s ? form.put(route('admin.surveys.update', s.slug)) : form.post(route('admin.surveys.store'));
}
</script>

<template>
    <AppLayout :title="survey ? 'Edit survey' : 'New survey'">
        <PageHeader :title="survey ? 'Edit survey' : 'New survey'" description="Saved as a draft; publishing invites the audience in-app.">
            <AppButton variant="ghost" :href="route('admin.surveys.index')">Cancel</AppButton>
        </PageHeader>
        <form class="space-y-6" @submit.prevent="save">
            <CardPanel>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="sm:col-span-2"><FormField label="Title" :error="form.errors.title" required><TextInput v-model="form.title" maxlength="200" /></FormField></div>
                    <div class="sm:col-span-2"><FormField label="Introduction" :error="form.errors.description"><TextArea v-model="form.description" rows="2" /></FormField></div>
                    <FormField label="Who answers"><SelectInput v-model="form.target" :options="[{ value: 'audience', label: 'An audience segment' }, { value: 'event', label: 'Attendees of an event' }]" /></FormField>
                    <FormField label="Closes" :error="form.errors.closes_at"><TextInput v-model="form.closes_at" type="datetime-local" /></FormField>
                    <FormField v-if="form.target === 'event'" label="Event" :error="form.errors.event_id"><SelectInput v-model="form.event_id" :options="events" placeholder="Choose" /></FormField>
                    <template v-else>
                        <FormField label="Roles"><MultiSelect v-model="form.audience.roles" :options="roles" /></FormField>
                        <FormField label="Programmes (optional)"><MultiSelect v-model="form.audience.programmes" :options="programmes" /></FormField>
                        <FormField label="Graduated from"><TextInput v-model.number="form.audience.graduation_from" type="number" /></FormField>
                        <FormField label="Graduated to"><TextInput v-model.number="form.audience.graduation_to" type="number" /></FormField>
                    </template>
                    <div class="sm:col-span-2"><CheckboxInput v-model="form.is_anonymous" label="Anonymous: don’t record who answered what" /></div>
                </div>
            </CardPanel>

            <CardPanel v-for="(q, i) in form.questions" :key="i" :title="`Question ${i + 1}`">
                <template #actions>
                    <button type="button" class="text-sm text-muted disabled:opacity-30" :disabled="i === 0" aria-label="Move up" @click="move(i, -1)">↑</button>
                    <button type="button" class="text-sm text-muted disabled:opacity-30" :disabled="i === form.questions.length - 1" aria-label="Move down" @click="move(i, 1)">↓</button>
                    <button type="button" class="text-sm text-red-700" @click="remove(i)">Remove</button>
                </template>
                <div class="grid gap-4 sm:grid-cols-[12rem_1fr]">
                    <FormField label="Type"><SelectInput v-model="q.type" :options="types" /></FormField>
                    <FormField label="Question" :error="err(`questions.${i}.prompt`)"><TextInput v-model="q.prompt" maxlength="500" /></FormField>
                    <div v-if="q.type === 'single' || q.type === 'multiple'" class="sm:col-span-2">
                        <FormField label="Options" :error="err(`questions.${i}.options`)"><TagInput v-model="q.options!" :max="12" placeholder="Type an option and press Enter" /></FormField>
                    </div>
                    <div class="sm:col-span-2"><CheckboxInput v-model="q.required" label="Required" /></div>
                </div>
            </CardPanel>
            <p v-if="form.errors.questions" class="text-sm text-red-600">{{ form.errors.questions }}</p>
            <div class="flex justify-between">
                <AppButton variant="secondary" :disabled="form.questions.length >= 30" @click="add">Add question</AppButton>
                <AppButton type="submit" :loading="form.processing">Save draft</AppButton>
            </div>
        </form>
    </AppLayout>
</template>
