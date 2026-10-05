<script setup lang="ts">
import AppButton from '@/Components/AppButton.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PaginationNav from '@/Components/PaginationNav.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Paginated } from '@/types';
import { router } from '@inertiajs/vue3';

interface Item {
    id: string;
    title: string;
    body: string | null;
    url: string | null;
    read: boolean;
    at: string;
}

defineProps<{ notifications: Paginated<Item> }>();
</script>

<template>
    <AppLayout title="Notifications">
        <PageHeader title="Notifications">
            <AppButton variant="secondary" @click="router.post(route('notifications.read-all'), {}, { preserveScroll: true })">Mark all as read</AppButton>
        </PageHeader>

        <EmptyState v-if="notifications.data.length === 0" title="You're all caught up" />
        <ul v-else class="divide-y divide-slate-100 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
            <li v-for="n in notifications.data" :key="n.id">
                <button
                    type="button"
                    class="flex w-full items-start gap-3 px-5 py-4 text-left hover:bg-slate-50"
                    @click="router.post(route('notifications.open', n.id))"
                >
                    <span :class="['mt-1.5 size-2 shrink-0 rounded-full', n.read ? 'bg-transparent' : 'bg-accent-500']" :aria-label="n.read ? undefined : 'Unread'" />
                    <span class="flex-1">
                        <span :class="['block text-sm', n.read ? 'text-slate-700' : 'font-semibold text-slate-900']">{{ n.title }}</span>
                        <span v-if="n.body" class="mt-0.5 block text-sm text-slate-500">{{ n.body }}</span>
                    </span>
                    <span class="shrink-0 text-xs text-slate-400">{{ n.at }}</span>
                </button>
            </li>
        </ul>
        <PaginationNav class="mt-4" :links="notifications.links" :from="notifications.from" :to="notifications.to" :total="notifications.total" />
    </AppLayout>
</template>
