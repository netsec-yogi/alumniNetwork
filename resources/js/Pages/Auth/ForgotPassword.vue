<script setup lang="ts">
import AlertBox from '@/Components/AlertBox.vue';
import AppButton from '@/Components/AppButton.vue';
import FormField from '@/Components/FormField.vue';
import TextInput from '@/Components/TextInput.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';

defineProps<{ status?: string | null }>();
const form = useForm({ email: '' });
</script>

<template>
    <GuestLayout title="Reset your password" description="Enter your email and we will send you a link to choose a new password.">
        <AlertBox v-if="status" tone="success" class="mb-6">{{ status }}</AlertBox>
        <form class="space-y-5" novalidate @submit.prevent="form.post(route('password.email'))">
            <FormField label="Email address" :error="form.errors.email" required>
                <TextInput v-model="form.email" type="email" autocomplete="username" required autofocus />
            </FormField>
            <AppButton type="submit" class="w-full" :loading="form.processing">Email reset link</AppButton>
        </form>
        <template #footer>
            <Link :href="route('login')" class="font-medium text-brand-700 hover:text-brand-900">Back to sign in</Link>
        </template>
    </GuestLayout>
</template>
