<script setup lang="ts">
import { ask } from '@/lib/confirm';
import AppButton from '@/Components/AppButton.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';

interface Row { slug: string; title: string; status: string; responses: number; audience: string; anonymous: boolean; closes_at: string | null }
defineProps<{ surveys: Row[] }>();
const publish = (s: Row) => ask('Publish and invite the audience?').then((ok) => ok && router.post(route('admin.surveys.publish', s.slug), {}, { preserveScroll: true }));
const close = (s: Row) => ask('Close this survey?').then((ok) => ok && router.post(route('admin.surveys.close', s.slug), {}, { preserveScroll: true }));
</script>

<template>
    <AppLayout title="Surveys">
        <PageHeader title="Surveys" description="Feedback from alumni, students and event attendees."><AppButton :href="route('admin.surveys.create')">New survey</AppButton></PageHeader>
        <EmptyState v-if="surveys.length === 0" title="No surveys yet" />
        <ul v-else class="card divide-y divide-line-soft">
            <li v-for="s in surveys" :key="s.slug" class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                <div>
                    <p class="font-medium text-ink">{{ s.title }}</p>
                    <p class="text-sm text-muted">{{ s.audience }} · {{ s.anonymous ? 'anonymous' : 'named' }} · {{ s.responses }} responses<template v-if="s.closes_at"> · closes {{ s.closes_at }}</template></p>
                </div>
                <div class="flex items-center gap-2">
                    <StatusBadge :status="s.status === 'published' ? 'active' : s.status === 'closed' ? 'deactivated' : 'pending'" :label="s.status" />
                    <template v-if="s.status === 'draft'">
                        <AppButton size="sm" variant="ghost" :href="route('admin.surveys.edit', s.slug)">Edit</AppButton>
                        <AppButton size="sm" @click="publish(s)">Publish</AppButton>
                    </template>
                    <template v-else>
                        <AppButton size="sm" variant="ghost" :href="route('admin.surveys.results', s.slug)">Results</AppButton>
                        <AppButton v-if="s.status === 'published'" size="sm" variant="ghost" @click="close(s)">Close</AppButton>
                    </template>
                </div>
            </li>
        </ul>
    </AppLayout>
</template>
