<script setup lang="ts">
import AppButton from '@/Components/AppButton.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PaginationNav from '@/Components/PaginationNav.vue';
import SelectInput from '@/Components/SelectInput.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Option, Paginated } from '@/types';
import { Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

interface Row {
    id: number;
    name: string;
    email: string;
    roll_number: string;
    programme: string;
    graduation_year: number;
    company: string | null;
    location: string;
    status: string;
}

const props = defineProps<{
    alumni: Paginated<Row>;
    filters: Record<string, string | number | undefined>;
    programmes: Option<number>[];
    can: { export: boolean; import: boolean };
    exportLimit: number;
}>();

const form = reactive({
    q: (props.filters.q as string) ?? '',
    programme: (props.filters.programme as number) ?? '',
    year: (props.filters.year as number) ?? '',
    status: (props.filters.status as string) ?? '',
    company: (props.filters.company as string) ?? '',
    location: (props.filters.location as string) ?? '',
});
const query = computed(() => Object.fromEntries(Object.entries(form).filter(([, v]) => v !== '' && v !== null)) as Record<string, string>);
const apply = () => router.get(route('admin.alumni.index'), query.value, { preserveState: true });
const statusOptions = ['pending', 'verified', 'rejected', 'suspended', 'archived'].map((s) => ({ value: s, label: s[0].toUpperCase() + s.slice(1) }));
</script>

<template>
    <AppLayout title="Alumni">
        <PageHeader title="Alumni" :description="`${alumni.total.toLocaleString('en-IN')} records match`">
            <AppButton v-if="can.import" variant="secondary" :href="route('admin.alumni.import')">Import institute records</AppButton>
            <!-- A plain link: the download needs a real navigation, and may pass through password confirmation first. -->
            <a v-if="can.export" :href="route('admin.alumni.export', query)" class="inline-flex items-center rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Export CSV</a>
        </PageHeader>

        <form class="mb-6 grid gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200 sm:grid-cols-3 lg:grid-cols-7" role="search" @submit.prevent="apply">
            <TextInput v-model="form.q" type="search" placeholder="Name, email or roll no." aria-label="Search" class="lg:col-span-2" />
            <SelectInput v-model="form.programme" :options="programmes" placeholder="Any programme" aria-label="Programme" />
            <TextInput v-model.number="form.year" type="number" placeholder="Grad. year" aria-label="Graduation year" />
            <SelectInput v-model="form.status" :options="statusOptions" placeholder="Any status" aria-label="Status" />
            <TextInput v-model="form.company" placeholder="Company" aria-label="Company" />
            <AppButton type="submit">Filter</AppButton>
        </form>
        <p v-if="can.export" class="-mt-3 mb-4 text-xs text-slate-500">Exports are limited to {{ exportLimit.toLocaleString('en-IN') }} rows, need your password, and are recorded in the audit log.</p>

        <EmptyState v-if="alumni.data.length === 0" title="No alumni match" />
        <div v-else class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-600">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-medium">Name</th>
                        <th scope="col" class="px-4 py-3 font-medium">Roll no.</th>
                        <th scope="col" class="px-4 py-3 font-medium">Batch</th>
                        <th scope="col" class="px-4 py-3 font-medium">Company</th>
                        <th scope="col" class="px-4 py-3 font-medium">Location</th>
                        <th scope="col" class="px-4 py-3 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="a in alumni.data" :key="a.id" class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <Link :href="route('admin.alumni.show', a.id)" class="font-medium text-brand-800 hover:underline">{{ a.name }}</Link>
                            <p class="text-slate-500">{{ a.email }}</p>
                        </td>
                        <td class="px-4 py-3 font-mono text-xs">{{ a.roll_number }}</td>
                        <td class="px-4 py-3">{{ a.programme }} · {{ a.graduation_year }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ a.company ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ a.location || '—' }}</td>
                        <td class="px-4 py-3"><StatusBadge :status="a.status" /></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <PaginationNav class="mt-4" :links="alumni.links" :from="alumni.from" :to="alumni.to" :total="alumni.total" />
    </AppLayout>
</template>
