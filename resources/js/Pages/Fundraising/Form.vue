<script setup lang="ts">
import FileUpload from '@/Components/FileUpload.vue';
import AppButton from '@/Components/AppButton.vue';
import CardPanel from '@/Components/CardPanel.vue';
import FormField from '@/Components/FormField.vue';
import PageHeader from '@/Components/PageHeader.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextArea from '@/Components/TextArea.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Option } from '@/types';
import { useForm } from '@inertiajs/vue3';

const props = defineProps<{ campaign: Record<string, any> | null; types: Option[]; categories: Option[]; chapters: Option<number>[]; isStaff: boolean }>();
const c = props.campaign;
const form = useForm({
    type: c?.type ?? props.types[0].value,
    title: c?.title ?? '',
    summary: c?.summary ?? '',
    story: c?.story ?? '',
    category: c?.category ?? 'scholarship',
    goal: c?.goal ?? ('' as number | ''),
    starts_at: c?.starts_at ?? '',
    ends_at: c?.ends_at ?? '',
    community_id: c?.community_id ?? ('' as number | ''),
    matching_sponsor: c?.matching_sponsor ?? '',
    matching_ratio: c?.matching_ratio ?? ('' as number | ''),
    matching_cap: c?.matching_cap ?? ('' as number | ''),
    cover: null as File | null,
    publish: false,
});

function save(publish: boolean) {
    form.publish = publish;
    form.transform((d) => {
        const out: Record<string, unknown> = Object.fromEntries(Object.entries(d).map(([k, v]) => [k, v === '' ? null : v]));
        if (!props.isStaff) ['matching_sponsor', 'matching_ratio', 'matching_cap'].forEach((k) => delete out[k]);
        return out;
    });
    form.post(c ? route('fundraising.update', c.slug) : route('fundraising.store'), { forceFormData: true });
}
</script>

<template>
    <AppLayout :title="campaign ? 'Edit campaign' : 'Start a campaign'">
        <PageHeader :title="campaign ? campaign.title : 'Start a campaign'" :description="isStaff ? 'Staff campaigns publish immediately.' : 'Alumni campaigns are reviewed by the fundraising office before going live. Funds always go to the institute.'">
            <AppButton variant="ghost" :href="route('fundraising.index')">Cancel</AppButton>
        </PageHeader>
        <form class="space-y-6" @submit.prevent="save(true)">
            <CardPanel title="The campaign">
                <div class="grid gap-5 sm:grid-cols-2">
                    <FormField label="Type" :error="form.errors.type"><SelectInput v-model="form.type" :options="types" /></FormField>
                    <FormField label="Funds go to" :error="form.errors.category"><SelectInput v-model="form.category" :options="categories" /></FormField>
                    <div class="sm:col-span-2"><FormField label="Title" :error="form.errors.title" required><TextInput v-model="form.title" maxlength="200" /></FormField></div>
                    <div class="sm:col-span-2"><FormField label="One-line summary" :error="form.errors.summary" required><TextInput v-model="form.summary" maxlength="400" /></FormField></div>
                    <div class="sm:col-span-2">
                        <FormField label="Story" :error="form.errors.story" hint="Why it matters and what the money will do. Markdown supported; HTML is removed." required><TextArea v-model="form.story" rows="12" /></FormField>
                    </div>
                    <FormField label="Goal (₹)" :error="form.errors.goal" required><TextInput v-model.number="form.goal" type="number" min="10000" /></FormField>
                    <FormField label="Chapter (optional)" :error="form.errors.community_id"><SelectInput v-model="form.community_id" :options="chapters" placeholder="—" /></FormField>
                    <FormField label="Starts" :error="form.errors.starts_at" required><TextInput v-model="form.starts_at" type="datetime-local" /></FormField>
                    <FormField label="Ends" :error="form.errors.ends_at" required><TextInput v-model="form.ends_at" type="datetime-local" /></FormField>
                    <FormField label="Cover image" :error="form.errors.cover"><FileUpload v-model="form.cover" accept="image/jpeg,image/png,image/webp" :max-mb="10" hint="JPEG, PNG or WebP, up to 10 MB" :progress="form.progress?.percentage ?? null" /></FormField>
                </div>
            </CardPanel>
            <CardPanel v-if="isStaff" title="Matching gift (optional)" description="A sponsor matches donations at a ratio, up to a cap.">
                <div class="grid gap-5 sm:grid-cols-3">
                    <FormField label="Sponsor" :error="form.errors.matching_sponsor"><TextInput v-model="form.matching_sponsor" /></FormField>
                    <FormField label="Ratio (e.g. 1 = 1:1)" :error="form.errors.matching_ratio"><TextInput v-model.number="form.matching_ratio" type="number" step="0.1" /></FormField>
                    <FormField label="Cap (₹)" :error="form.errors.matching_cap"><TextInput v-model.number="form.matching_cap" type="number" /></FormField>
                </div>
            </CardPanel>
            <div class="flex justify-end gap-2">
                <AppButton variant="secondary" :loading="form.processing && !form.publish" @click="save(false)">Save draft</AppButton>
                <AppButton type="submit" :loading="form.processing && form.publish">{{ isStaff ? 'Publish' : 'Submit for review' }}</AppButton>
            </div>
        </form>
    </AppLayout>
</template>
