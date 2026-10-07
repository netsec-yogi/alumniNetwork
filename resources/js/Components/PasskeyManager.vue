<script setup lang="ts">
import { ask } from '@/lib/confirm';
import { toast } from '@/lib/toast';
import { router } from '@inertiajs/vue3';
import { usePasskeyRegister } from '@laravel/passkeys/vue';
import { KeyRound, Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import AlertBox from './AlertBox.vue';
import AppButton from './AppButton.vue';
import CardPanel from './CardPanel.vue';
import ConfirmsPassword from './ConfirmsPassword.vue';
import EmptyState from './EmptyState.vue';
import FormField from './FormField.vue';
import ModalDialog from './ModalDialog.vue';
import TextInput from './TextInput.vue';

/**
 * Passkeys (SRS 10): add, list, remove. The browser does the WebAuthn
 * ceremony via @laravel/passkeys; adding and removing need a recently
 * confirmed password (enforced server-side too).
 */
defineProps<{ passkeys: { id: number; name: string; added: string; last_used: string | null }[] }>();

const naming = ref(false);
const name = ref('');
const { register, isLoading, error, isSupported } = usePasskeyRegister({
    onSuccess: () => {
        naming.value = false;
        name.value = '';
        toast('Passkey added. You can now sign in with it.');
        router.reload({ only: ['passkeys'] });
    },
});

function startAdding() {
    name.value = guessDeviceName();
    naming.value = true;
}

function guessDeviceName(): string {
    const ua = navigator.userAgent;
    const os = /iPhone|iPad/.test(ua) ? 'iPhone/iPad' : /Android/.test(ua) ? 'Android' : /Mac/.test(ua) ? 'Mac' : /Windows/.test(ua) ? 'Windows PC' : /Linux/.test(ua) ? 'Linux' : 'This device';
    return os;
}

async function remove(p: { id: number; name: string }) {
    if (!(await ask(`Remove the passkey “${p.name}”? You won't be able to sign in with it any more.`))) return;
    router.delete(route('passkey.destroy', p.id), { preserveScroll: true, onSuccess: () => toast('Passkey removed.') });
}
</script>

<template>
    <CardPanel title="Passkeys" description="Sign in with your fingerprint, face or device PIN — no password or code needed." :icon="KeyRound">
        <template #actions>
            <ConfirmsPassword v-if="isSupported" @confirmed="startAdding">
                <AppButton size="sm" variant="secondary" :icon="Plus">Add a passkey</AppButton>
            </ConfirmsPassword>
        </template>

        <AlertBox v-if="!isSupported" tone="warning" class="mb-4">This browser doesn't support passkeys. Try a recent version of Chrome, Safari, Edge or Firefox.</AlertBox>

        <EmptyState
            v-if="passkeys.length === 0"
            compact
            :icon="KeyRound"
            title="No passkeys yet"
            description="A passkey lives on your phone or computer and can't be phished or reused on another site."
        />
        <ul v-else class="divide-y divide-line-soft">
            <li v-for="p in passkeys" :key="p.id" class="flex items-center gap-3 py-3">
                <span class="grid size-9 shrink-0 place-items-center rounded-md bg-surface-sunken text-muted"><KeyRound :size="17" aria-hidden="true" /></span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-ink">{{ p.name }}</p>
                    <p class="text-xs text-muted">Added {{ p.added }} · {{ p.last_used ? `last used ${p.last_used}` : 'not used yet' }}</p>
                </div>
                <ConfirmsPassword @confirmed="remove(p)">
                    <AppButton variant="danger-ghost" size="sm" :icon="Trash2" :aria-label="`Remove ${p.name}`" />
                </ConfirmsPassword>
            </li>
        </ul>

        <ModalDialog :show="naming" title="Add a passkey" description="Give it a name so you can recognise it later." size="sm" @close="naming = false">
            <form id="passkey-form" @submit.prevent="register(name.trim())">
                <FormField label="Name" :error="error ?? undefined" required>
                    <TextInput v-model="name" maxlength="60" required autofocus placeholder="e.g. Work laptop" />
                </FormField>
                <p class="mt-3 text-xs text-muted">Your browser will ask you to confirm with your fingerprint, face or device PIN.</p>
            </form>
            <template #footer>
                <AppButton variant="secondary" @click="naming = false">Cancel</AppButton>
                <AppButton type="submit" form="passkey-form" :loading="isLoading" :disabled="!name.trim()">Continue</AppButton>
            </template>
        </ModalDialog>
    </CardPanel>
</template>
