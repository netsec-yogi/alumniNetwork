<script setup lang="ts">
import AlertBox from '@/Components/AlertBox.vue';
import AppButton from '@/Components/AppButton.vue';
import CheckboxInput from '@/Components/CheckboxInput.vue';
import FormField from '@/Components/FormField.vue';
import TextInput from '@/Components/TextInput.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';

defineProps<{ canResetPassword: boolean; status?: string | null }>();

const form = useForm({ email: '', password: '', remember: false });

const submit = () => form.post(route('login.store'), { onFinish: () => form.reset('password') });
</script>

<template>
    <GuestLayout title="Sign in" description="Welcome back to the ABV-IIITM alumni network.">
        <AlertBox v-if="status" tone="success" class="mb-6">{{ status }}</AlertBox>

        <form class="space-y-5" novalidate @submit.prevent="submit">
            <FormField label="Email address" :error="form.errors.email" required>
                <TextInput v-model="form.email" type="email" autocomplete="username" required autofocus />
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

        <template #footer>
            New here? <Link :href="route('register')" class="font-medium text-brand-700 hover:text-brand-900">Register as an alumnus</Link>
        </template>
    </GuestLayout>
</template>
