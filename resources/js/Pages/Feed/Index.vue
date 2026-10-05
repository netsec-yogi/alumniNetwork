<script setup lang="ts">
import EmptyState from '@/Components/EmptyState.vue';
import PostCard, { type FeedPost } from '@/Components/PostCard.vue';
import PostComposer from '@/Components/PostComposer.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { InfiniteScroll, Link } from '@inertiajs/vue3';

defineProps<{
    tab: 'all' | 'following' | 'saved';
    posts: { data: FeedPost[] };
    myCommunities: { id: number; name: string }[];
    canAnnounce: boolean;
    reportReasons: Record<string, string>;
}>();

const tabs = [
    { key: 'all', label: 'Everyone' },
    { key: 'following', label: 'People I follow' },
    { key: 'saved', label: 'Saved' },
];
</script>

<template>
    <AppLayout title="Feed">
        <div class="mx-auto max-w-2xl space-y-4">
            <PostComposer :communities="myCommunities" :can-announce="canAnnounce" />

            <nav class="flex gap-2" aria-label="Feed">
                <Link
                    v-for="t in tabs"
                    :key="t.key"
                    :href="route('feed', { tab: t.key })"
                    :aria-current="tab === t.key ? 'page' : undefined"
                    :class="['rounded-full px-3 py-1.5 text-sm font-medium', tab === t.key ? 'bg-brand-800 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50']"
                    >{{ t.label }}</Link
                >
            </nav>

            <EmptyState
                v-if="posts.data.length === 0"
                :title="tab === 'saved' ? 'Nothing saved yet' : 'No posts yet'"
                :description="tab === 'following' ? 'Follow or connect with people to see their posts here.' : 'Be the first to share something.'"
            />
            <InfiniteScroll v-else data="posts" class="space-y-4">
                <PostCard v-for="p in posts.data" :key="p.id" :post="p" :report-reasons="reportReasons" />
            </InfiniteScroll>
        </div>
    </AppLayout>
</template>
