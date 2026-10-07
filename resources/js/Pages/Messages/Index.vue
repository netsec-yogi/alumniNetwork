<script setup lang="ts">
import AvatarImage from '@/Components/AvatarImage.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PaginationNav from '@/Components/PaginationNav.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Paginated } from '@/types';
import { Link, usePoll } from '@inertiajs/vue3';

interface Row {
    id: number;
    status: string;
    other: { name: string; photo_url: string | null };
    preview: string | null;
    unread: boolean;
    at: string | null;
}

defineProps<{ tab: 'inbox' | 'requests'; conversations: Paginated<Row>; requestCount: number }>();

usePoll(20000, { only: ['conversations', 'requestCount', 'counts'] });
</script>

<template>
    <AppLayout title="Messages">
        <PageHeader title="Messages" description="Private conversations. Only the people in a conversation can read it." />

        <nav class="mb-6 flex gap-2" aria-label="Folders">
            <Link
                :href="route('messages.index')"
                :class="['rounded-md px-3 py-1.5 text-[13px] font-medium whitespace-nowrap transition-colors', tab === 'inbox' ? 'bg-brand-600 text-white shadow-sm' : 'bg-surface text-ink-soft ring-1 ring-line-strong']"
                >Inbox</Link
            >
            <Link
                :href="route('messages.index', { tab: 'requests' })"
                :class="['rounded-md px-3 py-1.5 text-[13px] font-medium whitespace-nowrap transition-colors', tab === 'requests' ? 'bg-brand-600 text-white shadow-sm' : 'bg-surface text-ink-soft ring-1 ring-line-strong']"
                >Requests<span v-if="requestCount" class="ml-1 tabular-nums">({{ requestCount }})</span></Link
            >
        </nav>

        <EmptyState
            v-if="conversations.data.length === 0"
            :title="tab === 'requests' ? 'No message requests' : 'No conversations yet'"
            :description="tab === 'inbox' ? 'Start one from any alumnus’s profile.' : undefined"
        />
        <ul v-else class="card divide-y divide-line-soft overflow-hidden">
            <li v-for="c in conversations.data" :key="c.id">
                <Link :href="route('messages.show', c.id)" class="flex items-center gap-3 px-5 py-4 hover:bg-surface-muted">
                    <AvatarImage :name="c.other.name" :src="c.other.photo_url" />
                    <span class="min-w-0 flex-1">
                        <span :class="['block truncate', c.unread ? 'font-semibold text-ink' : 'font-medium text-ink']">{{ c.other.name }}</span>
                        <span :class="['block truncate text-sm', c.unread ? 'text-ink' : 'text-muted']">{{ c.preview }}</span>
                    </span>
                    <span class="flex shrink-0 flex-col items-end gap-1 text-xs text-subtle">
                        {{ c.at }}
                        <span v-if="c.status === 'request'" class="rounded-full bg-amber-50 px-2 py-0.5 font-medium text-amber-800">Request</span>
                        <span v-else-if="c.status === 'declined'" class="rounded-full bg-surface-sunken px-2 py-0.5 text-muted">Declined</span>
                        <span v-if="c.unread" class="size-2 rounded-full bg-accent-500" aria-label="Unread" />
                    </span>
                </Link>
            </li>
        </ul>
        <PaginationNav class="mt-4" :links="conversations.links" :from="conversations.from" :to="conversations.to" :total="conversations.total" />
    </AppLayout>
</template>
