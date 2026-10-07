<script setup lang="ts">
import AppButton from '@/Components/AppButton.vue';
import CampaignProgress from '@/Components/CampaignProgress.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import SiteLayout from '@/Layouts/SiteLayout.vue';
import { Link } from '@inertiajs/vue3';

interface Card {
    slug: string;
    type_label: string;
    title: string;
    summary: string;
    stage: string;
    cover_url: string | null;
    goal: number;
    raised: number;
    matched: number;
    donors: number;
    days_left: number | null;
}

defineProps<{ campaigns: Card[]; canPropose: boolean; mine: { slug: string; title: string; status: string; rejection_reason: string | null }[] }>();
</script>

<template>
    <SiteLayout title="Campaigns">
        <PageHeader title="Fundraising campaigns" description="Scholarships, labs and student causes — led by the institute and by alumni.">
            <AppButton v-if="canPropose" :href="route('fundraising.create')">Start a campaign</AppButton>
        </PageHeader>

        <div v-if="mine.length" class="card mb-6 p-4">
            <p class="text-sm font-medium text-ink">Your campaigns</p>
            <ul class="mt-2 space-y-1 text-sm">
                <li v-for="m in mine" :key="m.slug" class="flex flex-wrap items-center gap-2">
                    <Link :href="route('fundraising.show', m.slug)" class="text-brand-700 hover:underline">{{ m.title }}</Link>
                    <StatusBadge :status="m.status === 'rejected' ? 'rejected' : 'pending'" :label="m.status.replace('_', ' ')" />
                    <span v-if="m.rejection_reason" class="text-red-700">{{ m.rejection_reason }}</span>
                </li>
            </ul>
        </div>

        <EmptyState v-if="campaigns.length === 0" title="No campaigns right now" />
        <ul v-else class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <li v-for="c in campaigns" :key="c.slug">
                <Link :href="route('fundraising.show', c.slug)" class="card group flex h-full flex-col overflow-hidden card-hover">
                    <img v-if="c.cover_url" :src="c.cover_url" alt="" class="h-40 w-full object-cover" loading="lazy" />
                    <div v-else class="h-40 bg-gradient-to-br from-deep-800 to-accent-500" />
                    <div class="flex flex-1 flex-col p-5">
                        <p class="text-xs font-medium tracking-wide text-accent-600 uppercase">{{ c.type_label }} · {{ c.stage }}</p>
                        <h2 class="mt-1 font-semibold text-ink group-hover:underline">{{ c.title }}</h2>
                        <p class="mt-1 line-clamp-2 text-sm text-muted">{{ c.summary }}</p>
                        <div class="mt-auto pt-4"><CampaignProgress :raised="c.raised" :matched="c.matched" :goal="c.goal" :donors="c.donors" :days-left="c.days_left" /></div>
                    </div>
                </Link>
            </li>
        </ul>
    </SiteLayout>
</template>
