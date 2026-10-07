<script setup lang="ts">
import { ask } from '@/lib/confirm';
import AutoBreadcrumbs from '@/Components/AutoBreadcrumbs.vue';
import AlertBox from '@/Components/AlertBox.vue';
import AppButton from '@/Components/AppButton.vue';
import CardPanel from '@/Components/CardPanel.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PostCard, { type FeedPost } from '@/Components/PostCard.vue';
import PostComposer from '@/Components/PostComposer.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { InfiniteScroll, Link, router } from '@inertiajs/vue3';

const props = defineProps<{
    group: {
        id: number;
        slug: string;
        name: string;
        kind: string;
        category: string;
        join_policy: string;
        is_official: boolean;
        description: string | null;
        leaders: { name: string; role: string; profile_id: number | null }[];
        members_count: number;
        pending_count: number;
    };
    membership: { status: string; role: string } | null;
    canRead: boolean;
    canPost: boolean;
    canModerate: boolean;
    events: { slug: string; title: string; starts_at: string }[];
    posts: { data: FeedPost[] } | null;
    reportReasons: Record<string, string>;
}>();

const join = () => router.post(route('communities.join', props.group.slug), {}, { preserveScroll: true });
const leave = () => ask(`Leave ${props.group.name}?`).then((ok) => ok && router.delete(route('communities.leave', props.group.slug), { preserveScroll: true }));
</script>

<template>
    <AppLayout :title="group.name">
        <AutoBreadcrumbs :title="group.name" class="mb-4" />
        <div class="mb-6 rounded-xl bg-gradient-to-r from-deep-900 to-deep-700 p-6 text-white">
            <p class="text-xs font-medium tracking-wide text-accent-400 uppercase">{{ group.kind === 'chapter' ? 'Chapter' : 'Community' }} · {{ group.category }}<span v-if="group.is_official"> · Official</span></p>
            <h1 class="mt-1 text-2xl font-semibold">{{ group.name }}</h1>
            <p class="mt-1 text-sm text-white/85">{{ group.members_count }} members</p>
            <div class="mt-4 flex flex-wrap gap-2">
                <template v-if="!membership">
                    <button v-if="group.join_policy !== 'restricted'" type="button" class="rounded-lg bg-accent-500 px-4 py-2 text-sm font-semibold text-brand-950 hover:bg-accent-400" @click="join">
                        {{ group.join_policy === 'approval' ? 'Request to join' : 'Join group' }}
                    </button>
                </template>
                <span v-else-if="membership.status === 'pending'" class="rounded-lg bg-white/10 px-3 py-2 text-sm">Request pending</span>
                <button v-else-if="membership.status === 'active'" type="button" class="rounded-lg px-3 py-2 text-sm ring-1 ring-white/30 hover:bg-white/10" @click="leave">Leave</button>
                <Link v-if="canModerate" :href="route('communities.members', group.slug)" class="rounded-lg px-3 py-2 text-sm ring-1 ring-white/30 hover:bg-white/10">
                    Members<span v-if="group.pending_count"> · {{ group.pending_count }} pending</span>
                </Link>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-[1fr_18rem]">
            <section class="space-y-4">
                <PostComposer v-if="canPost" :community-id="group.id" :can-announce="canModerate" :placeholder="`Post to ${group.name}…`" />
                <AlertBox v-if="!canRead" tone="info">This group’s posts are visible to members only.</AlertBox>
                <template v-else-if="posts">
                    <EmptyState v-if="posts.data.length === 0" title="No posts yet" description="Start the conversation." />
                    <InfiniteScroll v-else data="posts" class="space-y-4">
                        <PostCard v-for="p in posts.data" :key="p.id" :post="p" :report-reasons="reportReasons" />
                    </InfiniteScroll>
                </template>
            </section>

            <aside class="space-y-4">
                <CardPanel v-if="group.description" title="About"><p class="text-sm whitespace-pre-line text-ink-soft">{{ group.description }}</p></CardPanel>
                <CardPanel v-if="events.length" title="Upcoming events">
                    <ul class="space-y-3 text-sm">
                        <li v-for="e in events" :key="e.slug">
                            <Link :href="route('events.show', e.slug)" class="font-medium text-brand-700 hover:underline">{{ e.title }}</Link>
                            <p class="text-muted">{{ e.starts_at }}</p>
                        </li>
                    </ul>
                </CardPanel>
                <CardPanel v-if="group.leaders.length" :title="group.kind === 'chapter' ? 'Leadership' : 'Moderators'">
                    <ul class="space-y-2 text-sm">
                        <li v-for="l in group.leaders" :key="l.name" class="flex justify-between">
                            <Link v-if="l.profile_id" :href="route('alumni.show', l.profile_id)" class="text-ink hover:underline">{{ l.name }}</Link>
                            <span v-else>{{ l.name }}</span>
                            <span class="text-muted capitalize">{{ l.role }}</span>
                        </li>
                    </ul>
                </CardPanel>
                <AppButton variant="ghost" :href="route('communities.index')">← All groups</AppButton>
            </aside>
        </div>
    </AppLayout>
</template>
