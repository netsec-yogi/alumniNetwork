<script setup lang="ts">
import ImageGalleryManager, { type GalleryImage } from '@/Components/ImageGalleryManager.vue';
import CheckboxInput from '@/Components/CheckboxInput.vue';
import { ask } from '@/lib/confirm';
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
import { router, useForm } from '@inertiajs/vue3';

interface StoryInput {
    id: number;
    slug: string;
    type: string;
    title: string;
    excerpt: string | null;
    body: string;
    video_url: string | null;
    batch_year: number | null;
    programme_id: number | null;
    industry: string | null;
    location: string | null;
    status: string;
    is_featured: boolean;
    display_order: number;
    roll_number: string | null;
    cover_url: string | null;
}

const props = defineProps<{ story: StoryInput | null; types: Option[]; programmes: Option<number>[]; images: GalleryImage[]; imageLimitKb: number }>();
const s = props.story;

const form = useForm({
    type: s?.type ?? 'article',
    title: s?.title ?? '',
    excerpt: s?.excerpt ?? '',
    body: s?.body ?? '',
    video_url: s?.video_url ?? '',
    roll_number: s?.roll_number ?? '',
    batch_year: s?.batch_year ?? ('' as number | ''),
    programme_id: s?.programme_id ?? ('' as number | ''),
    industry: s?.industry ?? '',
    location: s?.location ?? '',
    cover: null as File | null,
    is_featured: s?.is_featured ?? false,
    display_order: s?.display_order ?? 0,
    publish: s?.status === 'published',
});

function save(publish: boolean) {
    form.publish = publish;
    form.transform((d) => Object.fromEntries(Object.entries(d).map(([k, v]) => [k, v === '' ? null : v])));
    form.post(s ? route('admin.stories.update', s.slug) : route('admin.stories.store'), { forceFormData: true });
}
const destroy = () => ask('Delete this story?').then((ok) => ok && router.delete(route('admin.stories.destroy', s!.slug)));
</script>

<template>
    <AppLayout :title="story ? 'Edit story' : 'New story'">
        <PageHeader :title="story ? story.title : 'New story'" :description="story?.status === 'published' ? 'Published — changes go live when you save.' : 'Draft — only staff can see it.'">
            <AppButton v-if="story?.status === 'published'" variant="ghost" :href="route('stories.show', story.slug)">View</AppButton>
            <AppButton variant="ghost" :href="route('admin.stories.index')">All stories</AppButton>
        </PageHeader>

        <form class="space-y-6" @submit.prevent="save(form.publish)">
            <CardPanel>
                <div class="grid gap-5 sm:grid-cols-2">
                    <FormField label="Format" :error="form.errors.type"><SelectInput v-model="form.type" :options="types" /></FormField>
                    <FormField label="Featured alumnus (roll number)" :error="form.errors.roll_number"><TextInput v-model="form.roll_number" /></FormField>
                    <div class="sm:col-span-2"><FormField label="Title" :error="form.errors.title" required><TextInput v-model="form.title" maxlength="200" /></FormField></div>
                    <div class="sm:col-span-2"><FormField label="Standfirst" :error="form.errors.excerpt" hint="One or two sentences shown in listings."><TextInput v-model="form.excerpt" maxlength="400" /></FormField></div>
                    <div class="sm:col-span-2">
                        <FormField label="Story" :error="form.errors.body" hint="Markdown: ## headings, **bold**, *italic*, [links](https://…), > quotes, - lists. HTML is removed." required>
                            <TextArea v-model="form.body" rows="18" class="font-mono" />
                        </FormField>
                    </div>
                    <FormField label="Cover image" :error="form.errors.cover" :hint="story?.cover_url ? 'Uploading replaces the current cover.' : undefined">
                        <FileUpload v-model="form.cover" accept="image/jpeg,image/png,image/webp" :max-mb="10" hint="JPEG, PNG or WebP, up to 10 MB" :progress="form.progress?.percentage ?? null" />
                    </FormField>
                    <FormField label="Video link" :error="form.errors.video_url"><TextInput v-model="form.video_url" type="url" placeholder="https://" /></FormField>
                </div>
            </CardPanel>
            <CardPanel title="Tags">
                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    <FormField label="Programme" :error="form.errors.programme_id"><SelectInput v-model="form.programme_id" :options="programmes" placeholder="—" /></FormField>
                    <FormField label="Batch year" :error="form.errors.batch_year"><TextInput v-model.number="form.batch_year" type="number" /></FormField>
                    <FormField label="Industry" :error="form.errors.industry"><TextInput v-model="form.industry" /></FormField>
                    <FormField label="Location" :error="form.errors.location"><TextInput v-model="form.location" /></FormField>
                </div>
            </CardPanel>
            <CardPanel title="Landing page" description="News and announcement types appear under “News & announcements”; other types under “Alumni stories”. Only published items are ever shown.">
                <div class="grid gap-5 sm:grid-cols-2">
                    <CheckboxInput v-model="form.is_featured" label="Featured" description="Shown first, with a Featured badge." />
                    <FormField label="Display order" :error="form.errors.display_order" hint="Lower numbers come first."><TextInput v-model.number="form.display_order" type="number" min="0" /></FormField>
                </div>
            </CardPanel>
            <div class="flex flex-wrap justify-between gap-2">
                <AppButton v-if="story" variant="danger" @click="destroy">Delete</AppButton>
                <span class="ml-auto flex gap-2">
                    <AppButton variant="secondary" :loading="form.processing" @click="save(false)">{{ story?.status === 'published' ? 'Unpublish' : 'Save draft' }}</AppButton>
                    <AppButton :loading="form.processing" @click="save(true)">{{ story?.status === 'published' ? 'Save' : 'Publish' }}</AppButton>
                </span>
            </div>
        </form>

        <ImageGalleryManager
            v-if="story"
            class="mt-6"
            type="stories"
            :owner-id="story.id"
            :images="images"
            :limit-kb="imageLimitKb"
            title="Images"
            :description="`The ★ featured image is the primary image on cards, the landing page and the article (it takes precedence over the cover above). All images form the article's gallery. Each is optimised to ${imageLimitKb} KB or less.`"
        />
        <p v-else class="mt-6 text-sm text-muted">Save the draft to add an image gallery.</p>
    </AppLayout>
</template>
