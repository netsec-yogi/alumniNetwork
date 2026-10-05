<script setup lang="ts">
import AppButton from '@/Components/AppButton.vue';
import CardPanel from '@/Components/CardPanel.vue';
import FormField from '@/Components/FormField.vue';
import ModalDialog from '@/Components/ModalDialog.vue';
import PageHeader from '@/Components/PageHeader.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Session {
    key: string;
    ip_address: string | null;
    agent: string;
    is_current: boolean;
    last_active: string;
}

defineProps<{ sessions: Session[] }>();

// null = closed, 'others' = sign out all others, otherwise a session key.
const target = ref<string | null>(null);
const form = useForm({ current_password: '' });

function submit() {
    const url = target.value === 'others' ? route('profile.sessions.destroy-others') : route('profile.sessions.destroy', target.value!);
    form.delete(url, { preserveScroll: true, onSuccess: close });
}

function close() {
    target.value = null;
    form.reset();
    form.clearErrors();
}
</script>

<template>
    <AppLayout title="Active sessions">
        <PageHeader title="Active sessions" description="Devices currently signed in to your account. Sign out any you don't recognise, then change your password.">
            <AppButton variant="secondary" :href="route('profile.security')">← Security</AppButton>
        </PageHeader>

        <CardPanel>
            <template #actions>
                <AppButton v-if="sessions.length > 1" variant="danger" size="sm" @click="target = 'others'">Sign out all other devices</AppButton>
            </template>
            <ul class="divide-y divide-slate-100">
                <li v-for="s in sessions" :key="s.key" class="flex flex-wrap items-center justify-between gap-3 py-3">
                    <div>
                        <p class="text-sm font-medium text-slate-900">
                            {{ s.agent }}
                            <span v-if="s.is_current" class="ml-2 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">This device</span>
                        </p>
                        <p class="text-sm text-slate-500">{{ s.ip_address ?? 'Unknown IP' }} · active {{ s.last_active }}</p>
                    </div>
                    <AppButton v-if="!s.is_current" variant="secondary" size="sm" @click="target = s.key">Sign out</AppButton>
                </li>
            </ul>
        </CardPanel>

        <ModalDialog :show="target !== null" :title="target === 'others' ? 'Sign out all other devices' : 'Sign out device'" @close="close">
            <form id="session-form" @submit.prevent="submit">
                <FormField label="Confirm with your password" :error="form.errors.current_password">
                    <TextInput v-model="form.current_password" type="password" autocomplete="current-password" required />
                </FormField>
            </form>
            <template #footer>
                <AppButton variant="secondary" @click="close">Cancel</AppButton>
                <AppButton type="submit" form="session-form" variant="danger" :loading="form.processing">Sign out</AppButton>
            </template>
        </ModalDialog>
    </AppLayout>
</template>
