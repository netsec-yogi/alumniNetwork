<script setup lang="ts">
import AppButton from '@/Components/AppButton.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PaginationNav from '@/Components/PaginationNav.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Paginated } from '@/types';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Group {
    id: number;
    slug: string;
    name: string;
    kind: 'community' | 'chapter';
    category: string;
    join_policy: string;
    is_official: boolean;
    members_count: number;
    membership: string | null;
}

const props = defineProps<{ groups: Paginated<Group>; filters: { kind?: string; q?: string; mine?: boolean | string } }>();

const q = ref(props.filters.q ?? '');
const mine = props.filters.mine === true || props.filters.mine === '1';
const go = (params: Record<string, unknown>) =>
    router.get(route('communities.index'), Object.fromEntries(Object.entries(params).filter(([, v]) => v)) as Record<string, string>, { preserveState: true });
const tabs = [
    { label: 'All', params: {} },
    { label: 'Communities', params: { kind: 'community' } },
    { label: 'Chapters', params: { kind: 'chapter' } },
    { label: 'My groups', params: { mine: 1 } },
];
const isActive = (p: Record<string, unknown>) => (p.mine ? mine : !mine && (p.kind ?? null) === (props.filters.kind ?? null));
const join = (g: Group) => router.post(route('communities.join', g.slug), {}, { preserveScroll: true });
</script>

<template>
    <AppLayout title="Communities">
        <PageHeader title="Communities & chapters" description="Your batch, your city, your field — find your people." />

        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <nav class="flex flex-wrap gap-2" aria-label="Filter">
                <button
                    v-for="t in tabs"
                    :key="t.label"
                    type="button"
                    :class="['rounded-md px-3 py-1.5 text-[13px] font-medium whitespace-nowrap transition-colors', isActive(t.params) ? 'bg-brand-600 text-white shadow-sm' : 'bg-surface text-ink-soft ring-1 ring-line-strong']"
                    @click="go({ ...t.params, q })"
                >
                    {{ t.label }}
                </button>
            </nav>
            <form class="w-64" role="search" @submit.prevent="go({ ...filters, q })"><TextInput v-model="q" type="search" placeholder="Search groups" aria-label="Search groups" /></form>
        </div>

        <EmptyState v-if="groups.data.length === 0" title="No groups found" />
        <ul v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <li v-for="g in groups.data" :key="g.id" class="card flex flex-col p-5">
                <p class="text-xs font-medium tracking-wide text-accent-600 uppercase">{{ g.category }}<span v-if="g.is_official"> · Official</span></p>
                <Link :href="route('communities.show', g.slug)" class="mt-1 font-semibold text-ink hover:underline">{{ g.name }}</Link>
                <p class="mt-1 text-sm text-muted">{{ g.members_count }} {{ g.members_count === 1 ? 'member' : 'members' }}<template v-if="g.join_policy === 'approval'"> · Approval needed</template></p>
                <div class="mt-auto pt-4">
                    <AppButton v-if="g.membership === 'active'" size="sm" variant="secondary" :href="route('communities.show', g.slug)">Open</AppButton>
                    <span v-else-if="g.membership === 'pending'" class="text-sm text-muted">Request pending</span>
                    <AppButton v-else-if="g.membership !== 'banned' && g.join_policy !== 'restricted'" size="sm" @click="join(g)">{{ g.join_policy === 'approval' ? 'Request to join' : 'Join' }}</AppButton>
                </div>
            </li>
        </ul>
        <PaginationNav class="mt-6" :links="groups.links" :from="groups.from" :to="groups.to" :total="groups.total" />
    </AppLayout>
</template>
