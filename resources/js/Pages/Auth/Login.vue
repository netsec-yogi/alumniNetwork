<script setup lang="ts">
import AlertBox from '@/Components/AlertBox.vue';
import AppButton from '@/Components/AppButton.vue';
import AppTooltip from '@/Components/AppTooltip.vue';
import CheckboxInput from '@/Components/CheckboxInput.vue';
import FormField from '@/Components/FormField.vue';
import TabsNav from '@/Components/TabsNav.vue';
import TextInput from '@/Components/TextInput.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { usePasskeyVerify } from '@laravel/passkeys/vue';
import { KeyRound, LockKeyhole, Mail, RefreshCw } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps<{ canResetPassword: boolean; status?: string | null; socialProviders: Record<string, string> }>();

// Password stays the default; ?mode=otp (used after an OTP error) opens the other tab.
const mode = ref<'password' | 'otp'>(new URLSearchParams(window.location.search).get('mode') === 'otp' ? 'otp' : 'password');
const tabs = [
    { key: 'password' as const, label: 'Password', icon: LockKeyhole },
    { key: 'otp' as const, label: 'Email OTP', icon: Mail },
];

const form = useForm({ email: '', password: '', remember: false });

const submit = () => form.post(route('login.store'), { onFinish: () => form.reset('password') });

// Email OTP: the CAPTCHA is single-use, so a fresh image follows every attempt.
const otpForm = useForm({ email: '', captcha: '' });
const captchaKey = ref(0);
const refreshCaptcha = () => {
    captchaKey.value++;
    otpForm.captcha = '';
};
const requestOtp = () => otpForm.post(route('login.otp.store'), { onFinish: refreshCaptcha });

// Passkey sign-in. `autofill` also offers saved passkeys in the email field's suggestions.
// A full page load afterwards picks up the new session and CSRF token.
const passkey = usePasskeyVerify({
    autofill: true,
    remember: () => form.remember,
    onSuccess: (response) => window.location.assign(response.redirect ?? route('dashboard')),
});
</script>

<template>
    <GuestLayout title="Sign in" description="Welcome back to the ABV-IIITM alumni network.">
        <AlertBox v-if="status" tone="success" class="mb-6">{{ status }}</AlertBox>

        <TabsNav v-model="mode" :items="tabs" class="mb-6" />

        <form v-if="mode === 'password'" class="space-y-5" novalidate @submit.prevent="submit">
            <FormField label="Email address" :error="form.errors.email" required>
                <TextInput v-model="form.email" type="email" autocomplete="username webauthn" required autofocus />
            </FormField>

            <FormField label="Password" :error="form.errors.password" required>
                <TextInput v-model="form.password" type="password" autocomplete="current-password" required />
            </FormField>

            <div class="flex items-center justify-between">
                <CheckboxInput v-model="form.remember" label="Keep me signed in" />
                <Link v-if="canResetPassword" :href="route('password.request')" class="text-sm font-medium text-brand-700 hover:text-brand-900">Forgot password?</Link>
            </div>

            <AppButton type="submit" class="w-full" :loading="form.processing">Sign in</AppButton>
        </form>

        <form v-else class="space-y-5" novalidate @submit.prevent="requestOtp">
            <p class="text-sm text-muted">Alumni can sign in with a one-time password sent to their registered email address.</p>

            <FormField label="Registered email address" :error="otpForm.errors.email" required>
                <TextInput v-model="otpForm.email" type="email" autocomplete="email" required autofocus />
            </FormField>

            <FormField label="Type the characters in the image" :error="otpForm.errors.captcha" required>
                <div class="flex items-center gap-2">
                    <img :key="captchaKey" :src="`${route('captcha')}?v=${captchaKey}`" alt="CAPTCHA image: type the characters shown" width="176" height="56" class="h-14 w-44 shrink-0 rounded-xl ring-1 ring-line" />
                    <AppTooltip text="New image">
                        <button type="button" class="rounded-full p-2 text-muted hover:bg-surface-sunken hover:text-ink" aria-label="Show a different CAPTCHA image" @click="refreshCaptcha"><RefreshCw :size="18" /></button>
                    </AppTooltip>
                </div>
                <TextInput v-model="otpForm.captcha" class="mt-2" autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="10" required />
            </FormField>

            <AppButton type="submit" class="w-full" :loading="otpForm.processing">Send OTP</AppButton>
        </form>

        <template v-if="mode === 'password' && passkey.isSupported.value">
            <p class="relative my-5 text-center text-xs text-subtle before:absolute before:inset-x-0 before:top-1/2 before:h-px before:bg-line">
                <span class="relative bg-surface px-3">or</span>
            </p>
            <AppButton variant="secondary" class="w-full" :icon="KeyRound" :loading="passkey.isLoading.value" @click="passkey.verify()">Sign in with a passkey</AppButton>
            <p v-if="passkey.error.value" class="mt-2 text-center text-[13px] text-red-600" role="alert">{{ passkey.error.value }}</p>
        </template>

        <div v-if="Object.keys(socialProviders).length" class="mt-6">
            <p class="relative text-center text-sm text-muted before:absolute before:inset-x-0 before:top-1/2 before:h-px before:bg-line">
                <span class="relative bg-surface px-3">or, if you’ve linked an account</span>
            </p>
            <div class="mt-4 grid gap-2">
                <!-- Full-page navigation: the provider's consent screen can't load inside an Inertia visit. -->
                <AppButton v-for="(label, provider) in socialProviders" :key="provider" variant="secondary" class="w-full" :href="route('social.redirect', { provider })" external>
                    Continue with {{ label }}
                </AppButton>
            </div>
        </div>

        <template #footer>
            New here? <Link :href="route('register')" class="font-medium text-brand-700 hover:text-brand-900">Register as an alumnus</Link>
        </template>
    </GuestLayout>
</template>
