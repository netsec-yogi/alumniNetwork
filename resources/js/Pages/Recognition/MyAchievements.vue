<script setup lang="ts">
import { ask } from '@/lib/confirm';
import FileUpload from '@/Components/FileUpload.vue';
import AppButton from '@/Components/AppButton.vue';
import CardPanel from '@/Components/CardPanel.vue';
import FormField from '@/Components/FormField.vue';
import PageHeader from '@/Components/PageHeader.vue';
import SelectInput from '@/Components/SelectInput.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextArea from '@/Components/TextArea.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Option } from '@/types';
import { router, useForm } from '@inertiajs/vue3';

defineProps<{ achievements: { id: number; title: string; category: string; status: string; rejection_reason: string | null; date: string | null }[]; categories: Option[] }>();

const form = useForm<{ category: string; title: string; description: string; achieved_on: string; link_url: string; image: File | null }>({
    category: '',
    title: '',
    description: '',
    achieved_on: '',
    link_url: '',
    image: null,
});
const submit = () =>
    form
        .transform((d) => ({ ...d, achieved_on: d.achieved_on || null, link_url: d.link_url || null }))
        .post(route('achievements.submit'), { forceFormData: true, preserveScroll: true, onSuccess: () => form.reset() });
const withdraw = (id: number) => ask('Remove this achievement?').then((ok) => ok && router.delete(route('achievements.withdraw', id), { preserveScroll: true }));
const badge = (s: string) => ({ published: 'verified', rejected: 'rejected' })[s] ?? 'pending';
</script>

<template>
    <AppLayout title="My achievements">
        <PageHeader title="My achievements" description="Published achievements appear on your profile and on the public achievements page.">
            <AppButton variant="ghost" :href="route('achievements.index')">View all achievements</AppButton>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
            <CardPanel title="Share an achievement">
                <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
                    <FormField label="Category" :error="form.errors.category" required><SelectInput v-model="form.category" :options="categories" placeholder="Choose" /></FormField>
                    <FormField label="When" :error="form.errors.achieved_on"><TextInput v-model="form.achieved_on" type="date" /></FormField>
                    <div class="sm:col-span-2"><FormField label="Title" :error="form.errors.title" required><TextInput v-model="form.title" maxlength="200" placeholder="e.g. Promoted to Principal Engineer at Atlassian" /></FormField></div>
                    <div class="sm:col-span-2"><FormField label="Details" :error="form.errors.description"><TextArea v-model="form.description" rows="4" maxlength="2000" /></FormField></div>
                    <FormField label="Link" :error="form.errors.link_url"><TextInput v-model="form.link_url" type="url" placeholder="https://" /></FormField>
                    <FormField label="Image (optional)" :error="form.errors.image">
                        <FileUpload v-model="form.image" accept="image/jpeg,image/png,image/webp" :max-mb="10" hint="JPEG, PNG or WebP, up to 10 MB" :progress="form.progress?.percentage ?? null" />
                    </FormField>
                    <div class="sm:col-span-2"><AppButton type="submit" :loading="form.processing">Submit for review</AppButton></div>
                </form>
            </CardPanel>

            <CardPanel title="Your submissions">
                <p v-if="achievements.length === 0" class="text-sm text-muted">Nothing yet.</p>
                <ul v-else class="space-y-3 text-sm">
                    <li v-for="a in achievements" :key="a.id">
                        <div class="flex items-start justify-between gap-2">
                            <span class="font-medium text-ink">{{ a.title }}</span>
                            <StatusBadge :status="badge(a.status)" :label="a.status" />
                        </div>
                        <p class="text-muted">{{ a.category }}<span v-if="a.date"> · {{ a.date }}</span></p>
                        <p v-if="a.rejection_reason" class="mt-1 text-red-700">{{ a.rejection_reason }}</p>
                        <button type="button" class="mt-1 text-xs text-muted hover:text-red-700" @click="withdraw(a.id)">Remove</button>
                    </li>
                </ul>
            </CardPanel>
        </div>
    </AppLayout>
</template>
