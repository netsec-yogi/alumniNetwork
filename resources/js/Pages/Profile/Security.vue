<script setup lang="ts">
import AlertBox from '@/Components/AlertBox.vue';
import AppButton from '@/Components/AppButton.vue';
import CardPanel from '@/Components/CardPanel.vue';
import ConfirmsPassword from '@/Components/ConfirmsPassword.vue';
import FormField from '@/Components/FormField.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PasskeyManager from '@/Components/PasskeyManager.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    twoFactor: {
        enabled: boolean;
        required: boolean;
        pendingConfirmation: boolean;
        qrCodeSvg: string | null;
        setupKey: string | null;
        recoveryCodes: string[] | null;
    };
    passwordChangedAt: string | null;
    passkeys: { id: number; name: string; added: string; last_used: string | null }[];
    social: { provider: string; label: string; email: string | null; linked: boolean; last_used: string | null }[];
    status: string | null;
}>();

const page = usePage();
const twoFactorError = computed(() => page.props.errors.two_factor);
const busy = ref(false);

const confirmForm = useForm({ code: '' });
const passwordForm = useForm({ current_password: '', password: '', password_confirmation: '' });

const visit = (method: 'post' | 'delete', name: string, params?: Record<string, string>) => {
    busy.value = true;
    router[method](route(name, params), {}, { preserveScroll: true, onFinish: () => (busy.value = false) });
};

const confirmTwoFactor = () =>
    confirmForm.post(route('two-factor.confirm'), {
        errorBag: 'confirmTwoFactorAuthentication',
        preserveScroll: true,
        onSuccess: () => confirmForm.reset(),
    });

const updatePassword = () =>
    passwordForm.put(route('user-password.update'), {
        errorBag: 'updatePassword',
        preserveScroll: true,
        onFinish: () => passwordForm.reset(),
    });
</script>

<template>
    <AppLayout title="Security">
        <PageHeader title="Security" description="Your password, two-factor authentication and signed-in devices.">
            <AppButton variant="secondary" :href="route('profile.sessions')">Active sessions</AppButton>
        </PageHeader>

        <div class="space-y-6">
            <AlertBox v-if="twoFactor.required && !twoFactor.enabled" tone="warning" title="Two-factor authentication is required for your role">
                Set it up below to continue using the portal.
            </AlertBox>

            <CardPanel title="Two-factor authentication" description="A code from an authenticator app (Google Authenticator, Microsoft Authenticator, Authy…) in addition to your password.">
                <template #actions>
                    <StatusBadge :status="twoFactor.enabled ? 'active' : twoFactor.pendingConfirmation ? 'pending' : 'deactivated'" :label="twoFactor.enabled ? 'On' : twoFactor.pendingConfirmation ? 'Setting up' : 'Off'" />
                </template>

                <AlertBox v-if="twoFactorError" tone="danger" class="mb-5">{{ twoFactorError }}</AlertBox>

                <!-- Not set up -->
                <div v-if="!twoFactor.enabled && !twoFactor.pendingConfirmation">
                    <p class="text-sm text-muted">When on, signing in needs your password and a 6-digit code from your phone.</p>
                    <ConfirmsPassword @confirmed="visit('post', 'two-factor.enable')">
                        <AppButton class="mt-4" :loading="busy">Set up two-factor authentication</AppButton>
                    </ConfirmsPassword>
                </div>

                <!-- Enrolment: scan and confirm -->
                <div v-else-if="twoFactor.pendingConfirmation" class="grid gap-6 md:grid-cols-[auto_1fr]">
                    <!-- Server-generated SVG (BaconQrCode); contains no user input. -->
                    <div class="w-fit rounded-lg bg-surface p-3 ring-1 ring-line" v-html="twoFactor.qrCodeSvg" />
                    <div class="space-y-4">
                        <ol class="list-decimal space-y-1 pl-5 text-sm text-ink-soft">
                            <li>Open your authenticator app and scan the QR code.</li>
                            <li>
                                Can't scan? Enter this key manually:
                                <code class="mt-1 block w-fit rounded bg-surface-sunken px-2 py-1 font-mono text-sm tracking-wider select-all">{{ twoFactor.setupKey }}</code>
                            </li>
                            <li>Enter the 6-digit code the app shows.</li>
                        </ol>
                        <form class="flex flex-wrap items-end gap-3" @submit.prevent="confirmTwoFactor">
                            <FormField label="Code" :error="confirmForm.errors.code" class="w-40">
                                <TextInput v-model="confirmForm.code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required />
                            </FormField>
                            <AppButton type="submit" :loading="confirmForm.processing">Confirm</AppButton>
                            <AppButton variant="ghost" :disabled="busy" @click="visit('delete', 'two-factor.disable')">Cancel</AppButton>
                        </form>
                    </div>
                </div>

                <!-- Enabled -->
                <div v-else class="space-y-5">
                    <div v-if="twoFactor.recoveryCodes" class="rounded-lg bg-amber-50 p-4 ring-1 ring-amber-200">
                        <p class="text-sm font-semibold text-amber-900">Save your recovery codes now — they won't be shown again.</p>
                        <p class="mt-1 text-sm text-amber-900">Each code signs you in once if you lose your phone. Store them in a password manager or print them.</p>
                        <ul class="mt-3 grid grid-cols-2 gap-2 font-mono text-sm sm:grid-cols-4">
                            <li v-for="code in twoFactor.recoveryCodes" :key="code" class="rounded bg-surface px-2 py-1 text-center select-all">{{ code }}</li>
                        </ul>
                    </div>
                    <p class="text-sm text-muted">Two-factor authentication is on. Lost your recovery codes? Generate a fresh set; the old ones stop working.</p>
                    <div class="flex flex-wrap gap-2">
                        <ConfirmsPassword @confirmed="visit('post', 'two-factor.regenerate-recovery-codes')">
                            <AppButton variant="secondary" :loading="busy">New recovery codes</AppButton>
                        </ConfirmsPassword>
                        <ConfirmsPassword v-if="!twoFactor.required" @confirmed="visit('delete', 'two-factor.disable')">
                            <AppButton variant="danger" :loading="busy">Turn off</AppButton>
                        </ConfirmsPassword>
                    </div>
                    <p v-if="twoFactor.required" class="text-sm text-muted">Mandatory for your role. If you lose your device, ask a portal administrator to reset it.</p>
                </div>
            </CardPanel>

            <PasskeyManager :passkeys="passkeys" />

            <CardPanel v-if="social.length" title="Linked sign-in accounts" description="Sign in with Google or LinkedIn instead of typing your password. Two-factor authentication still applies.">
                <ul class="divide-y divide-line-soft">
                    <li v-for="s in social" :key="s.provider" class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <div>
                            <p class="font-medium text-ink">{{ s.label }}</p>
                            <p class="text-sm text-muted">
                                <template v-if="s.linked">{{ s.email ?? 'Linked' }}<template v-if="s.last_used"> · last used {{ s.last_used }}</template></template>
                                <template v-else>Not linked</template>
                            </p>
                        </div>
                        <ConfirmsPassword v-if="s.linked" @confirmed="visit('delete', 'social.unlink', { provider: s.provider })">
                            <AppButton variant="secondary" size="sm" :loading="busy">Unlink</AppButton>
                        </ConfirmsPassword>
                        <ConfirmsPassword v-else @confirmed="visit('post', 'social.link', { provider: s.provider })">
                            <AppButton size="sm" :loading="busy">Link {{ s.label }}</AppButton>
                        </ConfirmsPassword>
                    </li>
                </ul>
            </CardPanel>

            <CardPanel title="Password" :description="passwordChangedAt ? `Last changed ${passwordChangedAt}.` : undefined">
                <AlertBox v-if="status === 'password-updated'" tone="success" class="mb-5">Password changed. Your other devices have been signed out.</AlertBox>
                <form class="grid max-w-xl gap-5" @submit.prevent="updatePassword">
                    <FormField label="Current password" :error="passwordForm.errors.current_password" required>
                        <TextInput v-model="passwordForm.current_password" type="password" autocomplete="current-password" required />
                    </FormField>
                    <FormField label="New password" :error="passwordForm.errors.password" hint="At least 12 characters. Passphrases welcome." required>
                        <TextInput v-model="passwordForm.password" type="password" autocomplete="new-password" required />
                    </FormField>
                    <FormField label="Confirm new password" :error="passwordForm.errors.password_confirmation" required>
                        <TextInput v-model="passwordForm.password_confirmation" type="password" autocomplete="new-password" required />
                    </FormField>
                    <div><AppButton type="submit" :loading="passwordForm.processing">Change password</AppButton></div>
                </form>
            </CardPanel>
        </div>
    </AppLayout>
</template>
