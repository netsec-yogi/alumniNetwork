<script setup lang="ts">
import AppButton from '@/Components/AppButton.vue';
import FormField from '@/Components/FormField.vue';
import TextInput from '@/Components/TextInput.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { useForm } from '@inertiajs/vue3';

const form = useForm({ password: '' });
</script>

<template>
    <GuestLayout title="Confirm your password" description="This is a secure area. Please confirm your password to continue.">
        <form class="space-y-5" @submit.prevent="form.post(route('password.confirm.store'), { onFinish: () => form.reset() })">
            <FormField label="Password" :error="form.errors.password" required>
                <TextInput v-model="form.password" type="password" autocomplete="current-password" required autofocus />
            </FormField>
            <AppButton type="submit" class="w-full" :loading="form.processing">Confirm</AppButton>
        </form>
    </GuestLayout>
</template>
