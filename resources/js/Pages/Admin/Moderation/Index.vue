<script setup lang="ts">
import AppButton from '@/Components/AppButton.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FormField from '@/Components/FormField.vue';
import ModalDialog from '@/Components/ModalDialog.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PaginationNav from '@/Components/PaginationNav.vue';
import TextArea from '@/Components/TextArea.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Paginated } from '@/types';
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Item {
    id: number;
    type: string;
    summary: string;
    url: string | null;
    reason: string;
    details: string | null;
    reporter: string | null;
    at: string;
    can_remove: boolean;
    resolution: string | null;
    reviewer: string | null;
}

defineProps<{ reports: Paginated<Item>; status: string }>();

const target = ref<{ report: Item; action: 'dismiss' | 'remove' } | null>(null);
const form = useForm({ action: 'dismiss', resolution: '' });

function open(report: Item, action: 'dismiss' | 'remove') {
    target.value = { report, action };
    form.action = action;
}
function close() {
    target.value = null;
    form.reset();
    form.clearErrors();
}
const submit = () => form.post(route('admin.moderation.resolve', target.value!.report.id), { preserveScroll: true, onSuccess: close });
const typeLabel = (t: string) => ({ alumni_profile: 'Profile', post: 'Post', post_comment: 'Comment', job_posting: 'Job' })[t] ?? t;
</script>

<template>
    <AppLayout title="Moderation">
        <PageHeader title="Moderation" description="Reports from members about profiles, posts, comments and jobs you are responsible for." />

        <nav class="mb-6 flex gap-2" aria-label="Status">
            <Link
                v-for="s in ['open', 'actioned', 'dismissed']"
                :key="s"
                :href="route('admin.moderation.index', { status: s })"
                :class="['rounded-full px-3 py-1.5 text-sm font-medium capitalize', status === s ? 'bg-brand-600 text-white shadow-sm' : 'bg-surface text-ink-soft ring-1 ring-line-strong']"
                >{{ s }}</Link
            >
        </nav>

        <EmptyState v-if="reports.data.length === 0" title="Nothing here" description="No reports with this status." />
        <ul v-else class="space-y-3">
            <li v-for="r in reports.data" :key="r.id" class="card p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-medium tracking-wide text-muted uppercase">{{ typeLabel(r.type) }} · {{ r.reason }}</p>
                        <p class="mt-1 font-medium break-words text-ink">{{ r.summary }}</p>
                        <p v-if="r.details" class="mt-1 text-sm text-muted">“{{ r.details }}”</p>
                        <p class="mt-1 text-xs text-subtle">Reported by {{ r.reporter ?? 'a deleted user' }} {{ r.at }}</p>
                        <p v-if="r.resolution" class="mt-2 text-sm text-muted">{{ r.reviewer }}: {{ r.resolution }}</p>
                    </div>
                    <div class="flex gap-2">
                        <AppButton v-if="r.url" size="sm" variant="ghost" :href="r.url">View</AppButton>
                        <template v-if="status === 'open'">
                            <AppButton size="sm" variant="secondary" @click="open(r, 'dismiss')">Dismiss</AppButton>
                            <AppButton v-if="r.can_remove" size="sm" variant="danger" @click="open(r, 'remove')">Remove content</AppButton>
                        </template>
                    </div>
                </div>
            </li>
        </ul>
        <PaginationNav class="mt-4" :links="reports.links" :from="reports.from" :to="reports.to" :total="reports.total" />

        <ModalDialog :show="target !== null" :title="target?.action === 'remove' ? 'Remove this content' : 'Dismiss report'" @close="close">
            <form id="moderation-form" @submit.prevent="submit">
                <FormField label="Note (recorded in the audit log)" :error="form.errors.resolution" required>
                    <TextArea v-model="form.resolution" rows="3" />
                </FormField>
            </form>
            <template #footer>
                <AppButton variant="secondary" @click="close">Cancel</AppButton>
                <AppButton type="submit" form="moderation-form" :variant="target?.action === 'remove' ? 'danger' : 'primary'" :loading="form.processing">Confirm</AppButton>
            </template>
        </ModalDialog>
    </AppLayout>
</template>
