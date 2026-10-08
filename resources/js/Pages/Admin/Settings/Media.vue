<script setup lang="ts">
import AppButton from '@/Components/AppButton.vue';
import CardPanel from '@/Components/CardPanel.vue';
import FormField from '@/Components/FormField.vue';
import PageHeader from '@/Components/PageHeader.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { ImageDown } from 'lucide-vue-next';

const props = defineProps<{ values: Record<string, number>; labels: Record<string, string>; defaults: Record<string, number>; bounds: { min: number; max: number } }>();

const form = useForm({ ...props.values });
const save = () => form.put(route('admin.settings.media.update'), { preserveScroll: true });
const reset = () => Object.assign(form, { ...props.defaults });
</script>

<template>
    <AppLayout title="Media settings">
        <PageHeader title="Media settings" description="Maximum stored size of uploaded images. Larger uploads are resized and compressed to fit; images that still can't fit are rejected." />

        <form class="max-w-2xl" @submit.prevent="save">
            <CardPanel title="Image size limits" :description="`Between ${bounds.min} KB and ${bounds.max} KB.`" :icon="ImageDown">
                <div class="space-y-5">
                    <FormField
                        v-for="(label, key) in labels"
                        :key="key"
                        :label="`${label} maximum size`"
                        :error="form.errors[key]"
                        :hint="`Default ${defaults[key]} KB. Applies to new uploads; existing images are unchanged.`"
                        required
                    >
                        <TextInput v-model.number="form[key]" type="number" :min="bounds.min" :max="bounds.max" step="10" suffix="KB" class="max-w-48" />
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
