<script setup lang="ts">
import AlertBox from '@/Components/AlertBox.vue';
import AppButton from '@/Components/AppButton.vue';
import CardPanel from '@/Components/CardPanel.vue';
import CheckboxInput from '@/Components/CheckboxInput.vue';
import ConfirmsPassword from '@/Components/ConfirmsPassword.vue';
import FormField from '@/Components/FormField.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import PageHeader from '@/Components/PageHeader.vue';
import TagInput from '@/Components/TagInput.vue';
import TextArea from '@/Components/TextArea.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { json } from '@/lib/http';
import type { Option } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { onMounted, ref, watch } from 'vue';

interface Audience {
    roles?: string[];
    programmes?: number[];
    graduation_from?: number | '';
    graduation_to?: number | '';
    cities?: string[];
    countries?: string[];
    industries?: string[];
    interests?: string[];
    communities?: number[];
}

const props = defineProps<{
    campaign: { id: number; name: string; subject: string; body: string; channels: string[]; audience: Audience; status: string; scheduled_at: string | null } | null;
    options: { roles: Option[]; programmes: Option<number>[]; cities: string[]; countries: string[]; industries: string[]; interests: Option[]; communities: Option<number>[] };
}>();

const c = props.campaign;
const form = useForm({
    name: c?.name ?? '',
    subject: c?.subject ?? '',
    body: c?.body ?? '',
    channels: c?.channels ?? ['email', 'in_app'],
    scheduled_at: c?.scheduled_at ?? '',
    action: 'draft' as 'draft' | 'schedule' | 'send',
    audience: {
        roles: c?.audience.roles ?? ['alumni'],
        programmes: c?.audience.programmes ?? [],
        graduation_from: c?.audience.graduation_from ?? '',
        graduation_to: c?.audience.graduation_to ?? '',
        cities: c?.audience.cities ?? [],
        countries: c?.audience.countries ?? [],
        industries: c?.audience.industries ?? [],
        interests: c?.audience.interests ?? [],
        communities: c?.audience.communities ?? [],
    } as Audience,
});

const preview = ref<{ total: number; email: number; sample: string[] } | null>(null);
let timer: ReturnType<typeof setTimeout> | undefined;
async function refresh() {
    const a = Object.fromEntries(Object.entries(form.audience).filter(([, v]) => (Array.isArray(v) ? v.length : v !== '' && v !== null)));
    preview.value = await json<{ total: number; email: number; sample: string[] }>('POST', route('admin.communications.preview'), a).catch(() => null);
}
watch(() => form.audience, () => {
    clearTimeout(timer);
    timer = setTimeout(refresh, 400);
}, { deep: true });
onMounted(refresh);

function submit(action: 'draft' | 'schedule' | 'send') {
    form.action = action;
    form.transform((d) => ({
        ...d,
        scheduled_at: d.scheduled_at || null,
        audience: Object.fromEntries(Object.entries(d.audience).filter(([, v]) => (Array.isArray(v) ? v.length : v !== '' && v !== null))),
    }));
    c ? form.put(route('admin.communications.update', c.id)) : form.post(route('admin.communications.store'));
}
const asOptions = (xs: string[]) => xs.map((x) => ({ value: x, label: x }));
</script>

<template>
    <AppLayout :title="campaign ? 'Edit message' : 'New message'">
        <PageHeader :title="campaign ? 'Edit message' : 'New message'">
            <AppButton variant="ghost" :href="route('admin.communications.index')">Cancel</AppButton>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
            <div class="space-y-6">
                <CardPanel title="Audience" description="Everyone matching all the filters you set. Empty filters don’t restrict.">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <FormField label="Who"><MultiSelect v-model="form.audience.roles!" :options="options.roles" /></FormField>
                        <FormField label="Programmes"><MultiSelect v-model="form.audience.programmes!" :options="options.programmes" /></FormField>
                        <FormField label="Graduated from"><TextInput v-model.number="form.audience.graduation_from" type="number" placeholder="e.g. 2010" /></FormField>
                        <FormField label="Graduated to"><TextInput v-model.number="form.audience.graduation_to" type="number" placeholder="e.g. 2018" /></FormField>
                        <FormField label="Cities"><MultiSelect v-model="form.audience.cities!" :options="asOptions(options.cities)" /></FormField>
                        <FormField label="Countries"><MultiSelect v-model="form.audience.countries!" :options="asOptions(options.countries)" /></FormField>
                        <FormField label="Industries"><MultiSelect v-model="form.audience.industries!" :options="asOptions(options.industries)" /></FormField>
                        <FormField label="Open to"><MultiSelect v-model="form.audience.interests!" :options="options.interests" /></FormField>
                        <div class="sm:col-span-2"><FormField label="Members of groups"><MultiSelect v-model="form.audience.communities!" :options="options.communities" /></FormField></div>
                    </div>
                    <p v-if="form.errors.audience" class="mt-2 text-sm text-red-600">{{ form.errors.audience }}</p>
                </CardPanel>

                <CardPanel title="Message">
                    <div class="space-y-4">
                        <FormField label="Internal name" :error="form.errors.name" required><TextInput v-model="form.name" maxlength="160" /></FormField>
                        <FormField label="Subject" :error="form.errors.subject" required><TextInput v-model="form.subject" maxlength="200" /></FormField>
                        <FormField label="Body" :error="form.errors.body" hint="Markdown supported. Recipients see “Dear <name>,” first and an unsubscribe link last." required>
                            <TextArea v-model="form.body" rows="12" />
                        </FormField>
                        <fieldset>
                            <legend class="text-sm font-medium text-ink-soft">Send via</legend>
                            <div class="mt-2 flex gap-6">
                                <CheckboxInput v-model="form.channels" value="email" label="Email" />
                                <CheckboxInput v-model="form.channels" value="in_app" label="In-app notification" />
                            </div>
                            <p v-if="form.errors.channels" class="mt-1 text-sm text-red-600">{{ form.errors.channels }}</p>
                        </fieldset>
                        <FormField label="Schedule for (optional)" :error="form.errors.scheduled_at"><TextInput v-model="form.scheduled_at" type="datetime-local" /></FormField>
                    </div>
                </CardPanel>
            </div>

            <aside class="space-y-4 lg:sticky lg:top-24 lg:self-start">
                <CardPanel title="Reach">
                    <template v-if="preview">
                        <p class="text-3xl font-semibold tabular-nums">{{ preview.total.toLocaleString('en-IN') }}</p>
                        <p class="text-sm text-muted">people match · <strong>{{ preview.email.toLocaleString('en-IN') }}</strong> opted in to email</p>
                        <p v-if="preview.sample.length" class="mt-3 text-xs text-muted">e.g. {{ preview.sample.join(', ') }}</p>
                    </template>
                    <p v-else class="text-sm text-muted">Calculating…</p>
                </CardPanel>
                <AlertBox v-if="preview && preview.total === 0" tone="warning">No one matches these filters.</AlertBox>
                <div class="flex flex-col gap-2">
                    <ConfirmsPassword @confirmed="submit('send')">
                        <AppButton class="w-full" :disabled="!preview?.total" :loading="form.processing && form.action === 'send'">Send now</AppButton>
                    </ConfirmsPassword>
                    <ConfirmsPassword @confirmed="submit('schedule')">
                        <AppButton class="w-full" variant="secondary" :disabled="!form.scheduled_at">Schedule</AppButton>
                    </ConfirmsPassword>
                    <ConfirmsPassword @confirmed="submit('draft')">
                        <AppButton class="w-full" variant="ghost">Save draft</AppButton>
                    </ConfirmsPassword>
                </div>
            </aside>
        </div>
    </AppLayout>
</template>
