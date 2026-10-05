<script setup lang="ts">
import AppButton from '@/Components/AppButton.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PaginationNav from '@/Components/PaginationNav.vue';
import PersonCard from '@/Components/PersonCard.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Paginated } from '@/types';
import { Link, router } from '@inertiajs/vue3';

interface Member {
    id: number;
    name: string;
    subtitle: string;
    role: 'member' | 'moderator' | 'admin';
    is_me: boolean;
    joined: string;
}

defineProps<{ group: { slug: string; name: string }; status: string; isAdmin: boolean; members: Paginated<Member> }>();

const act = (m: Member, action: string, confirmText?: string) =>
    (!confirmText || confirm(confirmText)) && router.post(route('communities.members.manage', m.id), { action }, { preserveScroll: true });
</script>

<template>
    <AppLayout :title="`Members · ${group.name}`">
        <PageHeader :title="`Members of ${group.name}`">
            <AppButton variant="ghost" :href="route('communities.show', group.slug)">← Group</AppButton>
        </PageHeader>

        <nav class="mb-6 flex gap-2" aria-label="Status">
            <Link
                v-for="s in ['active', 'pending', 'banned']"
                :key="s"
                :href="route('communities.members', { community: group.slug, status: s })"
                :class="['rounded-full px-3 py-1.5 text-sm font-medium capitalize', status === s ? 'bg-brand-800 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-300']"
                >{{ s }}</Link
            >
        </nav>

        <EmptyState v-if="members.data.length === 0" title="Nobody here" />
        <ul v-else class="divide-y divide-slate-100 rounded-xl bg-white px-5 shadow-sm ring-1 ring-slate-200">
            <li v-for="m in members.data" :key="m.id" class="py-3">
                <PersonCard :name="m.name" :subtitle="`${m.subtitle} · joined ${m.joined}`">
                    <template #actions>
                        <span v-if="m.role !== 'member'" class="self-center rounded-full bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-800 capitalize">{{ m.role }}</span>
                        <template v-if="!m.is_me">
                            <template v-if="status === 'pending'">
                                <AppButton size="sm" @click="act(m, 'approve')">Approve</AppButton>
                                <AppButton size="sm" variant="secondary" @click="act(m, 'reject')">Reject</AppButton>
                            </template>
                            <template v-else-if="status === 'active'">
                                <AppButton v-if="isAdmin && m.role === 'member'" size="sm" variant="ghost" @click="act(m, 'promote')">Make moderator</AppButton>
                                <AppButton v-if="isAdmin && m.role === 'moderator'" size="sm" variant="ghost" @click="act(m, 'make_admin', `Make ${m.name} an admin?`)">Make admin</AppButton>
                                <AppButton v-if="isAdmin && m.role !== 'member'" size="sm" variant="ghost" @click="act(m, 'demote')">Remove role</AppButton>
                                <AppButton v-if="isAdmin || m.role === 'member'" size="sm" variant="ghost" @click="act(m, 'remove', `Remove ${m.name} from the group?`)">Remove</AppButton>
                                <AppButton v-if="isAdmin || m.role === 'member'" size="sm" variant="ghost" class="text-red-700" @click="act(m, 'ban', `Ban ${m.name}? They won’t be able to rejoin.`)">Ban</AppButton>
                            </template>
                            <AppButton v-else size="sm" variant="secondary" @click="act(m, 'remove')">Unban</AppButton>
                        </template>
                    </template>
                </PersonCard>
            </li>
        </ul>
        <PaginationNav class="mt-4" :links="members.links" :from="members.from" :to="members.to" :total="members.total" />
    </AppLayout>
</template>
