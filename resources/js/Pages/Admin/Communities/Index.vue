<script setup lang="ts">
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
}

const props = defineProps<{ groups: Paginated<Row>; kinds: string[]; categories: Record<string, Record<string, string>> }>();

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
    </AppLayout>
</template>
