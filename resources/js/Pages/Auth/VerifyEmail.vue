<script setup lang="ts">
import AlertBox from '@/Components/AlertBox.vue';
import AppButton from '@/Components/AppButton.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { router, useForm } from '@inertiajs/vue3';

defineProps<{ status?: string | null }>();
const form = useForm({});
</script>

<template>
    <GuestLayout title="Verify your email" description="We sent a verification link to your email address. Follow it to continue.">
        <AlertBox v-if="status === 'verification-link-sent'" tone="success" class="mb-6">A new verification link has been sent.</AlertBox>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <AppButton :loading="form.processing" @click="form.post(route('verification.send'))">Resend link</AppButton>
            <AppButton variant="ghost" @click="router.post(route('logout'))">Sign out</AppButton>
        </div>
    </GuestLayout>
</template>
