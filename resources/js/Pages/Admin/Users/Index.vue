<script setup lang="ts">
import AppButton from '@/Components/AppButton.vue';
import CheckboxInput from '@/Components/CheckboxInput.vue';
import ConfirmsPassword from '@/Components/ConfirmsPassword.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FormField from '@/Components/FormField.vue';
import ModalDialog from '@/Components/ModalDialog.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PaginationNav from '@/Components/PaginationNav.vue';
import SelectInput from '@/Components/SelectInput.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextArea from '@/Components/TextArea.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Option, Paginated } from '@/types';
import { router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

interface Row {
    id: number;
    name: string;
    email: string;
    status: string;
    roles: string[];
    two_factor: boolean;
    locked: boolean;
    lock_reason: string | null;
    last_login_at: string | null;
    can_manage: boolean;
    can_assign_roles: boolean;
}

const props = defineProps<{
    users: Paginated<Row>;
    filters: { q?: string; role?: string; status?: string };
    roleOptions: Option[];
    assignableRoles: string[];
    canCreate: boolean;
}>();

const roleLabel = (r: string) => props.roleOptions.find((o) => o.value === r)?.label ?? r;
const assignableOptions = computed(() => props.roleOptions.filter((o) => props.assignableRoles.includes(o.value)));
const statusOptions = [
    { value: 'active', label: 'Active' },
    { value: 'suspended', label: 'Suspended' },
    { value: 'deactivated', label: 'Deactivated' },
    { value: 'locked', label: 'Locked out' },
];

const filters = reactive({ q: props.filters.q ?? '', role: props.filters.role ?? '', status: props.filters.status ?? '' });
const applyFilters = () =>
    router.get(route('admin.users.index'), Object.fromEntries(Object.entries(filters).filter(([, v]) => v)), { preserveState: true, preserveScroll: true });

// One modal at a time; `kind` decides which form it shows.
type Kind = 'roles' | 'suspend' | 'reset2fa' | 'create';
const modal = ref<{ kind: Kind; user?: Row } | null>(null);
const rolesForm = useForm({ roles: [] as string[] });
const reasonForm = useForm({ status: 'suspended', reason: '' });
const createForm = useForm({ name: '', email: '', roles: [] as string[] });

function open(kind: Kind, user?: Row) {
    modal.value = { kind, user };
    if (kind === 'roles' && user) rolesForm.roles = user.roles.filter((r) => props.assignableRoles.includes(r));
}

function close() {
    modal.value = null;
    rolesForm.reset().clearErrors();
    reasonForm.reset().clearErrors();
    createForm.reset().clearErrors();
}

const opts = { preserveScroll: true, onSuccess: close };

function submitModal() {
    const m = modal.value!;
    if (m.kind === 'roles') rolesForm.put(route('admin.users.roles', m.user!.id), opts);
    if (m.kind === 'suspend') reasonForm.put(route('admin.users.status', m.user!.id), opts);
    if (m.kind === 'reset2fa') reasonForm.post(route('admin.users.reset-two-factor', m.user!.id), opts);
    if (m.kind === 'create') createForm.post(route('admin.users.store'), opts);
}

const reactivate = (u: Row) => router.put(route('admin.users.status', u.id), { status: 'active' }, { preserveScroll: true });
const unlock = (u: Row) => router.post(route('admin.users.unlock', u.id), {}, { preserveScroll: true });

const modalTitle = computed(
    () =>
        ({
            roles: `Roles for ${modal.value?.user?.name}`,
            suspend: `Suspend ${modal.value?.user?.name}`,
            reset2fa: `Reset 2FA for ${modal.value?.user?.name}`,
            create: 'Add a user',
        })[modal.value?.kind ?? 'create'],
);
</script>

<template>
    <AppLayout title="Users">
        <PageHeader title="Users" description="Accounts, roles and access.">
            <ConfirmsPassword v-if="canCreate" @confirmed="open('create')">
                <AppButton>Add user</AppButton>
            </ConfirmsPassword>
        </PageHeader>

        <form class="mb-6 grid gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200 sm:grid-cols-[1fr_12rem_12rem_auto]" role="search" @submit.prevent="applyFilters">
            <TextInput v-model="filters.q" type="search" placeholder="Name or email" aria-label="Search users" />
            <SelectInput v-model="filters.role" :options="roleOptions" placeholder="Any role" aria-label="Role" />
            <SelectInput v-model="filters.status" :options="statusOptions" placeholder="Any status" aria-label="Status" />
            <AppButton type="submit">Filter</AppButton>
        </form>

        <EmptyState v-if="users.data.length === 0" title="No users match" />

        <div v-else class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-600">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-medium">User</th>
                        <th scope="col" class="px-4 py-3 font-medium">Roles</th>
                        <th scope="col" class="px-4 py-3 font-medium">Status</th>
                        <th scope="col" class="px-4 py-3 font-medium">2FA</th>
                        <th scope="col" class="px-4 py-3 font-medium">Last sign-in</th>
                        <th scope="col" class="px-4 py-3"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="u in users.data" :key="u.id">
                        <td class="px-4 py-3">
                            <p class="font-medium text-slate-900">{{ u.name }}</p>
                            <p class="text-slate-500">{{ u.email }}</p>
                        </td>
                        <td class="px-4 py-3">
                            <span v-for="r in u.roles" :key="r" class="mr-1 mb-1 inline-block rounded bg-brand-50 px-1.5 py-0.5 text-xs text-brand-800">{{ roleLabel(r) }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <StatusBadge :status="u.status" />
                            <StatusBadge v-if="u.locked" status="locked" class="ml-1" :title="u.lock_reason ?? undefined" />
                        </td>
                        <td class="px-4 py-3">{{ u.two_factor ? 'On' : '—' }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ u.last_login_at ?? 'Never' }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <template v-if="u.can_manage || u.can_assign_roles">
                                <ConfirmsPassword v-if="u.can_assign_roles" @confirmed="open('roles', u)">
                                    <AppButton size="sm" variant="ghost">Roles</AppButton>
                                </ConfirmsPassword>
                                <template v-if="u.can_manage">
                                    <AppButton v-if="u.locked" size="sm" variant="ghost" @click="unlock(u)">Unlock</AppButton>
                                    <ConfirmsPassword v-if="u.two_factor" @confirmed="open('reset2fa', u)">
                                        <AppButton size="sm" variant="ghost">Reset 2FA</AppButton>
                                    </ConfirmsPassword>
                                    <AppButton v-if="u.status === 'active'" size="sm" variant="ghost" class="text-red-700" @click="open('suspend', u)">Suspend</AppButton>
                                    <AppButton v-else size="sm" variant="ghost" @click="reactivate(u)">Reactivate</AppButton>
                                </template>
                            </template>
                            <span v-else class="text-xs text-slate-400">No access</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <PaginationNav class="mt-4" :links="users.links" :from="users.from" :to="users.to" :total="users.total" />

        <ModalDialog :show="modal !== null" :title="modalTitle" @close="close">
            <form id="user-modal-form" class="space-y-4" @submit.prevent="submitModal">
                <template v-if="modal?.kind === 'roles'">
                    <p class="text-sm text-slate-600">You can grant only roles whose permissions you hold yourself. Changes are audited.</p>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <CheckboxInput v-for="o in assignableOptions" :key="o.value" v-model="rolesForm.roles" :value="o.value" :label="o.label" />
                    </div>
                    <p v-if="rolesForm.errors.roles" class="text-sm text-red-600">{{ rolesForm.errors.roles }}</p>
                </template>

                <template v-else-if="modal?.kind === 'suspend' || modal?.kind === 'reset2fa'">
                    <p class="text-sm text-slate-600">
                        {{
                            modal.kind === 'suspend'
                                ? 'They will be signed out everywhere and unable to sign in until reactivated.'
                                : 'Their authenticator and recovery codes are removed and they are signed out; they must enrol again at next sign-in. Confirm their identity first.'
                        }}
                    </p>
                    <FormField label="Reason (recorded in the audit log)" :error="reasonForm.errors.reason" required>
                        <TextArea v-model="reasonForm.reason" rows="3" />
                    </FormField>
                </template>

                <template v-else-if="modal?.kind === 'create'">
                    <p class="text-sm text-slate-600">For staff, faculty and students. They receive an email link to set their own password.</p>
                    <FormField label="Full name" :error="createForm.errors.name" required>
                        <TextInput v-model="createForm.name" required />
                    </FormField>
                    <FormField label="Email address" :error="createForm.errors.email" required>
                        <TextInput v-model="createForm.email" type="email" required />
                    </FormField>
                    <fieldset>
                        <legend class="text-sm font-medium text-slate-700">Roles</legend>
                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                            <CheckboxInput v-for="o in assignableOptions" :key="o.value" v-model="createForm.roles" :value="o.value" :label="o.label" />
                        </div>
                        <p v-if="createForm.errors.roles" class="mt-1 text-sm text-red-600">{{ createForm.errors.roles }}</p>
                    </fieldset>
                </template>
            </form>
            <template #footer>
                <AppButton variant="secondary" @click="close">Cancel</AppButton>
                <AppButton
                    type="submit"
                    form="user-modal-form"
                    :variant="modal?.kind === 'suspend' || modal?.kind === 'reset2fa' ? 'danger' : 'primary'"
                    :loading="rolesForm.processing || reasonForm.processing || createForm.processing"
                    >Save</AppButton
                >
            </template>
        </ModalDialog>
    </AppLayout>
</template>
