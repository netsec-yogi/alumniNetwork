<script setup lang="ts">
import DataTable from '@/Components/DataTable.vue';
import { Download } from 'lucide-vue-next';
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
            <AppButton v-if="can.export" external :href="route('admin.alumni.export', query)" :icon="Download">Export CSV</AppButton>
        </PageHeader>

        <p v-if="can.export" class="-mt-3 mb-4 text-xs text-muted">Exports are limited to {{ exportLimit.toLocaleString('en-IN') }} rows, need your password, and are recorded in the audit log.</p>

        <DataTable :empty="alumni.data.length === 0" empty-title="No alumni match" empty-description="Try a broader search or clear the filters.">
            <template #toolbar>
                <form class="grid w-full gap-2 sm:grid-cols-3 lg:grid-cols-7" role="search" @submit.prevent="apply">
                    <TextInput v-model="form.q" type="search" placeholder="Name, email or roll no." aria-label="Search" class="lg:col-span-2" />
                    <SelectInput v-model="form.programme" :options="programmes" placeholder="Any programme" aria-label="Programme" />
                    <TextInput v-model.number="form.year" type="number" placeholder="Grad. year" aria-label="Graduation year" />
                    <SelectInput v-model="form.status" :options="statusOptions" placeholder="Any status" aria-label="Status" />
                    <TextInput v-model="form.company" placeholder="Company" aria-label="Company" />
                    <AppButton type="submit">Filter</AppButton>
                </form>
            </template>
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Roll no.</th>
                        <th scope="col">Batch</th>
                        <th scope="col">Company</th>
                        <th scope="col">Location</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="a in alumni.data" :key="a.id" class="hover:bg-surface-muted">
                        <td>
                            <Link :href="route('admin.alumni.show', a.id)" class="font-medium text-brand-800 hover:underline">{{ a.name }}</Link>
                            <p class="text-muted">{{ a.email }}</p>
                        </td>
                        <td class="font-mono text-xs">{{ a.roll_number }}</td>
                        <td>{{ a.programme }} · {{ a.graduation_year }}</td>
                        <td class="text-muted">{{ a.company ?? '—' }}</td>
                        <td class="text-muted">{{ a.location || '—' }}</td>
                        <td><StatusBadge :status="a.status" /></td>
                    </tr>
                </tbody>
            </table>
            <template #footer><PaginationNav :links="alumni.links" :from="alumni.from" :to="alumni.to" :total="alumni.total" /></template>
        </DataTable>
    </AppLayout>
</template>
