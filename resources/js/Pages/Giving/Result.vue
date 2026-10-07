<script setup lang="ts">
import AppButton from '@/Components/AppButton.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { usePoll } from '@inertiajs/vue3';

const props = defineProps<{ status: string; reference: string; amount: string; receipt: string | null; email: string }>();
// The webhook may land a moment after the redirect: check again briefly.
usePoll(3000, { only: ['status', 'receipt'] }, { autoStart: props.status === 'pending' });
</script>

<template>
    <GuestLayout :title="status === 'paid' ? 'Thank you!' : status === 'pending' ? 'Confirming your payment…' : 'Payment not completed'">
        <template v-if="status === 'paid'">
            <p class="text-sm text-ink-soft">Your gift of <strong>{{ amount }}</strong> has been received. Receipt <strong>{{ receipt }}</strong> is on its way to {{ email }}.</p>
        </template>
        <p v-else-if="status === 'pending'" class="text-sm text-ink-soft">We’re waiting for confirmation from the payment provider. This page updates automatically.</p>
        <p v-else class="text-sm text-ink-soft">The payment didn’t go through and you haven’t been charged. You can try again.</p>
        <p class="mt-3 text-xs text-muted">Reference {{ reference }}</p>
        <AppButton class="mt-6 w-full" :href="route(status === 'paid' ? 'home' : 'giving.index')">{{ status === 'paid' ? 'Back to home' : 'Try again' }}</AppButton>
    </GuestLayout>
</template>
