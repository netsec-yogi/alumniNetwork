<script setup lang="ts">
import AppButton from '@/Components/AppButton.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';

defineProps<{ action: string | null; subscribed: boolean }>();
</script>

<template>
    <GuestLayout title="Email preferences">
        <template v-if="action && subscribed">
            <p class="text-sm text-muted">Stop receiving alumni news and announcements by email? Account and security emails will still be sent.</p>
            <!-- Plain form: the signed URL in `action` is the credential. -->
            <form method="post" :action="action" class="mt-6">
                <AppButton type="submit" class="w-full">Unsubscribe</AppButton>
            </form>
        </template>
        <p v-else class="text-sm text-muted">You’re unsubscribed from alumni news emails. You can opt back in any time from your profile.</p>
    </GuestLayout>
</template>
