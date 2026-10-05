<script setup lang="ts">
import AppButton from '@/Components/AppButton.vue';
import FormField from '@/Components/FormField.vue';
import TextInput from '@/Components/TextInput.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const useRecovery = ref(false);
const form = useForm({ code: '', recovery_code: '' });

function toggle() {
    useRecovery.value = !useRecovery.value;
    form.reset();
    form.clearErrors();
}
</script>

<template>
    <GuestLayout
        title="Two-factor authentication"
        :description="useRecovery ? 'Enter one of your emergency recovery codes. Each code works once.' : 'Enter the 6-digit code from your authenticator app.'"
    >
        <form class="space-y-5" novalidate @submit.prevent="form.post(route('two-factor.login.store'))">
            <FormField v-if="!useRecovery" label="Authentication code" :error="form.errors.code" required>
                <TextInput v-model="form.code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]*" maxlength="6" required autofocus />
            </FormField>
            <FormField v-else label="Recovery code" :error="form.errors.recovery_code" required>
                <TextInput v-model="form.recovery_code" autocomplete="off" required autofocus />
            </FormField>
            <AppButton type="submit" class="w-full" :loading="form.processing">Verify</AppButton>
        </form>
        <template #footer>
            <button type="button" class="font-medium text-brand-700 hover:text-brand-900" @click="toggle">
                {{ useRecovery ? 'Use an authenticator code' : 'Lost your device? Use a recovery code' }}
            </button>
        </template>
    </GuestLayout>
</template>
