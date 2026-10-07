<script setup lang="ts">
import AppButton from '@/Components/AppButton.vue';
import CardPanel from '@/Components/CardPanel.vue';
import CheckboxInput from '@/Components/CheckboxInput.vue';
import FormField from '@/Components/FormField.vue';
import PageHeader from '@/Components/PageHeader.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextArea from '@/Components/TextArea.vue';
import TagInput from '@/Components/TagInput.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Option } from '@/types';
import { useForm } from '@inertiajs/vue3';

interface EventInput {
    id: number;
    slug: string;
    title: string;
    type: string;
    summary: string | null;
    description: string | null;
    starts_at: string;
    ends_at: string;
    venue: string | null;
    is_online: boolean;
    online_url: string | null;
    capacity: number | null;
    max_guests: number;
    registration_opens_at: string | null;
    registration_closes_at: string | null;
    audience: string;
    status: string;
    community_id: number | null;
    fee: number;
    batch_years: number[];
}

const props = defineProps<{ event: EventInput | null; typeOptions: Option[]; groups: Option<number>[] }>();
const e = props.event;

const form = useForm({
    title: e?.title ?? '',
    type: e?.type ?? '',
    summary: e?.summary ?? '',
    description: e?.description ?? '',
    starts_at: e?.starts_at ?? '',
    ends_at: e?.ends_at ?? '',
    is_online: e?.is_online ?? false,
    venue: e?.venue ?? '',
    online_url: e?.online_url ?? '',
    capacity: e?.capacity ?? ('' as number | ''),
    max_guests: e?.max_guests ?? 0,
    registration_opens_at: e?.registration_opens_at ?? '',
    registration_closes_at: e?.registration_closes_at ?? '',
    audience: e?.audience ?? 'members',
    community_id: e?.community_id ?? ('' as number | ''),
    fee: e?.fee ?? 0,
    batch_years: (e?.batch_years ?? []).map(String),
});

const audienceOptions = [
    { value: 'members', label: 'IIITM community only' },
    { value: 'public', label: 'Public (anyone can view)' },
];

function submit() {
    // Empty strings become nulls so optional dates and capacity clear properly.
    form.transform((d) => ({
        ...Object.fromEntries(Object.entries(d).map(([k, v]) => [k, v === '' ? null : v])),
        batch_years: d.batch_years.map((y) => Number(y)).filter((y) => y > 0),
    }));
    if (e) form.put(route('admin.events.update', e.slug));
    else form.post(route('admin.events.store'));
}
</script>

<template>
    <AppLayout :title="event ? 'Edit event' : 'New event'">
        <PageHeader :title="event ? `Edit: ${event.title}` : 'New event'" description="Saved as a draft until you publish it.">
            <AppButton variant="ghost" :href="event ? route('admin.events.show', event.slug) : route('admin.events.index')">Cancel</AppButton>
        </PageHeader>

        <form class="space-y-6" novalidate @submit.prevent="submit">
            <CardPanel title="Details">
                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <FormField label="Title" :error="form.errors.title" required><TextInput v-model="form.title" required maxlength="160" /></FormField>
                    </div>
                    <FormField label="Type" :error="form.errors.type" required>
                        <SelectInput v-model="form.type" :options="typeOptions" placeholder="Choose a type" required />
                    </FormField>
                    <FormField label="Who can see it" :error="form.errors.audience" required>
                        <SelectInput v-model="form.audience" :options="audienceOptions" />
                    </FormField>
                    <FormField v-if="groups.length" label="Hosted by" :error="form.errors.community_id" hint="Shows the event on the chapter or group page.">
                        <SelectInput v-model="form.community_id" :options="groups" placeholder="The institute (no group)" />
                    </FormField>
                    <div class="sm:col-span-2">
                        <FormField label="Summary" :error="form.errors.summary" hint="One or two lines shown in listings."><TextInput v-model="form.summary" maxlength="300" /></FormField>
                    </div>
                    <div class="sm:col-span-2">
                        <FormField label="Description" :error="form.errors.description"><TextArea v-model="form.description" rows="8" /></FormField>
                    </div>
                </div>
            </CardPanel>

            <CardPanel title="When and where">
                <div class="grid gap-5 sm:grid-cols-2">
                    <FormField label="Starts" :error="form.errors.starts_at" required><TextInput v-model="form.starts_at" type="datetime-local" required /></FormField>
                    <FormField label="Ends" :error="form.errors.ends_at" required><TextInput v-model="form.ends_at" type="datetime-local" required /></FormField>
                    <div class="sm:col-span-2"><CheckboxInput v-model="form.is_online" label="This is an online event" /></div>
                    <FormField v-if="!form.is_online" label="Venue" :error="form.errors.venue" required><TextInput v-model="form.venue" /></FormField>
                    <FormField v-else label="Joining link" :error="form.errors.online_url" hint="Shown only to confirmed registrants." required>
                        <TextInput v-model="form.online_url" type="url" placeholder="https://" />
                    </FormField>
                </div>
            </CardPanel>

            <CardPanel title="Registration">
                <div class="grid gap-5 sm:grid-cols-2">
                    <FormField label="Capacity" :error="form.errors.capacity" hint="Total seats including guests. Leave empty for unlimited.">
                        <TextInput v-model.number="form.capacity" type="number" min="1" />
                    </FormField>
                    <FormField label="Guests per registration" :error="form.errors.max_guests"><TextInput v-model.number="form.max_guests" type="number" min="0" max="10" /></FormField>
                    <FormField label="Fee per person (₹)" :error="form.errors.fee" hint="0 for free. Guests pay the same; paid via the secure checkout.">
                        <TextInput v-model.number="form.fee" type="number" min="0" step="1" />
                    </FormField>
                    <FormField label="Reunion batches" :error="form.errors.batch_years" hint="Graduation years to invite, e.g. 2011, 2016. Leave empty for other events.">
                        <TagInput v-model="form.batch_years" :max="20" placeholder="Type a year and press Enter" />
                    </FormField>
                    <FormField label="Registration opens" :error="form.errors.registration_opens_at" hint="Empty = as soon as published.">
                        <TextInput v-model="form.registration_opens_at" type="datetime-local" />
                    </FormField>
                    <FormField label="Registration closes" :error="form.errors.registration_closes_at" hint="Empty = when the event ends.">
                        <TextInput v-model="form.registration_closes_at" type="datetime-local" />
                    </FormField>
                </div>
            </CardPanel>

            <div class="flex justify-end"><AppButton type="submit" :loading="form.processing">{{ event ? 'Save changes' : 'Save draft' }}</AppButton></div>
        </form>
    </AppLayout>
</template>
