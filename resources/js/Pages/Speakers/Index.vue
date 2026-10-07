<script setup lang="ts">
import AppButton from '@/Components/AppButton.vue';
import CardPanel from '@/Components/CardPanel.vue';
import CheckboxInput from '@/Components/CheckboxInput.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FormField from '@/Components/FormField.vue';
import ModalDialog from '@/Components/ModalDialog.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PaginationNav from '@/Components/PaginationNav.vue';
import PersonCard from '@/Components/PersonCard.vue';
import SelectInput from '@/Components/SelectInput.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextArea from '@/Components/TextArea.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Option, Paginated } from '@/types';
import { router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

interface Speaker {
    user_id: number;
    name: string;
    subtitle: string;
    role: string | null;
    photo_url: string | null;
    profile_id: number | null;
    topics: string[];
    formats: string[];
    format_keys: string[];
    bio: string | null;
    languages: string | null;
    remote: boolean;
    in_person: boolean;
    is_me: boolean;
}
interface Invitation {
    id: number;
    title: string;
    details: string;
    format: string;
    date: string | null;
    status: string;
    note: string | null;
    other: string;
}

const props = defineProps<{
    speakers: Paginated<Speaker>;
    filters: Record<string, string | number | boolean | undefined>;
    formats: Option[];
    programmes: Option<number>[];
    mine: boolean;
    canSpeak: boolean;
    invitations: { received: Invitation[]; sent: Invitation[] };
}>();

const f = reactive({
    topic: (props.filters.topic as string) ?? '',
    format: (props.filters.format as string) ?? '',
    industry: (props.filters.industry as string) ?? '',
    programme: (props.filters.programme as number) ?? '',
    in_person: !!props.filters.in_person,
});
const search = () => router.get(route('speakers.index'), Object.fromEntries(Object.entries({ ...f, in_person: f.in_person ? 1 : '' }).filter(([, v]) => v)) as Record<string, string>, { preserveState: true });

const target = ref<Speaker | null>(null);
const form = useForm({ title: '', details: '', format: '', proposed_date: '' });
const formatOptions = computed(() => props.formats.filter((o) => target.value?.format_keys.includes(o.value)));
const invite = () => form.transform((d) => ({ ...d, proposed_date: d.proposed_date || null })).post(route('speakers.invite', target.value!.user_id), { preserveScroll: true, onSuccess: () => ((target.value = null), form.reset()) });
const respond = (i: Invitation, decision: 'accept' | 'decline') => router.post(route('speakers.respond', i.id), { decision }, { preserveScroll: true });
const delivered = (i: Invitation) => router.post(route('speakers.delivered', i.id), {}, { preserveScroll: true });
const badge = (s: string) => ({ accepted: 'active', delivered: 'verified', declined: 'rejected', cancelled: 'deactivated' })[s] ?? 'pending';
</script>

<template>
    <AppLayout title="Speakers">
        <PageHeader title="Speaker network" description="Alumni and faculty who speak at lectures, workshops, panels and chapter events.">
            <AppButton v-if="canSpeak" variant="secondary" :href="route('speakers.profile')">{{ mine ? 'My speaker profile' : 'Become a speaker' }}</AppButton>
        </PageHeader>

        <div v-if="invitations.received.length || invitations.sent.length" class="mb-6 grid gap-4 lg:grid-cols-2">
            <CardPanel v-if="invitations.received.length" title="Invitations to you">
                <ul class="space-y-3 text-sm">
                    <li v-for="i in invitations.received" :key="i.id">
                        <div class="flex justify-between gap-2"><span class="font-medium">{{ i.title }}</span><StatusBadge :status="badge(i.status)" :label="i.status" /></div>
                        <p class="text-muted">{{ i.format }} · from {{ i.other }}<template v-if="i.date"> · {{ i.date }}</template></p>
                        <p class="mt-1 text-ink-soft">{{ i.details }}</p>
                        <div v-if="i.status === 'pending'" class="mt-2 flex gap-2">
                            <AppButton size="sm" @click="respond(i, 'accept')">Accept</AppButton>
                            <AppButton size="sm" variant="secondary" @click="respond(i, 'decline')">Decline</AppButton>
                        </div>
                    </li>
                </ul>
            </CardPanel>
            <CardPanel v-if="invitations.sent.length" title="Invitations you sent">
                <ul class="space-y-3 text-sm">
                    <li v-for="i in invitations.sent" :key="i.id">
                        <div class="flex justify-between gap-2"><span class="font-medium">{{ i.title }}</span><StatusBadge :status="badge(i.status)" :label="i.status" /></div>
                        <p class="text-muted">{{ i.other }} · {{ i.format }}<template v-if="i.date"> · {{ i.date }}</template></p>
                        <p v-if="i.note" class="mt-1 text-muted">“{{ i.note }}”</p>
                        <AppButton v-if="i.status === 'accepted'" size="sm" variant="secondary" class="mt-2" @click="delivered(i)">Mark as delivered</AppButton>
                    </li>
                </ul>
            </CardPanel>
        </div>

        <form class="card mb-6 grid gap-3 p-4 sm:grid-cols-3 lg:grid-cols-6" role="search" @submit.prevent="search">
            <TextInput v-model="f.topic" placeholder="Topic, e.g. AI" aria-label="Topic" />
            <SelectInput v-model="f.format" :options="formats" placeholder="Any format" aria-label="Format" />
            <TextInput v-model="f.industry" placeholder="Industry" aria-label="Industry" />
            <SelectInput v-model="f.programme" :options="programmes" placeholder="Any programme" aria-label="Programme" />
            <CheckboxInput v-model="f.in_person" label="In person" />
            <AppButton type="submit">Search</AppButton>
        </form>

        <EmptyState v-if="speakers.data.length === 0" title="No speakers match" />
        <ul v-else class="grid gap-4 md:grid-cols-2">
            <li v-for="s in speakers.data" :key="s.user_id" class="card p-5">
                <PersonCard :name="s.name" :subtitle="[s.role, s.subtitle].filter(Boolean).join(' · ')" :profile-id="s.profile_id" :photo-url="s.photo_url">
                    <div class="mt-2 flex flex-wrap gap-1">
                        <span v-for="t in s.topics" :key="t" class="rounded bg-brand-50 px-2 py-0.5 text-xs text-brand-800">{{ t }}</span>
                    </div>
                    <p v-if="s.bio" class="mt-2 line-clamp-3 text-sm text-ink-soft">{{ s.bio }}</p>
                    <p class="mt-2 text-xs text-muted">{{ s.formats.join(', ') }} · {{ [s.remote ? 'Remote' : null, s.in_person ? 'In person' : null].filter(Boolean).join(' / ') }}<template v-if="s.languages"> · {{ s.languages }}</template></p>
                    <template #actions><AppButton v-if="!s.is_me" size="sm" @click="target = s">Invite</AppButton></template>
                </PersonCard>
            </li>
        </ul>
        <PaginationNav class="mt-4" :links="speakers.links" :from="speakers.from" :to="speakers.to" :total="speakers.total" />

        <ModalDialog :show="target !== null" :title="`Invite ${target?.name} to speak`" @close="target = null">
            <form id="invite-form" class="space-y-4" @submit.prevent="invite">
                <FormField label="Event or session" :error="form.errors.title" required><TextInput v-model="form.title" maxlength="200" /></FormField>
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField label="Format" :error="form.errors.format" required><SelectInput v-model="form.format" :options="formatOptions" placeholder="Choose" /></FormField>
                    <FormField label="Proposed date" :error="form.errors.proposed_date"><TextInput v-model="form.proposed_date" type="date" /></FormField>
                </div>
                <FormField label="Details" :error="form.errors.details" hint="Audience, duration, venue or link." required><TextArea v-model="form.details" rows="4" /></FormField>
            </form>
            <template #footer>
                <AppButton variant="secondary" @click="target = null">Cancel</AppButton>
                <AppButton type="submit" form="invite-form" :loading="form.processing">Send invitation</AppButton>
            </template>
        </ModalDialog>
    </AppLayout>
</template>
