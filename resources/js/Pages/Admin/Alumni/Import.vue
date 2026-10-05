<script setup lang="ts">
import AlertBox from '@/Components/AlertBox.vue';
import AppButton from '@/Components/AppButton.vue';
import CardPanel from '@/Components/CardPanel.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { router, useForm } from '@inertiajs/vue3';

interface Preview {
    id: number;
    file: string;
    total: number;
    valid: number;
    new: number;
    updates: number;
    errors: { row: number; message: string }[] | null;
    sample: Record<string, string | number | null>[];
}

defineProps<{
    columns: string[];
    required: string[];
    programmeCodes: string[];
    preview: Preview | null;
    history: { id: number; file: string; status: string; by: string | null; total: number; valid: number; created: number; updated: number; at: string; errors: { message: string }[] | null }[];
}>();

const form = useForm<{ file: File | null }>({ file: null });
const upload = () => form.post(route('admin.alumni.import.preview'), { forceFormData: true, onSuccess: () => form.reset() });
const confirmImport = (id: number) => router.post(route('admin.alumni.import.confirm', id));
const badge = (s: string) => ({ completed: 'verified', failed: 'rejected', processing: 'pending', queued: 'pending' })[s] ?? 'deactivated';
</script>

<template>
    <AppLayout title="Import institute records">
        <PageHeader title="Import institute records" description="Graduate records from the academic section. Registrations are verified automatically against these.">
            <AppButton variant="ghost" :href="route('admin.alumni.index')">← Alumni</AppButton>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
            <div class="space-y-6">
                <CardPanel title="1. Upload a CSV">
                    <form class="space-y-4" @submit.prevent="upload">
                        <input
                            type="file"
                            accept=".csv,text/csv"
                            class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-brand-800"
                            aria-label="CSV file"
                            @change="form.file = ($event.target as HTMLInputElement).files?.[0] ?? null"
                        />
                        <p v-if="form.errors.file" class="text-sm text-red-600">{{ form.errors.file }}</p>
                        <AppButton type="submit" :disabled="!form.file" :loading="form.processing">Validate</AppButton>
                        <p class="text-xs text-slate-500">Nothing is written until you confirm. Up to 5 MB / 20,000 rows. Existing roll numbers are updated.</p>
                    </form>
                </CardPanel>

                <CardPanel v-if="preview" :title="`2. Review “${preview.file}”`">
                    <dl class="mb-4 grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                        <div><dt class="text-slate-500">Rows</dt><dd class="text-xl font-semibold tabular-nums">{{ preview.total }}</dd></div>
                        <div><dt class="text-slate-500">Valid</dt><dd class="text-xl font-semibold text-emerald-700 tabular-nums">{{ preview.valid }}</dd></div>
                        <div><dt class="text-slate-500">New</dt><dd class="text-xl font-semibold tabular-nums">{{ preview.new }}</dd></div>
                        <div><dt class="text-slate-500">Updates</dt><dd class="text-xl font-semibold tabular-nums">{{ preview.updates }}</dd></div>
                    </dl>
                    <AlertBox v-if="preview.errors?.length" tone="warning" :title="`${preview.total - preview.valid} rows will be skipped`" class="mb-4">
                        <ul class="mt-1 max-h-48 list-disc space-y-0.5 overflow-y-auto pl-5">
                            <li v-for="(e, i) in preview.errors" :key="i">Row {{ e.row }}: {{ e.message }}</li>
                        </ul>
                    </AlertBox>
                    <div v-if="preview.sample.length" class="mb-4 overflow-x-auto">
                        <table class="min-w-full text-xs">
                            <thead><tr class="text-left text-slate-500"><th v-for="c in ['roll_number', 'name', 'graduation_year', 'email']" :key="c" class="py-1 pr-4 font-medium">{{ c }}</th></tr></thead>
                            <tbody>
                                <tr v-for="(r, i) in preview.sample" :key="i" class="border-t border-slate-100">
                                    <td class="py-1 pr-4 font-mono">{{ r.roll_number }}</td><td class="py-1 pr-4">{{ r.name }}</td><td class="py-1 pr-4">{{ r.graduation_year }}</td><td class="py-1 pr-4">{{ r.email ?? '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <AppButton :disabled="preview.valid === 0" @click="confirmImport(preview.id)">Import {{ preview.valid }} rows</AppButton>
                </CardPanel>

                <CardPanel title="Recent imports">
                    <p v-if="history.length === 0" class="text-sm text-slate-500">None yet.</p>
                    <ul v-else class="divide-y divide-slate-100 text-sm">
                        <li v-for="h in history" :key="h.id" class="flex flex-wrap items-center justify-between gap-2 py-2">
                            <span><span class="font-medium">{{ h.file }}</span> <span class="text-slate-500">· {{ h.by }} · {{ h.at }}</span></span>
                            <span class="flex items-center gap-2 text-slate-600">
                                <template v-if="h.status === 'completed'">+{{ h.created }} new, {{ h.updated }} updated</template>
                                <template v-else-if="h.status === 'previewed'">{{ h.valid }}/{{ h.total }} valid, not imported</template>
                                <StatusBadge :status="badge(h.status)" :label="h.status" />
                            </span>
                            <p v-if="h.errors" class="w-full text-xs text-red-700">{{ h.errors[0]?.message }}</p>
                        </li>
                    </ul>
                </CardPanel>
            </div>

            <CardPanel title="File format">
                <p class="text-sm text-slate-600">First row is the header. Columns:</p>
                <ul class="mt-2 space-y-1 text-sm">
                    <li v-for="c in columns" :key="c"><code>{{ c }}</code><span v-if="required.includes(c)" class="text-red-600"> *</span></li>
                </ul>
                <p class="mt-3 text-xs text-slate-500">Programme codes: {{ programmeCodes.join(', ') }}. Dates as YYYY-MM-DD.</p>
            </CardPanel>
        </div>
    </AppLayout>
</template>
