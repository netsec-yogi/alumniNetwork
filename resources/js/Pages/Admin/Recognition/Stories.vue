<script setup lang="ts">
import AppButton from '@/Components/AppButton.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PaginationNav from '@/Components/PaginationNav.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Paginated } from '@/types';
import { Link } from '@inertiajs/vue3';
import ContentNav from './ContentNav.vue';

defineProps<{ stories: Paginated<{ id: number; slug: string; title: string; type: string; status: string; published_at: string | null; author: string | null }> }>();
</script>

<template>
    <AppLayout title="Stories">
        <PageHeader title="Content"><AppButton :href="route('admin.stories.create')">New story</AppButton></PageHeader>
        <ContentNav />
        <EmptyState v-if="stories.data.length === 0" title="No stories yet" />
        <ul v-else class="card divide-y divide-line-soft">
            <li v-for="s in stories.data" :key="s.id" class="flex items-center justify-between gap-3 px-5 py-3">
                <div>
                    <Link :href="route('admin.stories.edit', s.slug)" class="font-medium text-brand-800 hover:underline">{{ s.title }}</Link>
                    <p class="text-sm text-muted">{{ s.type }}<template v-if="s.author"> · {{ s.author }}</template><template v-if="s.published_at"> · {{ s.published_at }}</template></p>
                </div>
                <StatusBadge :status="s.status === 'published' ? 'active' : 'pending'" :label="s.status" />
            </li>
        </ul>
        <PaginationNav class="mt-4" :links="stories.links" :from="stories.from" :to="stories.to" :total="stories.total" />
    </AppLayout>
</template>
