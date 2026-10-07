<script setup lang="ts">
import EmptyState from '@/Components/EmptyState.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PaginationNav from '@/Components/PaginationNav.vue';
import SelectInput from '@/Components/SelectInput.vue';
import SiteLayout from '@/Layouts/SiteLayout.vue';
import type { Option, Paginated } from '@/types';
import { Link, router } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';

interface Card {
    slug: string;
    title: string;
    excerpt: string | null;
    type: string;
    cover_url: string | null;
    date: string;
}

const props = defineProps<{ stories: Paginated<Card>; filters: { type?: string; programme?: number }; types: Option[]; programmes: Option<number>[] }>();
const f = reactive({ type: props.filters.type ?? '', programme: props.filters.programme ?? ('' as number | '') });
watch(f, () => router.get(route('stories.index'), Object.fromEntries(Object.entries(f).filter(([, v]) => v)) as Record<string, string>, { preserveState: true }));
</script>

<template>
    <SiteLayout title="Alumni stories">
        <PageHeader title="Alumni stories" description="Journeys, interviews and lessons from IIITM graduates around the world." />
        <div class="mb-6 flex flex-wrap gap-3">
            <SelectInput v-model="f.type" :options="types" placeholder="All formats" aria-label="Format" class="w-44" />
            <SelectInput v-model="f.programme" :options="programmes" placeholder="All programmes" aria-label="Programme" class="w-72" />
        </div>
        <EmptyState v-if="stories.data.length === 0" title="No stories yet" />
        <ul v-else class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <li v-for="s in stories.data" :key="s.slug">
                <Link :href="route('stories.show', s.slug)" class="card group block overflow-hidden card-hover">
                    <img v-if="s.cover_url" :src="s.cover_url" alt="" class="h-44 w-full object-cover" loading="lazy" />
                    <div v-else class="h-44 bg-gradient-to-br from-deep-800 to-brand-600" />
                    <div class="p-5">
                        <p class="text-xs font-medium tracking-wide text-accent-600 uppercase">{{ s.type }} · {{ s.date }}</p>
                        <h2 class="mt-1 font-semibold text-ink group-hover:underline">{{ s.title }}</h2>
                        <p v-if="s.excerpt" class="mt-2 line-clamp-3 text-sm text-muted">{{ s.excerpt }}</p>
                    </div>
                </Link>
            </li>
        </ul>
        <PaginationNav class="mt-6" :links="stories.links" :from="stories.from" :to="stories.to" :total="stories.total" />
    </SiteLayout>
</template>
