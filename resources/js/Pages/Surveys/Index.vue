<script setup lang="ts">
import EmptyState from '@/Components/EmptyState.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

defineProps<{ surveys: { slug: string; title: string; description: string | null; anonymous: boolean; closes_at: string | null; done: boolean }[] }>();
</script>

<template>
    <AppLayout title="Surveys">
        <PageHeader title="Surveys" description="A few minutes of your time helps shape alumni programmes." />
        <EmptyState v-if="surveys.length === 0" title="No open surveys for you right now" />
        <ul v-else class="space-y-3">
            <li v-for="s in surveys" :key="s.slug">
                <Link :href="route('surveys.show', s.slug)" class="card flex items-start justify-between gap-3 p-5 card-hover">
                    <span>
                        <span class="block font-semibold text-ink">{{ s.title }}</span>
                        <span v-if="s.description" class="mt-1 block text-sm text-muted">{{ s.description }}</span>
                        <span class="mt-1 block text-xs text-subtle">{{ s.anonymous ? 'Anonymous' : 'Named responses' }}<template v-if="s.closes_at"> · closes {{ s.closes_at }}</template></span>
                    </span>
                    <StatusBadge :status="s.done ? 'verified' : 'pending'" :label="s.done ? 'Answered' : 'Open'" />
                </Link>
            </li>
        </ul>
    </AppLayout>
</template>
