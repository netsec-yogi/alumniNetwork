<script setup lang="ts">
import AppButton from '@/Components/AppButton.vue';
import DataTable from '@/Components/DataTable.vue';
import DrawerPanel from '@/Components/DrawerPanel.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PaginationNav from '@/Components/PaginationNav.vue';
import SelectInput from '@/Components/SelectInput.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TabsNav from '@/Components/TabsNav.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Paginated } from '@/types';
import { router } from '@inertiajs/vue3';
import { KeyRound, Lock } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

interface Entry {
    id: number;
    at: string;
    user: { id: number; name: string; email: string } | null;
    action: string;
    label: string | null;
    module: string;
    entity: string | null;
    subject: string | null;
    status: 'success' | 'failed' | 'denied';
    reason: string | null;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    ip: string | null;
    user_agent: string | null;
    request_id: string | null;
}

const props = defineProps<{
    logs: Paginated<Entry>;
    filters: { module?: string; action?: string; user?: number; request_id?: string; from?: string; to?: string; status?: string; preset?: string };
    modules: string[];
}>();

const form = reactive({
    module: props.filters.module ?? '',
    action: props.filters.action ?? '',
    request_id: props.filters.request_id ?? '',
    status: props.filters.status ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
    preset: props.filters.preset ?? '',
});
const moduleOptions = computed(() => props.modules.map((m) => ({ value: m, label: m })));
const statusOptions = [
    { value: 'success', label: 'Succeeded' },
    { value: 'failed', label: 'Failed' },
    { value: 'denied', label: 'Refused' },
];
const tabs = computed(() => [
    { key: 'all', label: 'All entries', href: route('admin.audit-logs.index'), active: !props.filters.preset },
    { key: 'passwords', label: 'Password changes', icon: KeyRound, href: route('admin.audit-logs.index', { preset: 'passwords' }), active: props.filters.preset === 'passwords' },
]);

const apply = () => router.get(route('admin.audit-logs.index'), Object.fromEntries(Object.entries(form).filter(([, v]) => v)), { preserveState: true });
const selected = ref<Entry | null>(null);
const pretty = (v: unknown) => JSON.stringify(v, null, 2);
const statusLabel = { success: 'Succeeded', failed: 'Failed', denied: 'Refused' };
</script>

<template>
    <AppLayout title="Audit log">
        <PageHeader title="Audit log" description="Append-only record of security-relevant and administrative actions. Entries cannot be edited or deleted; passwords and tokens are never recorded." />

        <TabsNav :items="tabs" class="mb-4" />

        <DataTable :empty="logs.data.length === 0" empty-title="No entries match">
            <template #toolbar>
                <form class="grid w-full gap-2 sm:grid-cols-3 lg:grid-cols-7" role="search" @submit.prevent="apply">
                    <SelectInput v-model="form.module" :options="moduleOptions" placeholder="Any module" aria-label="Module" />
                    <TextInput v-model="form.action" placeholder="Action, e.g. login." aria-label="Action prefix" />
                    <SelectInput v-model="form.status" :options="statusOptions" placeholder="Any outcome" aria-label="Outcome" />
                    <TextInput v-model="form.request_id" placeholder="Request ID" aria-label="Request ID" />
                    <TextInput v-model="form.from" type="date" aria-label="From date" />
                    <TextInput v-model="form.to" type="date" aria-label="To date" />
                    <AppButton type="submit">Filter</AppButton>
                </form>
            </template>
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col">When</th>
                        <th scope="col">Administrator / actor</th>
                        <th scope="col">Affected</th>
                        <th scope="col">Action</th>
                        <th scope="col">Outcome</th>
                        <th scope="col"><span class="sr-only">Details</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="l in logs.data" :key="l.id">
                        <td class="whitespace-nowrap text-muted">{{ l.at }}</td>
                        <td>
                            <span class="block font-medium text-ink">{{ l.user?.name ?? 'System / guest' }}</span>
                            <span v-if="l.user" class="block text-xs text-muted">{{ l.user.email }}</span>
                        </td>
                        <td class="text-muted">{{ l.subject ?? l.entity ?? '—' }}</td>
                        <td>
                            <span v-if="l.label" class="block font-medium text-ink">{{ l.label }}</span>
                            <code class="rounded bg-surface-sunken px-1.5 py-0.5 text-xs">{{ l.action }}</code>
                        </td>
                        <td><StatusBadge :status="l.status === 'success' ? 'completed' : l.status === 'denied' ? 'rejected' : 'failed'" :label="statusLabel[l.status]" /></td>
                        <td class="text-right"><AppButton size="sm" variant="ghost" @click="selected = l">Details</AppButton></td>
                    </tr>
                </tbody>
            </table>
            <template #footer><PaginationNav :links="logs.links" :from="logs.from" :to="logs.to" :total="logs.total" /></template>
        </DataTable>

        <DrawerPanel :show="selected !== null" :title="selected?.label ?? selected?.action ?? 'Audit entry'" @close="selected = null">
            <template v-if="selected">
                <p class="mb-4 flex items-center gap-1.5 text-xs text-muted"><Lock :size="13" aria-hidden="true" />Read-only. Audit entries can't be changed or removed.</p>
                <dl class="space-y-3 text-sm">
                    <div><dt class="text-xs font-semibold text-muted">Administrator / actor</dt><dd class="text-ink">{{ selected.user ? `${selected.user.name} (${selected.user.email})` : 'System / guest' }}</dd></div>
                    <div><dt class="text-xs font-semibold text-muted">Affected</dt><dd class="text-ink">{{ selected.subject ?? selected.entity ?? '—' }}</dd></div>
                    <div><dt class="text-xs font-semibold text-muted">Action</dt><dd><code class="text-xs">{{ selected.action }}</code> · {{ selected.module }}</dd></div>
                    <div><dt class="text-xs font-semibold text-muted">Date and time</dt><dd class="text-ink">{{ selected.at }}</dd></div>
                    <div><dt class="text-xs font-semibold text-muted">Outcome</dt><dd class="text-ink">{{ statusLabel[selected.status] }}</dd></div>
                    <div v-if="selected.reason"><dt class="text-xs font-semibold text-muted">Reason</dt><dd class="whitespace-pre-line text-ink">{{ selected.reason }}</dd></div>
                    <div><dt class="text-xs font-semibold text-muted">IP address</dt><dd class="text-ink">{{ selected.ip ?? '—' }}</dd></div>
                    <div><dt class="text-xs font-semibold text-muted">Browser / device</dt><dd class="break-all text-ink">{{ selected.user_agent ?? '—' }}</dd></div>
                    <div><dt class="text-xs font-semibold text-muted">Request ID</dt><dd><code class="text-xs">{{ selected.request_id ?? '—' }}</code></dd></div>
                    <div v-if="selected.old_values"><dt class="text-xs font-semibold text-muted">Before</dt><dd><pre class="overflow-x-auto rounded-xl bg-surface-muted p-3 text-xs ring-1 ring-line">{{ pretty(selected.old_values) }}</pre></dd></div>
                    <div v-if="selected.new_values"><dt class="text-xs font-semibold text-muted">Details</dt><dd><pre class="overflow-x-auto rounded-xl bg-surface-muted p-3 text-xs ring-1 ring-line">{{ pretty(selected.new_values) }}</pre></dd></div>
                </dl>
            </template>
        </DrawerPanel>
    </AppLayout>
</template>
