<script setup lang="ts">
import AppButton from '@/Components/AppButton.vue';
import CheckboxInput from '@/Components/CheckboxInput.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FormField from '@/Components/FormField.vue';
import JobCard, { type JobSummary } from '@/Components/JobCard.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PaginationNav from '@/Components/PaginationNav.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Option, Paginated } from '@/types';
import { router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps<{
    jobs: Paginated<JobSummary>;
    filters: { q?: string; type?: string; work_mode?: string; location?: string; referral?: boolean | string; mine?: boolean | string };
    options: { types: Option[]; workModes: Option[]; employmentTypes: Option[] };
    canPost: boolean;
}>();

const truthy = (v: unknown) => v === true || v === '1' || v === 1;
const form = reactive({
    q: props.filters.q ?? '',
    type: props.filters.type ?? '',
    work_mode: props.filters.work_mode ?? '',
    location: props.filters.location ?? '',
    referral: truthy(props.filters.referral),
});
const mine = truthy(props.filters.mine);

function search() {
    const query: Record<string, string | number> = {};
    for (const [k, v] of Object.entries(form)) if (v) query[k] = v === true ? 1 : (v as string);
    router.get(route('jobs.index'), query, { preserveState: true });
}
</script>

<template>
    <AppLayout title="Jobs & internships">
        <PageHeader title="Jobs & internships" description="Opportunities shared by IIITM alumni, faculty and recruiters.">
            <AppButton variant="secondary" :href="route('jobs.referrals')">Referral requests</AppButton>
            <AppButton v-if="canPost" variant="secondary" :href="mine ? route('jobs.index') : route('jobs.index', { mine: 1 })">{{ mine ? 'All postings' : 'My postings' }}</AppButton>
            <AppButton v-if="canPost" :href="route('jobs.create')">Post an opportunity</AppButton>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-[17rem_1fr]">
            <form v-if="!mine" class="card h-fit space-y-4 p-5" role="search" @submit.prevent="search">
                <FormField label="Keyword"><TextInput v-model="form.q" type="search" placeholder="Title, company or skill" /></FormField>
                <FormField label="Type"><SelectInput v-model="form.type" :options="options.types" placeholder="Jobs and internships" /></FormField>
                <FormField label="Work mode"><SelectInput v-model="form.work_mode" :options="options.workModes" placeholder="Any" /></FormField>
                <FormField label="Location"><TextInput v-model="form.location" /></FormField>
                <CheckboxInput v-model="form.referral" label="Referral available" />
                <AppButton type="submit" class="w-full">Search</AppButton>
            </form>

            <section :class="['space-y-4', mine ? 'lg:col-span-2' : '']">
                <EmptyState v-if="jobs.data.length === 0" :title="mine ? 'You haven’t posted anything yet' : 'No openings match'" />
                <JobCard v-for="j in jobs.data" :key="j.id" :job="j" :show-status="mine" />
                <PaginationNav :links="jobs.links" :from="jobs.from" :to="jobs.to" :total="jobs.total" />
            </section>
        </div>
    </AppLayout>
</template>
