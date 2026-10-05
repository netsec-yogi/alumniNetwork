<script setup lang="ts">
import AppButton from '@/Components/AppButton.vue';
import FormField from '@/Components/FormField.vue';
import TextInput from '@/Components/TextInput.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps<{ email: string; token: string }>();
const form = useForm({ token: props.token, email: props.email, password: '', password_confirmation: '' });

const submit = () => form.post(route('password.update'), { onFinish: () => form.reset('password', 'password_confirmation') });
</script>

<template>
    <GuestLayout title="Choose a new password" description="All your other sessions will be signed out.">
        <form class="space-y-5" novalidate @submit.prevent="submit">
            <FormField label="Email address" :error="form.errors.email" required>
                <TextInput v-model="form.email" type="email" autocomplete="username" required />
            </FormField>
            <FormField label="New password" :error="form.errors.password" hint="At least 12 characters." required>
                <TextInput v-model="form.password" type="password" autocomplete="new-password" required autofocus />
            </FormField>
            <FormField label="Confirm new password" :error="form.errors.password_confirmation" required>
                <TextInput v-model="form.password_confirmation" type="password" autocomplete="new-password" required />
            </FormField>
            <AppButton type="submit" class="w-full" :loading="form.processing">Reset password</AppButton>
        </form>
    </GuestLayout>
</template>
