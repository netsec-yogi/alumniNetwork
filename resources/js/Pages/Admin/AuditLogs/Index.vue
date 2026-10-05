<script setup lang="ts">
import AppButton from '@/Components/AppButton.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PaginationNav from '@/Components/PaginationNav.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Paginated } from '@/types';
import { router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

interface Entry {
    id: number;
    at: string;
    user: { id: number; name: string; email: string } | null;
    action: string;
    module: string;
    entity: string | null;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    ip: string | null;
    user_agent: string | null;
    request_id: string | null;
}

const props = defineProps<{
    logs: Paginated<Entry>;
    filters: { module?: string; action?: string; user?: number; request_id?: string; from?: string; to?: string };
    modules: string[];
}>();

const form = reactive({
    module: props.filters.module ?? '',
    action: props.filters.action ?? '',
    request_id: props.filters.request_id ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
});
const moduleOptions = computed(() => props.modules.map((m) => ({ value: m, label: m })));
const expanded = ref<number | null>(null);

const apply = () => router.get(route('admin.audit-logs.index'), Object.fromEntries(Object.entries(form).filter(([, v]) => v)), { preserveState: true });
const pretty = (v: unknown) => JSON.stringify(v, null, 2);
</script>

<template>
    <AppLayout title="Audit log">
        <PageHeader title="Audit log" description="Append-only record of security-relevant and administrative actions." />

        <form class="mb-6 grid gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200 sm:grid-cols-3 lg:grid-cols-6" role="search" @submit.prevent="apply">
            <SelectInput v-model="form.module" :options="moduleOptions" placeholder="Any module" aria-label="Module" />
            <TextInput v-model="form.action" placeholder="Action, e.g. login." aria-label="Action prefix" />
            <TextInput v-model="form.request_id" placeholder="Request ID" aria-label="Request ID" />
            <TextInput v-model="form.from" type="date" aria-label="From date" />
            <TextInput v-model="form.to" type="date" aria-label="To date" />
            <AppButton type="submit">Filter</AppButton>
        </form>

        <EmptyState v-if="logs.data.length === 0" title="No entries match" />

        <div v-else class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-600">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-medium">When</th>
                        <th scope="col" class="px-4 py-3 font-medium">Actor</th>
                        <th scope="col" class="px-4 py-3 font-medium">Action</th>
                        <th scope="col" class="px-4 py-3 font-medium">Subject</th>
                        <th scope="col" class="px-4 py-3 font-medium">IP</th>
                        <th scope="col" class="px-4 py-3"><span class="sr-only">Details</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <template v-for="l in logs.data" :key="l.id">
                        <tr>
                            <td class="px-4 py-2.5 whitespace-nowrap text-slate-600">{{ l.at }}</td>
                            <td class="px-4 py-2.5">{{ l.user?.name ?? 'System / guest' }}</td>
                            <td class="px-4 py-2.5"><code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs">{{ l.action }}</code></td>
                            <td class="px-4 py-2.5 text-slate-600">{{ l.entity ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-slate-600">{{ l.ip ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-right">
                                <button type="button" class="text-brand-700 hover:underline" :aria-expanded="expanded === l.id" @click="expanded = expanded === l.id ? null : l.id">
                                    {{ expanded === l.id ? 'Hide' : 'Details' }}
                                </button>
                            </td>
                        </tr>
                        <tr v-if="expanded === l.id" class="bg-slate-50">
                            <td colspan="6" class="px-4 py-3">
                                <div class="grid gap-4 text-xs md:grid-cols-2">
                                    <div v-if="l.old_values"><p class="mb-1 font-medium text-slate-600">Before</p><pre class="overflow-x-auto rounded bg-white p-2 ring-1 ring-slate-200">{{ pretty(l.old_values) }}</pre></div>
                                    <div v-if="l.new_values"><p class="mb-1 font-medium text-slate-600">After</p><pre class="overflow-x-auto rounded bg-white p-2 ring-1 ring-slate-200">{{ pretty(l.new_values) }}</pre></div>
                                    <div class="md:col-span-2 space-y-0.5 text-slate-500">
                                        <p>Module: {{ l.module }}<template v-if="l.user"> · Actor: {{ l.user.email }}</template></p>
                                        <p>Request ID: <code>{{ l.request_id ?? '—' }}</code></p>
                                        <p class="break-all">User agent: {{ l.user_agent ?? '—' }}</p>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        <PaginationNav class="mt-4" :links="logs.links" :from="logs.from" :to="logs.to" :total="logs.total" />
    </AppLayout>
</template>
