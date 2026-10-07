<script setup lang="ts">
import AlertBox from '@/Components/AlertBox.vue';
import AppButton from '@/Components/AppButton.vue';
import CheckboxInput from '@/Components/CheckboxInput.vue';
import FormField from '@/Components/FormField.vue';
import TextInput from '@/Components/TextInput.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { usePasskeyVerify } from '@laravel/passkeys/vue';
import { KeyRound } from 'lucide-vue-next';

defineProps<{ canResetPassword: boolean; status?: string | null; socialProviders: Record<string, string> }>();

const form = useForm({ email: '', password: '', remember: false });

const submit = () => form.post(route('login.store'), { onFinish: () => form.reset('password') });

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

        <form class="space-y-5" novalidate @submit.prevent="submit">
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

        <template v-if="passkey.isSupported.value">
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
