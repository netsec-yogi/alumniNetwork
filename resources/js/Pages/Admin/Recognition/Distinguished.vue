<script setup lang="ts">
import { ask } from '@/lib/confirm';
import AppButton from '@/Components/AppButton.vue';
import CheckboxInput from '@/Components/CheckboxInput.vue';
import FormField from '@/Components/FormField.vue';
import ModalDialog from '@/Components/ModalDialog.vue';
import PageHeader from '@/Components/PageHeader.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextArea from '@/Components/TextArea.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Option } from '@/types';
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import ContentNav from './ContentNav.vue';

interface Honouree {
    id: number;
    category: string;
    award_year: number;
    citation: string;
    is_published: boolean;
    name: string;
    batch: string;
}

const props = defineProps<{ honourees: Honouree[]; categories: Option[] }>();
const label = (v: string) => props.categories.find((c) => c.value === v)?.label ?? v;

const editing = ref<Honouree | 'new' | null>(null);
const form = useForm({ roll_number: '', category: '', award_year: new Date().getFullYear(), citation: '', is_published: true });
function open(h: Honouree | 'new') {
    editing.value = h;
    form.clearErrors();
    if (h === 'new') form.reset();
    else Object.assign(form, { roll_number: '', category: h.category, award_year: h.award_year, citation: h.citation, is_published: h.is_published });
}
function save() {
    const opts = { preserveScroll: true, onSuccess: () => (editing.value = null) };
    if (editing.value === 'new') form.post(route('admin.distinguished.store'), opts);
    else form.put(route('admin.distinguished.update', (editing.value as Honouree).id), opts);
}
const remove = (h: Honouree) => ask(`Remove ${h.name}?`).then((ok) => ok && router.delete(route('admin.distinguished.destroy', h.id), { preserveScroll: true }));
</script>

<template>
    <AppLayout title="Distinguished alumni">
        <PageHeader title="Content"><AppButton @click="open('new')">Add honouree</AppButton></PageHeader>
        <ContentNav />

        <ul class="space-y-3">
            <li v-for="h in honourees" :key="h.id" class="card flex flex-wrap items-start justify-between gap-3 p-5">
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-ink">{{ h.name }} <span class="font-normal text-muted">· {{ h.batch }}</span></p>
                    <p class="text-sm text-accent-600">{{ label(h.category) }} · {{ h.award_year }}<span v-if="!h.is_published" class="ml-2 text-muted">(hidden)</span></p>
                    <p class="mt-2 line-clamp-2 text-sm text-ink-soft">{{ h.citation }}</p>
                </div>
                <div class="flex gap-2">
                    <AppButton size="sm" variant="ghost" @click="open(h)">Edit</AppButton>
                    <AppButton size="sm" variant="danger-ghost" @click="remove(h)">Remove</AppButton>
                </div>
            </li>
            <li v-if="honourees.length === 0" class="text-sm text-muted">No honourees yet.</li>
        </ul>

        <ModalDialog :show="editing !== null" :title="editing === 'new' ? 'Add distinguished alumnus' : 'Edit honour'" @close="editing = null">
            <form id="honour-form" class="grid gap-4 sm:grid-cols-2" @submit.prevent="save">
                <div v-if="editing === 'new'" class="sm:col-span-2">
                    <FormField label="Roll number" :error="form.errors.roll_number" hint="Must be a verified alumnus." required><TextInput v-model="form.roll_number" /></FormField>
                </div>
                <FormField label="Category" :error="form.errors.category" required><SelectInput v-model="form.category" :options="categories" placeholder="Choose" /></FormField>
                <FormField label="Year" :error="form.errors.award_year" required><TextInput v-model.number="form.award_year" type="number" /></FormField>
                <div class="sm:col-span-2"><FormField label="Citation" :error="form.errors.citation" required><TextArea v-model="form.citation" rows="5" /></FormField></div>
                <div class="sm:col-span-2"><CheckboxInput v-model="form.is_published" label="Show on the public page" /></div>
            </form>
            <template #footer>
                <AppButton variant="secondary" @click="editing = null">Cancel</AppButton>
                <AppButton type="submit" form="honour-form" :loading="form.processing">Save</AppButton>
            </template>
        </ModalDialog>
    </AppLayout>
</template>
