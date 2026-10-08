<script setup lang="ts">
import AlertBox from '@/Components/AlertBox.vue';
import AppButton from '@/Components/AppButton.vue';
import FormField from '@/Components/FormField.vue';
import TextInput from '@/Components/TextInput.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { router, useForm } from '@inertiajs/vue3';

const form = useForm({ current_password: '', password: '', password_confirmation: '' });

const submit = () =>
    form.put(route('user-password.update'), {
        errorBag: 'updatePassword',
        onSuccess: () => router.visit(route('dashboard')),
        onFinish: () => form.reset(),
    });
const signOut = () => router.post(route('logout'));
</script>

<template>
    <GuestLayout title="Choose a new password" description="An administrator set a password for your account. Please replace it with one only you know.">
        <AlertBox tone="info" class="mb-5">Use the password the administrator gave you as your current password.</AlertBox>
        <form class="space-y-5" novalidate @submit.prevent="submit">
            <FormField label="Current password" :error="form.errors.current_password" required>
                <TextInput v-model="form.current_password" type="password" autocomplete="current-password" required autofocus />
            </FormField>
            <FormField label="New password" :error="form.errors.password" hint="At least 12 characters. A memorable passphrase works well." required>
                <TextInput v-model="form.password" type="password" autocomplete="new-password" required />
            </FormField>
            <FormField label="Confirm new password" :error="form.errors.password_confirmation" required>
                <TextInput v-model="form.password_confirmation" type="password" autocomplete="new-password" required />
            </FormField>
            <AppButton type="submit" class="w-full" :loading="form.processing">Save and continue</AppButton>
        </form>
        <template #footer><button type="button" class="font-semibold text-brand-600 hover:underline" @click="signOut">Sign out</button></template>
    </GuestLayout>
</template>
