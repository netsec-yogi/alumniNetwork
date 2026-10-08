<script setup lang="ts">
import CheckboxInput from '@/Components/CheckboxInput.vue';
import DataTable from '@/Components/DataTable.vue';
import { ask } from '@/lib/confirm';
import AppButton from '@/Components/AppButton.vue';
import FormField from '@/Components/FormField.vue';
import ModalDialog from '@/Components/ModalDialog.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PaginationNav from '@/Components/PaginationNav.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextArea from '@/Components/TextArea.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Paginated } from '@/types';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Row {
    id: number;
    slug: string;
    name: string;
    kind: string;
    category: string;
    join_policy: string;
    is_official: boolean;
    members_count: number;
    landing: { city: string | null; country: string | null; coordinator_name: string | null; show_on_landing: boolean } | null;
}

const props = defineProps<{ groups: Paginated<Row>; kinds: string[]; categories: Record<string, Record<string, string>> }>();

// Chapter details for the public landing page.
const editingLanding = ref<Row | null>(null);
const landingForm = useForm({ city: '', country: '', coordinator_name: '', show_on_landing: false });
function openLanding(g: Row) {
    editingLanding.value = g;
    landingForm.clearErrors();
    Object.assign(landingForm, { city: g.landing?.city ?? '', country: g.landing?.country ?? '', coordinator_name: g.landing?.coordinator_name ?? '', show_on_landing: g.landing?.show_on_landing ?? false });
}
const saveLanding = () =>
    landingForm
        .transform((d) => ({ ...d, city: d.city || null, country: d.country || null, coordinator_name: d.coordinator_name || null }))
        .put(route('admin.communities.landing', editingLanding.value!.slug), { preserveScroll: true, onSuccess: () => (editingLanding.value = null) });

const creating = ref(false);
const form = useForm({ kind: props.kinds[0], category: '', name: '', description: '', join_policy: 'open', admin_email: '' });
const categoryOptions = computed(() => Object.entries(props.categories[form.kind] ?? {}).map(([value, label]) => ({ value, label })));
const kindOptions = computed(() => props.kinds.map((k) => ({ value: k, label: k === 'chapter' ? 'Chapter' : 'Community' })));
const submit = () => form.post(route('admin.communities.store'), { onSuccess: () => ((creating.value = false), form.reset()) });
const archive = (g: Row) => ask(`Archive ${g.name}? Members will lose access to it.`).then((ok) => ok && router.delete(route('admin.communities.destroy', g.slug), { preserveScroll: true }));
</script>

<template>
    <AppLayout title="Communities">
        <PageHeader title="Communities & chapters" description="Official groups, and who runs them. Batch groups are created automatically.">
            <AppButton @click="creating = true">New group</AppButton>
        </PageHeader>

        <DataTable>
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Type</th>
                        <th scope="col">Joining</th>
                        <th scope="col">Members</th>
                        <th scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="g in groups.data" :key="g.id">
                        <td><Link :href="route('communities.show', g.slug)" class="font-medium text-brand-800 hover:underline">{{ g.name }}</Link></td>
                        <td class="text-muted">{{ g.kind === 'chapter' ? 'Chapter' : 'Community' }} · {{ g.category }}</td>
                        <td class="text-muted capitalize">{{ g.join_policy }}</td>
                        <td class="tabular-nums">{{ g.members_count }}</td>
                        <td class="text-right whitespace-nowrap">
                            <AppButton v-if="g.landing" size="sm" variant="ghost" @click="openLanding(g)">Landing page<span v-if="g.landing.show_on_landing" class="ml-1 size-1.5 rounded-full bg-emerald-500" aria-label="(shown)" /></AppButton>
                            <AppButton size="sm" variant="ghost" :href="route('communities.members', g.slug)">Members</AppButton>
                            <AppButton size="sm" variant="danger-ghost" @click="archive(g)">Archive</AppButton>
                        </td>
                    </tr>
                </tbody>
            </table>
            <template #footer><PaginationNav :links="groups.links" :from="groups.from" :to="groups.to" :total="groups.total" /></template>
        </DataTable>

        <ModalDialog :show="creating" title="New group" @close="creating = false">
            <form id="new-group" class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField label="Kind" :error="form.errors.kind"><SelectInput v-model="form.kind" :options="kindOptions" /></FormField>
                    <FormField label="Category" :error="form.errors.category" required><SelectInput v-model="form.category" :options="categoryOptions" placeholder="Choose" /></FormField>
                </div>
                <FormField label="Name" :error="form.errors.name" required><TextInput v-model="form.name" maxlength="120" /></FormField>
                <FormField label="Description" :error="form.errors.description"><TextArea v-model="form.description" rows="3" /></FormField>
                <FormField label="Joining" :error="form.errors.join_policy">
                    <SelectInput v-model="form.join_policy" :options="[{ value: 'open', label: 'Anyone in the network can join' }, { value: 'approval', label: 'Admins approve requests' }]" />
                </FormField>
                <FormField label="First admin (email)" :error="form.errors.admin_email" hint="An existing member who will run the group."><TextInput v-model="form.admin_email" type="email" /></FormField>
            </form>
            <template #footer>
                <AppButton variant="secondary" @click="creating = false">Cancel</AppButton>
                <AppButton type="submit" form="new-group" :loading="form.processing">Create</AppButton>
            </template>
        </ModalDialog>
        <ModalDialog :show="editingLanding !== null" :title="`${editingLanding?.name} on the landing page`" description="Only what you enter here is shown publicly." @close="editingLanding = null">
            <form id="landing-form" class="grid gap-4 sm:grid-cols-2" @submit.prevent="saveLanding">
                <FormField label="City" :error="landingForm.errors.city"><TextInput v-model="landingForm.city" placeholder="e.g. Bengaluru" /></FormField>
                <FormField label="Country" :error="landingForm.errors.country"><TextInput v-model="landingForm.country" placeholder="e.g. India" /></FormField>
                <div class="sm:col-span-2">
                    <FormField label="Coordinator (public)" :error="landingForm.errors.coordinator_name" hint="Shown to everyone. Leave empty to show no coordinator."><TextInput v-model="landingForm.coordinator_name" /></FormField>
                </div>
                <div class="sm:col-span-2"><CheckboxInput v-model="landingForm.show_on_landing" label="Show this chapter on the public landing page" /></div>
            </form>
            <template #footer>
                <AppButton variant="secondary" @click="editingLanding = null">Cancel</AppButton>
                <AppButton type="submit" form="landing-form" :loading="landingForm.processing">Save</AppButton>
            </template>
        </ModalDialog>
    </AppLayout>
</template>
