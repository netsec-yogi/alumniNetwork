<script setup lang="ts">
import AppButton from '@/Components/AppButton.vue';
import CardPanel from '@/Components/CardPanel.vue';
import FormField from '@/Components/FormField.vue';
import PageHeader from '@/Components/PageHeader.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { Mail } from 'lucide-vue-next';

interface Field {
    default: number;
    min: number;
    max: number;
    label: string;
    unit: string;
}

const props = defineProps<{ values: Record<string, number>; fields: Record<string, Field> }>();

const form = useForm({ ...props.values });
const save = () => form.put(route('admin.settings.otp.update'), { preserveScroll: true });
const reset = () => Object.assign(form, Object.fromEntries(Object.entries(props.fields).map(([k, f]) => [k, f.default])));
</script>

<template>
    <AppLayout title="OTP sign-in">
        <PageHeader title="OTP sign-in" description="Alumni can sign in with a one-time password emailed to their registered address. Password sign-in is unaffected. Staff accounts can’t use OTP sign-in." />

        <form class="max-w-2xl" @submit.prevent="save">
            <CardPanel title="Email OTP" description="Changes apply to new OTP requests." :icon="Mail">
                <div class="space-y-5">
                    <FormField v-for="(f, key) in fields" :key="key" :label="f.label" :error="form.errors[key]" :hint="`Between ${f.min} and ${f.max}. Default ${f.default}.`" required>
                        <TextInput v-model.number="form[key]" type="number" :min="f.min" :max="f.max" :suffix="f.unit" class="max-w-56" />
                    </FormField>
                </div>
                <template #footer>
                    <AppButton variant="ghost" @click="reset">Restore defaults</AppButton>
                    <AppButton type="submit" :loading="form.processing">Save settings</AppButton>
                </template>
            </CardPanel>
        </form>
    </AppLayout>
</template>
