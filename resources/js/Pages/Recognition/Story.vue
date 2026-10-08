<script setup lang="ts">
import PhotoGallery from '@/Components/PhotoGallery.vue';
import AutoBreadcrumbs from '@/Components/AutoBreadcrumbs.vue';
import AppButton from '@/Components/AppButton.vue';
import AvatarImage from '@/Components/AvatarImage.vue';
import SiteLayout from '@/Layouts/SiteLayout.vue';

defineProps<{
    story: {
        title: string;
        type: string;
        excerpt: string | null;
        html: string;
        cover_url: string | null;
        gallery: { thumb: string; full: string; title: string; featured: boolean }[];
        video_url: string | null;
        date: string;
        person: { name: string; batch: string; photo_url: string | null } | null;
        tags: string[];
    };
}>();
</script>

<template>
    <SiteLayout :title="story.title">
        <AutoBreadcrumbs :title="story.title" class="mb-4" />
        <article class="mx-auto max-w-3xl">
            <AppButton variant="ghost" class="-ml-3 mb-4" :href="route('stories.index')">← All stories</AppButton>
            <p class="text-sm font-medium tracking-wide text-accent-600 uppercase">{{ story.type }} · {{ story.date }}</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-ink">{{ story.title }}</h1>
            <p v-if="story.excerpt" class="mt-3 text-lg text-muted">{{ story.excerpt }}</p>
            <div v-if="story.person" class="mt-5 flex items-center gap-3">
                <AvatarImage :name="story.person.name" :src="story.person.photo_url" />
                <p class="text-sm"><span class="font-medium text-ink">{{ story.person.name }}</span><br /><span class="text-muted">{{ story.person.batch }}</span></p>
            </div>
            <img v-if="story.cover_url" :src="story.cover_url" :alt="story.title" class="mt-6 w-full rounded-[var(--radius-card)] object-cover" />
            <a v-if="story.video_url" :href="story.video_url" target="_blank" rel="noopener noreferrer" class="mt-6 block rounded-xl bg-brand-50 p-4 text-sm font-medium text-brand-800 ring-1 ring-brand-100 hover:bg-brand-100">▶ Watch the video</a>
            <!-- Server-rendered Markdown with raw HTML stripped and unsafe links refused (Story::bodyHtml). -->
            <div class="story-body mt-8 text-ink" v-html="story.html" />
            <PhotoGallery v-if="story.gallery.length > 1" class="mt-10" :items="story.gallery" title="Photos" />
            <div v-if="story.tags.length" class="mt-8 flex flex-wrap gap-2 border-t border-line pt-6">
                <span v-for="t in story.tags" :key="t" class="rounded-full bg-surface-sunken px-3 py-1 text-xs text-ink-soft">{{ t }}</span>
            </div>
        </article>
    </SiteLayout>
</template>
