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
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import ContentNav from './ContentNav.vue';

interface Item {
    id: number;
    title: string;
    category: string;
    description: string | null;
    date: string | null;
    link_url: string | null;
    image_url: string | null;
    rejection_reason: string | null;
    person: { name: string; batch: string; id: number };
    submitted: string;
}

defineProps<{ status: string; achievements: Paginated<Item> }>();

const rejecting = ref<Item | null>(null);
const form = useForm({ decision: 'reject', reason: '' });
const publish = (a: Item) => router.post(route('admin.achievements.review', a.id), { decision: 'publish' }, { preserveScroll: true });
const reject = () => form.post(route('admin.achievements.review', rejecting.value!.id), { preserveScroll: true, onSuccess: () => ((rejecting.value = null), form.reset('reason')) });
</script>

<template>
    <AppLayout title="Content">
        <PageHeader title="Content" description="Review submissions and publish stories. Everything here is public once published." />
        <ContentNav />

        <nav class="mb-6 flex gap-2" aria-label="Status">
            <Link
                v-for="s in ['submitted', 'published', 'rejected']"
                :key="s"
                :href="route('admin.achievements.index', { status: s })"
                :class="['rounded-full px-3 py-1.5 text-sm font-medium capitalize', status === s ? 'bg-brand-600 text-white shadow-sm' : 'bg-surface text-ink-soft ring-1 ring-line-strong']"
                >{{ s }}</Link
            >
        </nav>

        <EmptyState v-if="achievements.data.length === 0" title="Nothing here" />
        <ul v-else class="space-y-3">
            <li v-for="a in achievements.data" :key="a.id" class="card flex gap-4 p-5">
                <img v-if="a.image_url" :src="a.image_url" alt="" class="size-24 shrink-0 rounded-lg object-cover" />
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-medium tracking-wide text-accent-600 uppercase">{{ a.category }}<span v-if="a.date"> · {{ a.date }}</span></p>
                    <p class="font-semibold text-ink">{{ a.title }}</p>
                    <p class="text-sm text-muted">
                        <Link :href="route('admin.alumni.show', a.person.id)" class="hover:underline">{{ a.person.name }}</Link> · {{ a.person.batch }} · {{ a.submitted }}
                    </p>
                    <p v-if="a.description" class="mt-2 text-sm text-ink-soft">{{ a.description }}</p>
                    <a v-if="a.link_url" :href="a.link_url" target="_blank" rel="noopener noreferrer nofollow" class="mt-1 block text-sm break-all text-brand-700">{{ a.link_url }}</a>
                    <p v-if="a.rejection_reason" class="mt-2 text-sm text-red-700">{{ a.rejection_reason }}</p>
                </div>
                <div v-if="status === 'submitted'" class="flex shrink-0 flex-col gap-2">
                    <AppButton size="sm" @click="publish(a)">Publish</AppButton>
                    <AppButton size="sm" variant="secondary" @click="rejecting = a">Reject</AppButton>
                </div>
            </li>
        </ul>
        <PaginationNav class="mt-4" :links="achievements.links" :from="achievements.from" :to="achievements.to" :total="achievements.total" />

        <ModalDialog :show="rejecting !== null" title="Reject achievement" @close="rejecting = null">
            <form id="reject-achievement" @submit.prevent="reject">
                <FormField label="Reason (sent to the alumnus)" :error="form.errors.reason" required><TextArea v-model="form.reason" rows="3" /></FormField>
            </form>
            <template #footer>
                <AppButton variant="secondary" @click="rejecting = null">Cancel</AppButton>
                <AppButton type="submit" form="reject-achievement" variant="danger" :loading="form.processing">Reject</AppButton>
            </template>
        </ModalDialog>
    </AppLayout>
</template>
