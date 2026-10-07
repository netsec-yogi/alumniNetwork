<script setup lang="ts">
import AutoBreadcrumbs from '@/Components/AutoBreadcrumbs.vue';
import AlertBox from '@/Components/AlertBox.vue';
import AppButton from '@/Components/AppButton.vue';
import CardPanel from '@/Components/CardPanel.vue';
import PersonCard from '@/Components/PersonCard.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';

const props = defineProps<{
    startup: {
        slug: string;
        name: string;
        tagline: string | null;
        industry: string;
        location: string | null;
        stage: string;
        is_hiring: boolean;
        logo_url: string | null;
        description: string;
        website_url: string | null;
        founded_year: number | null;
        founders: { name: string; role: string | null; profile_id: number; batch: string; photo_url: string | null }[];
        is_hidden: boolean;
    };
    canEdit: boolean;
    canModerate: boolean;
}>();
const toggle = () => router.post(route('startups.visibility', props.startup.slug), {}, { preserveScroll: true });
</script>

<template>
    <AppLayout :title="startup.name">
        <AutoBreadcrumbs :title="startup.name" class="mb-4" />
        <div class="mb-6 flex flex-wrap items-center justify-between gap-2">
            <AppButton variant="ghost" class="-ml-3" :href="route('startups.index')">← Startups</AppButton>
            <div class="flex gap-2">
                <AppButton v-if="canEdit" variant="secondary" :href="route('startups.edit', startup.slug)">Edit</AppButton>
                <AppButton v-if="canModerate" variant="ghost" @click="toggle">{{ startup.is_hidden ? 'Unhide' : 'Hide from directory' }}</AppButton>
            </div>
        </div>
        <AlertBox v-if="startup.is_hidden" tone="warning" class="mb-6">Hidden from the directory by a content manager.</AlertBox>
        <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
            <CardPanel>
                <div class="flex items-start gap-4">
                    <img v-if="startup.logo_url" :src="startup.logo_url" alt="" class="size-16 rounded-lg object-cover ring-1 ring-line" />
                    <div>
                        <h1 class="text-2xl font-semibold text-ink">{{ startup.name }}</h1>
                        <p v-if="startup.tagline" class="text-muted">{{ startup.tagline }}</p>
                    </div>
                </div>
                <p class="mt-6 text-sm leading-relaxed whitespace-pre-line text-ink-soft">{{ startup.description }}</p>
            </CardPanel>
            <aside class="space-y-4">
                <CardPanel>
                    <dl class="space-y-3 text-sm">
                        <div><dt class="text-muted">Industry</dt><dd class="font-medium">{{ startup.industry }}</dd></div>
                        <div><dt class="text-muted">Stage</dt><dd class="font-medium">{{ startup.stage }}</dd></div>
                        <div v-if="startup.location"><dt class="text-muted">Location</dt><dd class="font-medium">{{ startup.location }}</dd></div>
                        <div v-if="startup.founded_year"><dt class="text-muted">Founded</dt><dd class="font-medium">{{ startup.founded_year }}</dd></div>
                        <div v-if="startup.is_hiring"><dd class="font-medium text-emerald-700">Hiring now</dd></div>
                    </dl>
                    <a v-if="startup.website_url" :href="startup.website_url" target="_blank" rel="noopener noreferrer nofollow" class="mt-4 block text-sm font-medium text-brand-700 hover:underline">Website ↗</a>
                </CardPanel>
                <CardPanel title="Founders">
                    <ul class="space-y-3">
                        <li v-for="f in startup.founders" :key="f.profile_id"><PersonCard :name="f.name" :subtitle="[f.role, f.batch].filter(Boolean).join(' · ')" :profile-id="f.profile_id" :photo-url="f.photo_url" /></li>
                    </ul>
                </CardPanel>
            </aside>
        </div>
    </AppLayout>
</template>
