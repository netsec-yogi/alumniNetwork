<script setup lang="ts">
import AppButton from '@/Components/AppButton.vue';
import CardPanel from '@/Components/CardPanel.vue';
import CheckboxInput from '@/Components/CheckboxInput.vue';
import FormField from '@/Components/FormField.vue';
import ModalDialog from '@/Components/ModalDialog.vue';
import PageHeader from '@/Components/PageHeader.vue';
import SelectInput from '@/Components/SelectInput.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Programme {
    id: number;
    code: string;
    name: string;
    degree: string;
    duration_years: number;
    department_id: number | null;
    department: string | null;
    is_active: boolean;
    alumni: number;
}

const props = defineProps<{ departments: { id: number; code: string; name: string }[]; programmes: Programme[] }>();
const deptOptions = computed(() => props.departments.map((d) => ({ value: d.id, label: d.name })));

const editing = ref<Programme | 'new' | null>(null);
const form = useForm({ code: '', name: '', degree: '', duration_years: 4, department_id: '' as number | '', is_active: true });
function open(p: Programme | 'new') {
    editing.value = p;
    form.clearErrors();
    if (p === 'new') form.reset();
    else Object.assign(form, { code: p.code, name: p.name, degree: p.degree, duration_years: p.duration_years, department_id: p.department_id ?? '', is_active: p.is_active });
}
function save() {
    form.transform((d) => ({ ...d, department_id: d.department_id || null }));
    const opts = { preserveScroll: true, onSuccess: () => (editing.value = null) };
    if (editing.value === 'new') form.post(route('admin.programmes.store'), opts);
    else form.put(route('admin.programmes.update', (editing.value as Programme).id), opts);
}

const deptForm = useForm({ code: '', name: '' });
const addDept = () => deptForm.post(route('admin.departments.store'), { preserveScroll: true, onSuccess: () => deptForm.reset() });
</script>

<template>
    <AppLayout title="Programmes">
        <PageHeader title="Programmes & departments" description="Keep discontinued programmes (inactive) so older batches can still register.">
            <AppButton @click="open('new')">Add programme</AppButton>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
            <div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-600">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-medium">Programme</th>
                            <th scope="col" class="px-4 py-3 font-medium">Dept.</th>
                            <th scope="col" class="px-4 py-3 font-medium">Alumni</th>
                            <th scope="col" class="px-4 py-3 font-medium">Status</th>
                            <th scope="col" class="px-4 py-3"><span class="sr-only">Edit</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="p in programmes" :key="p.id">
                            <td class="px-4 py-3"><p class="font-medium">{{ p.name }}</p><p class="text-xs text-slate-500">{{ p.code }} · {{ p.degree }} · {{ p.duration_years }} yrs</p></td>
                            <td class="px-4 py-3">{{ p.department ?? '—' }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ p.alumni }}</td>
                            <td class="px-4 py-3"><StatusBadge :status="p.is_active ? 'active' : 'deactivated'" :label="p.is_active ? 'Active' : 'Inactive'" /></td>
                            <td class="px-4 py-3 text-right"><AppButton size="sm" variant="ghost" @click="open(p)">Edit</AppButton></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <CardPanel title="Departments">
                <ul class="mb-4 space-y-1 text-sm">
                    <li v-for="d in departments" :key="d.id"><span class="font-mono text-xs text-slate-500">{{ d.code }}</span> {{ d.name }}</li>
                </ul>
                <form class="space-y-3" @submit.prevent="addDept">
                    <FormField label="Code" :error="deptForm.errors.code"><TextInput v-model="deptForm.code" maxlength="20" /></FormField>
                    <FormField label="Name" :error="deptForm.errors.name"><TextInput v-model="deptForm.name" /></FormField>
                    <AppButton type="submit" size="sm" variant="secondary" :loading="deptForm.processing">Add department</AppButton>
                </form>
            </CardPanel>
        </div>

        <ModalDialog :show="editing !== null" :title="editing === 'new' ? 'Add programme' : 'Edit programme'" @close="editing = null">
            <form id="programme-form" class="grid gap-4 sm:grid-cols-2" @submit.prevent="save">
                <FormField label="Code" :error="form.errors.code" required><TextInput v-model="form.code" maxlength="30" /></FormField>
                <FormField label="Degree" :error="form.errors.degree" required><TextInput v-model="form.degree" placeholder="B.Tech." /></FormField>
                <div class="sm:col-span-2"><FormField label="Name" :error="form.errors.name" required><TextInput v-model="form.name" /></FormField></div>
                <FormField label="Duration (years)" :error="form.errors.duration_years"><TextInput v-model.number="form.duration_years" type="number" min="1" max="7" /></FormField>
                <FormField label="Department" :error="form.errors.department_id"><SelectInput v-model="form.department_id" :options="deptOptions" placeholder="—" /></FormField>
                <div class="sm:col-span-2"><CheckboxInput v-model="form.is_active" label="Open for new registrations" /></div>
            </form>
            <template #footer>
                <AppButton variant="secondary" @click="editing = null">Cancel</AppButton>
                <AppButton type="submit" form="programme-form" :loading="form.processing">Save</AppButton>
            </template>
        </ModalDialog>
    </AppLayout>
</template>
