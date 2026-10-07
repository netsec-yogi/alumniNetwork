<script setup lang="ts">
import AppButton from '@/Components/AppButton.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FormField from '@/Components/FormField.vue';
import ModalDialog from '@/Components/ModalDialog.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PaginationNav from '@/Components/PaginationNav.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TagInput from '@/Components/TagInput.vue';
import TextArea from '@/Components/TextArea.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Option, Paginated } from '@/types';
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Row {
    id: number;
    type: string;
    title: string;
    organization: string | null;
    areas: string[];
    closes_on: string | null;
    poster: string;
    interests: number | null;
    is_open: boolean;
}

const props = defineProps<{ opportunities: Paginated<Row>; filters: { type?: string; mine?: boolean | string }; types: Option[]; canPost: boolean }>();
const mine = props.filters.mine === true || props.filters.mine === '1';
const posting = ref(false);
const form = useForm({ type: '', title: '', description: '', areas: [] as string[], organization: '', closes_on: '' });
const submit = () => form.transform((d) => ({ ...d, closes_on: d.closes_on || null, organization: d.organization || null })).post(route('research.store'));
</script>

<template>
    <AppLayout title="Research">
        <PageHeader title="Research & collaboration" description="Projects, industry collaborations, guest lectures and consultancy between faculty and alumni.">
            <AppButton v-if="canPost" variant="secondary" :href="mine ? route('research.index') : route('research.index', { mine: 1 })">{{ mine ? 'All' : 'My posts' }}</AppButton>
            <AppButton v-if="canPost" @click="posting = true">Post an opportunity</AppButton>
        </PageHeader>
        <nav class="mb-6 flex flex-wrap gap-2" aria-label="Type">
            <Link :href="route('research.index')" :class="['rounded-md px-3 py-1.5 text-[13px] font-medium whitespace-nowrap transition-colors', !filters.type ? 'bg-brand-600 text-white shadow-sm' : 'bg-surface text-ink-soft ring-1 ring-line-strong']">All</Link>
            <Link
                v-for="t in types"
                :key="t.value"
                :href="route('research.index', { type: t.value })"
                :class="['rounded-md px-3 py-1.5 text-[13px] font-medium whitespace-nowrap transition-colors', filters.type === t.value ? 'bg-brand-600 text-white shadow-sm' : 'bg-surface text-ink-soft ring-1 ring-line-strong']"
                >{{ t.label }}</Link
            >
        </nav>
        <EmptyState v-if="opportunities.data.length === 0" title="No open opportunities" />
        <ul v-else class="space-y-3">
            <li v-for="o in opportunities.data" :key="o.id">
                <Link :href="route('research.show', o.id)" class="card block p-5 card-hover">
                    <p class="text-xs font-medium tracking-wide text-accent-600 uppercase">{{ o.type }}<span v-if="!o.is_open" class="text-muted"> · Closed</span></p>
                    <p class="mt-1 font-semibold text-ink">{{ o.title }}</p>
                    <p class="text-sm text-muted">{{ o.poster }}<template v-if="o.organization"> · {{ o.organization }}</template><template v-if="o.closes_on"> · closes {{ o.closes_on }}</template></p>
                    <div v-if="o.areas.length" class="mt-2 flex flex-wrap gap-1"><span v-for="a in o.areas" :key="a" class="rounded bg-surface-sunken px-2 py-0.5 text-xs text-ink-soft">{{ a }}</span></div>
                    <p v-if="o.interests !== null" class="mt-2 text-xs font-medium text-brand-700">{{ o.interests }} interested</p>
                </Link>
            </li>
        </ul>
        <PaginationNav class="mt-4" :links="opportunities.links" :from="opportunities.from" :to="opportunities.to" :total="opportunities.total" />

        <ModalDialog :show="posting" title="Post an opportunity" @close="posting = false">
            <form id="research-form" class="space-y-4" @submit.prevent="submit">
                <FormField label="Type" :error="form.errors.type" required><SelectInput v-model="form.type" :options="types" placeholder="Choose" /></FormField>
                <FormField label="Title" :error="form.errors.title" required><TextInput v-model="form.title" maxlength="200" /></FormField>
                <FormField label="Description" :error="form.errors.description" required><TextArea v-model="form.description" rows="5" /></FormField>
                <FormField label="Areas" :error="form.errors.areas"><TagInput v-model="form.areas" :max="10" placeholder="e.g. NLP, VLSI" /></FormField>
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField label="Organisation" :error="form.errors.organization"><TextInput v-model="form.organization" /></FormField>
                    <FormField label="Closes on" :error="form.errors.closes_on"><TextInput v-model="form.closes_on" type="date" /></FormField>
                </div>
            </form>
            <template #footer>
                <AppButton variant="secondary" @click="posting = false">Cancel</AppButton>
                <AppButton type="submit" form="research-form" :loading="form.processing">Post</AppButton>
            </template>
        </ModalDialog>
    </AppLayout>
</template>
