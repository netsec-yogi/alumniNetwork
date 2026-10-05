<script setup lang="ts">
import { HttpError, json } from '@/lib/http';
import { nextTick, ref } from 'vue';
import AppButton from './AppButton.vue';
import FormField from './FormField.vue';
import ModalDialog from './ModalDialog.vue';
import TextInput from './TextInput.vue';

/**
 * Wraps a sensitive action (SRS 77). Emits `confirmed` straight away if the
 * password was confirmed recently, otherwise asks for it first. Uses
 * Fortify's JSON endpoints so the user never leaves the page.
 */
withDefaults(defineProps<{ title?: string; content?: string; button?: string }>(), {
    title: 'Confirm your password',
    content: 'For your security, please confirm your password to continue.',
    button: 'Confirm',
});
const emit = defineEmits<{ confirmed: [] }>();

const open = ref(false);
const password = ref('');
const error = ref('');
const busy = ref(false);
const input = ref<InstanceType<typeof TextInput>>();

async function start() {
    const { confirmed } = await json<{ confirmed: boolean }>('GET', route('password.confirmation'));
    if (confirmed) {
        emit('confirmed');
        return;
    }
    open.value = true;
    await nextTick();
    (input.value?.$el as HTMLInputElement | undefined)?.focus();
}

async function confirm() {
    busy.value = true;
    error.value = '';
    try {
        await json('POST', route('password.confirm.store'), { password: password.value });
        close();
        await nextTick();
        emit('confirmed');
    } catch (e) {
        error.value = e instanceof HttpError ? (e.body.errors?.password?.[0] ?? e.message) : 'Something went wrong.';
    } finally {
        busy.value = false;
    }
}

function close() {
    open.value = false;
    password.value = '';
    error.value = '';
}
</script>

<template>
    <span @click.prevent="start"><slot /></span>

    <ModalDialog :show="open" :title="title" @close="close">
        <form id="confirm-password-form" @submit.prevent="confirm">
            <p class="mb-4 text-sm text-slate-600">{{ content }}</p>
            <FormField label="Password" :error="error">
                <TextInput ref="input" v-model="password" type="password" autocomplete="current-password" required />
            </FormField>
        </form>
        <template #footer>
            <AppButton variant="secondary" @click="close">Cancel</AppButton>
            <AppButton type="submit" form="confirm-password-form" :loading="busy">{{ button }}</AppButton>
        </template>
    </ModalDialog>
</template>
