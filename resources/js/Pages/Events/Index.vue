<script setup lang="ts">
import EmptyState from '@/Components/EmptyState.vue';
import EventCard, { type EventSummary } from '@/Components/EventCard.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PaginationNav from '@/Components/PaginationNav.vue';
import SelectInput from '@/Components/SelectInput.vue';
import SiteLayout from '@/Layouts/SiteLayout.vue';
import type { Option, Paginated } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps<{ events: Paginated<EventSummary>; filters: { type: string | null; when: string }; typeOptions: Option[] }>();

const type = ref(props.filters.type ?? '');
watch(type, (t) => router.get(route('events.index'), { when: props.filters.when, ...(t ? { type: t } : {}) }, { preserveState: true }));

const signedIn = !!usePage().props.auth.user;
const tabs = [
    { key: 'upcoming', label: 'Upcoming' },
    { key: 'past', label: 'Past' },
    ...(signedIn ? [{ key: 'mine', label: 'My events' }] : []),
];
</script>

<template>
    <SiteLayout title="Events">
        <PageHeader title="Events" description="Alumni meets, reunions, webinars, lectures and chapter events." />

        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <nav class="flex gap-2" aria-label="When">
                <Link
                    v-for="t in tabs"
                    :key="t.key"
                    :href="route('events.index', { when: t.key, ...(filters.type ? { type: filters.type } : {}) })"
                    :aria-current="filters.when === t.key ? 'page' : undefined"
                    :class="['rounded-full px-3 py-1.5 text-sm font-medium', filters.when === t.key ? 'bg-brand-800 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50']"
                    >{{ t.label }}</Link
                >
            </nav>
            <SelectInput v-model="type" :options="typeOptions" placeholder="All types" aria-label="Event type" class="w-48" />
        </div>

        <EmptyState v-if="events.data.length === 0" title="No events here yet" description="Check back soon, or look at past events." />
        <ul v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <li v-for="e in events.data" :key="e.id"><EventCard :event="e" /></li>
        </ul>
        <PaginationNav class="mt-6" :links="events.links" :from="events.from" :to="events.to" :total="events.total" />
    </SiteLayout>
</template>
