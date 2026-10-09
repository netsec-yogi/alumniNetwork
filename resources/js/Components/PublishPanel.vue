<script setup lang="ts">
import { ask } from '@/lib/confirm';
import { router } from '@inertiajs/vue3';
import { History, RotateCcw, Upload } from 'lucide-vue-next';
import AppButton from './AppButton.vue';
import CardPanel from './CardPanel.vue';
import StatusBadge from './StatusBadge.vue';

/**
 * Status, unpublish and version history for a draft → publish area
 * (landing text, branding). Restoring loads a version into the draft;
 * publishing it is a separate, deliberate step.
 */
export interface PublishStatus {
    is_published: boolean;
    has_changes: boolean;
    published_at: string | null;
    published_by: string | null;
    draft_at: string | null;
    draft_by: string | null;
}

const props = defineProps<{ status: PublishStatus; history: { id: number; at: string; by: string | null }[]; unpublishRoute: string; restoreRoute: string; what: string }>();

const when = (iso: string | null) => (iso ? new Date(iso).toLocaleString('en-IN', { dateStyle: 'medium', timeStyle: 'short' }) : '');
const opts = { preserveScroll: true };

const unpublish = () =>
    ask(`Unpublish the custom ${props.what}? Visitors will see the defaults again. Your draft is kept, and you can publish it again at any time.`, { confirmLabel: 'Unpublish', tone: 'danger' }).then(
        (ok) => ok && router.post(route(props.unpublishRoute), {}, opts),
    );
const restore = (revision: number | null) =>
    ask(revision ? `Load version #${revision} into the draft? It replaces the current draft. Nothing changes for visitors until you publish.` : 'Reset the draft to the defaults? It replaces the current draft. Nothing changes for visitors until you publish.', {
        confirmLabel: revision ? 'Load version' : 'Reset draft',
    }).then((ok) => ok && router.post(route(props.restoreRoute), { revision }, opts));
</script>

<template>
    <CardPanel title="Publishing" :icon="Upload">
        <div class="space-y-3 text-sm">
            <p class="flex flex-wrap items-center gap-2">
                <StatusBadge :status="status.is_published ? 'published' : 'draft'" :label="status.is_published ? 'Published' : 'Using defaults'" />
                <StatusBadge v-if="status.has_changes" status="pending" label="Unpublished changes" />
            </p>
            <p v-if="status.is_published" class="text-muted">Published {{ when(status.published_at) }}<template v-if="status.published_by"> by {{ status.published_by }}</template>.</p>
            <p v-if="status.draft_at" class="text-muted">Draft saved {{ when(status.draft_at) }}<template v-if="status.draft_by"> by {{ status.draft_by }}</template>.</p>
            <AppButton v-if="status.is_published" variant="danger-ghost" size="sm" @click="unpublish">Unpublish</AppButton>
        </div>

        <h3 class="mt-6 mb-2 flex items-center gap-1.5 text-xs font-bold tracking-wide text-muted uppercase"><History :size="14" aria-hidden="true" />Published versions</h3>
        <p v-if="history.length === 0" class="text-sm text-muted">Nothing published yet.</p>
        <ul v-else class="divide-y divide-line-soft text-sm">
            <li v-for="(h, i) in history" :key="h.id" class="flex items-center justify-between gap-3 py-2">
                <span class="min-w-0">
                    <span class="font-semibold text-ink">#{{ h.id }}</span>
                    <span v-if="i === 0 && status.is_published" class="ml-1.5 text-xs font-semibold text-emerald-700 dark:text-emerald-300">current</span>
                    <span class="block truncate text-xs text-muted">{{ when(h.at) }}<template v-if="h.by"> · {{ h.by }}</template></span>
                </span>
                <AppButton size="sm" variant="ghost" :icon="RotateCcw" @click="restore(h.id)">Load</AppButton>
            </li>
        </ul>
        <AppButton class="mt-3" size="sm" variant="ghost" :icon="RotateCcw" @click="restore(null)">Reset draft to defaults</AppButton>
    </CardPanel>
</template>
