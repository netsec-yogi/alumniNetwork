<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppButton from './AppButton.vue';
import FormField from './FormField.vue';
import ModalDialog from './ModalDialog.vue';
import TextArea from './TextArea.vue';

/** Share an event or job to the feed with a short note. */
const props = defineProps<{ type: 'event' | 'job_posting'; id: number; title: string }>();
const open = ref(false);
const form = useForm({ body: '', share_type: props.type, share_id: props.id });
const submit = () => form.post(route('posts.store'), { preserveScroll: true, onSuccess: () => ((open.value = false), form.reset('body')) });
</script>

<template>
    <AppButton variant="ghost" size="sm" @click="open = true">Share to feed</AppButton>
    <ModalDialog :show="open" :title="`Share “${title}”`" @close="open = false">
        <form :id="`share-${type}-${id}`" @submit.prevent="submit">
            <FormField label="Add a note" :error="form.errors.body" required><TextArea v-model="form.body" rows="3" maxlength="5000" /></FormField>
        </form>
        <template #footer>
            <AppButton variant="secondary" @click="open = false">Cancel</AppButton>
            <AppButton type="submit" :form="`share-${type}-${id}`" :loading="form.processing">Share</AppButton>
        </template>
    </ModalDialog>
</template>
