<script setup lang="ts">
import { ask } from '@/lib/confirm';
import AppButton from '@/Components/AppButton.vue';
import CardPanel from '@/Components/CardPanel.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PaginationNav from '@/Components/PaginationNav.vue';
import PersonCard from '@/Components/PersonCard.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Paginated } from '@/types';
import { Link, router } from '@inertiajs/vue3';

interface Item {
    user_id: number;
    name: string;
    profile_id: number | null;
    subtitle: string;
    connection_id?: number;
    message?: string | null;
    since?: string;
}

defineProps<{
    tab: 'connections' | 'received' | 'sent' | 'blocked';
    items: Paginated<Item>;
    counts: { connections: number; received: number; sent: number };
    suggestions: { profile_id: number; name: string; subtitle: string; reason: string }[];
}>();

const tabs = [
    { key: 'connections', label: 'Connections' },
    { key: 'received', label: 'Requests' },
    { key: 'sent', label: 'Sent' },
    { key: 'blocked', label: 'Blocked' },
] as const;

const opts = { preserveScroll: true };
const accept = (id: number) => router.post(route('connections.accept', id), {}, opts);
const decline = (id: number) => router.post(route('connections.decline', id), {}, opts);
const remove = (id: number, what: string) => ask(what).then((ok) => ok && router.delete(route('connections.destroy', id), opts));
const unblock = (userId: number) => router.delete(route('blocks.destroy', userId), opts);
const connect = (profileId: number) => router.post(route('connections.store', profileId), {}, opts);
</script>

<template>
    <AppLayout title="Connections">
        <PageHeader title="Connections" description="Your network of IIITM alumni, students and faculty.">
            <AppButton variant="secondary" :href="route('directory')">Find people</AppButton>
        </PageHeader>

        <nav class="mb-6 flex flex-wrap gap-2" aria-label="Connection lists">
            <Link
                v-for="t in tabs"
                :key="t.key"
                :href="route('connections.index', { tab: t.key })"
                :aria-current="tab === t.key ? 'page' : undefined"
                :class="['rounded-md px-3 py-1.5 text-[13px] font-medium whitespace-nowrap transition-colors', tab === t.key ? 'bg-brand-600 text-white shadow-sm' : 'bg-surface text-muted ring-1 ring-line ring-inset hover:bg-surface-muted hover:text-ink']"
            >
                {{ t.label }}<span v-if="t.key !== 'blocked'" class="ml-1 tabular-nums opacity-75">({{ counts[t.key] }})</span>
            </Link>
        </nav>

        <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
            <section class="space-y-4">
                <EmptyState
                    v-if="items.data.length === 0"
                    :title="{ connections: 'No connections yet', received: 'No pending requests', sent: 'No sent requests', blocked: 'Nobody blocked' }[tab]"
                    :description="tab === 'connections' ? 'Search the directory and send a few requests to batchmates.' : undefined"
                />
                <ul v-else class="card divide-y divide-line-soft px-5">
                    <li v-for="item in items.data" :key="item.user_id" class="py-4">
                        <PersonCard :name="item.name" :subtitle="item.subtitle" :profile-id="item.profile_id">
                            <p v-if="item.message" class="mt-2 rounded-lg bg-surface-muted p-2 text-sm text-ink-soft">“{{ item.message }}”</p>
                            <p v-if="item.since" class="mt-1 text-xs text-subtle">{{ tab === 'connections' ? 'Connected' : 'Sent' }} {{ item.since }}</p>
                            <template #actions>
                                <template v-if="tab === 'received'">
                                    <AppButton size="sm" @click="accept(item.connection_id!)">Accept</AppButton>
                                    <AppButton size="sm" variant="secondary" @click="decline(item.connection_id!)">Ignore</AppButton>
                                </template>
                                <AppButton v-else-if="tab === 'sent'" size="sm" variant="secondary" @click="remove(item.connection_id!, 'Withdraw this request?')">Withdraw</AppButton>
                                <AppButton v-else-if="tab === 'connections'" size="sm" variant="ghost" @click="remove(item.connection_id!, `Remove ${item.name} from your connections?`)">Remove</AppButton>
                                <AppButton v-else size="sm" variant="secondary" @click="unblock(item.user_id)">Unblock</AppButton>
                            </template>
                        </PersonCard>
                    </li>
                </ul>
                <PaginationNav :links="items.links" :from="items.from" :to="items.to" :total="items.total" />
            </section>

            <aside v-if="suggestions.length">
                <CardPanel title="People you may know">
                    <ul class="space-y-4">
                        <li v-for="s in suggestions" :key="s.profile_id">
                            <PersonCard :name="s.name" :subtitle="`${s.reason} · ${s.subtitle}`" :profile-id="s.profile_id">
                                <AppButton size="sm" variant="secondary" class="mt-2" @click="connect(s.profile_id)">Connect</AppButton>
                            </PersonCard>
                        </li>
                    </ul>
                </CardPanel>
            </aside>
        </div>
    </AppLayout>
</template>
