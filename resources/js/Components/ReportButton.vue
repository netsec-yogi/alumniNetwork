<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppButton from './AppButton.vue';
import FormField from './FormField.vue';
import ModalDialog from './ModalDialog.vue';
import SelectInput from './SelectInput.vue';
import TextArea from './TextArea.vue';

/** "Report" link + dialog for any reportable item (SRS 24-26). */
const props = defineProps<{ type: string; id: number; reasons: Record<string, string>; label?: string }>();

const open = ref(false);
const form = useForm({ type: props.type, id: props.id, reason: '', details: '' });
const options = Object.entries(props.reasons).map(([value, label]) => ({ value, label }));

function submit() {
    form.post(route('reports.store'), { preserveScroll: true, onSuccess: close });
}

function close() {
    open.value = false;
    form.reset('reason', 'details');
    form.clearErrors();
}
</script>

<template>
    <button type="button" class="text-sm text-muted hover:text-red-700" @click="open = true">{{ label ?? 'Report' }}</button>
    <ModalDialog :show="open" title="Report to moderators" @close="close">
        <form :id="`report-${type}-${id}`" class="space-y-4" @submit.prevent="submit">
            <p class="text-sm text-muted">Reports are confidential; the member is not told who reported them.</p>
            <FormField label="Reason" :error="form.errors.reason" required>
                <SelectInput v-model="form.reason" :options="options" placeholder="Choose a reason" required />
            </FormField>
            <FormField label="Details (optional)" :error="form.errors.details">
                <TextArea v-model="form.details" rows="3" maxlength="1000" />
            </FormField>
        </form>
        <template #footer>
            <AppButton variant="secondary" @click="close">Cancel</AppButton>
            <AppButton type="submit" :form="`report-${type}-${id}`" variant="danger" :loading="form.processing">Send report</AppButton>
        </template>
    </ModalDialog>
</template>
