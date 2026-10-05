<script setup lang="ts">
import AlertBox from '@/Components/AlertBox.vue';
import AppButton from '@/Components/AppButton.vue';
import CardPanel from '@/Components/CardPanel.vue';
import FormField from '@/Components/FormField.vue';
import SelectInput from '@/Components/SelectInput.vue';
import ShareToFeed from '@/Components/ShareToFeed.vue';
import SiteLayout from '@/Layouts/SiteLayout.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface EventDetail {
    id: number;
    slug: string;
    title: string;
    type_label: string;
    summary: string | null;
    description: string | null;
    starts_at: string;
    ends_at: string;
    venue: string | null;
    is_online: boolean;
    online_url: string | null;
    status: string;
    audience: string;
    has_ended: boolean;
    capacity: number | null;
    seats_left: number | null;
    max_guests: number;
    registration_open: boolean;
    registration_closes_at: string | null;
    cancellation_reason: string | null;
}

const props = defineProps<{
    event: EventDetail;
    registration: { status: 'confirmed' | 'waitlisted'; guests: number; checked_in: boolean; waitlist_position: number | null } | null;
    canRegister: boolean;
    canManage: boolean;
}>();

const signedIn = !!usePage().props.auth.user;
const form = useForm({ guests: 0 });
const guestOptions = computed(() => Array.from({ length: props.event.max_guests + 1 }, (_, i) => ({ value: i, label: i === 0 ? 'Just me' : `Me + ${i}` })));
const register = () => form.post(route('events.register', props.event.slug), { preserveScroll: true });
const cancel = () => confirm('Cancel your registration? Your seat will go to the next person on the waitlist.') && router.delete(route('events.cancel', props.event.slug), { preserveScroll: true });
</script>

<template>
    <SiteLayout :title="event.title">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-2">
            <AppButton variant="ghost" class="-ml-3" :href="route('events.index')">← All events</AppButton>
            <AppButton v-if="canManage" variant="secondary" :href="route('admin.events.show', event.slug)">Manage event</AppButton>
        </div>

        <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
            <article class="space-y-6">
                <AlertBox v-if="event.status === 'cancelled'" tone="danger" title="This event has been cancelled">{{ event.cancellation_reason }}</AlertBox>
                <CardPanel>
                    <p class="text-sm font-medium tracking-wide text-accent-600 uppercase">{{ event.type_label }}</p>
                    <h1 class="mt-1 text-2xl font-semibold text-slate-900">{{ event.title }}</h1>
                    <p v-if="event.summary" class="mt-2 text-slate-600">{{ event.summary }}</p>
                    <div v-if="event.description" class="mt-6 text-sm leading-relaxed whitespace-pre-line text-slate-700">{{ event.description }}</div>
                </CardPanel>
            </article>

            <aside class="space-y-4">
                <CardPanel>
                    <dl class="space-y-3 text-sm">
                        <div><dt class="text-slate-500">Starts</dt><dd class="font-medium">{{ event.starts_at }}</dd></div>
                        <div><dt class="text-slate-500">Ends</dt><dd class="font-medium">{{ event.ends_at }}</dd></div>
                        <div><dt class="text-slate-500">Where</dt><dd class="font-medium">{{ event.is_online ? 'Online' : event.venue }}</dd></div>
                        <div v-if="event.seats_left !== null"><dt class="text-slate-500">Seats left</dt><dd class="font-medium">{{ event.seats_left }} of {{ event.capacity }}</dd></div>
                        <div v-if="event.registration_closes_at"><dt class="text-slate-500">Registration closes</dt><dd class="font-medium">{{ event.registration_closes_at }}</dd></div>
                    </dl>
                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <a :href="route('events.calendar', event.slug)" class="text-sm font-medium text-brand-700 hover:underline">Add to calendar (.ics)</a>
                        <ShareToFeed v-if="signedIn && event.status === 'published'" type="event" :id="event.id" :title="event.title" />
                    </div>
                </CardPanel>

                <CardPanel v-if="event.status !== 'cancelled' && !event.has_ended">
                    <template v-if="registration">
                        <AlertBox v-if="registration.status === 'confirmed'" tone="success" title="You’re going">
                            {{ registration.guests ? `You + ${registration.guests} guest${registration.guests > 1 ? 's' : ''}.` : '' }}
                            <span v-if="registration.checked_in">Checked in.</span>
                        </AlertBox>
                        <AlertBox v-else tone="warning" title="You’re on the waitlist">Position {{ registration.waitlist_position }}. We’ll email you if a seat opens up.</AlertBox>
                        <p v-if="event.online_url" class="mt-3 text-sm">
                            Joining link: <a :href="event.online_url" target="_blank" rel="noopener noreferrer" class="font-medium break-all text-brand-700 hover:underline">{{ event.online_url }}</a>
                        </p>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <AppButton v-if="registration.status === 'confirmed'" :href="route('events.ticket', event.slug)">View ticket</AppButton>
                            <AppButton v-if="!registration.checked_in" variant="secondary" @click="cancel">Cancel registration</AppButton>
                        </div>
                    </template>
                    <template v-else-if="!signedIn">
                        <p class="text-sm text-slate-600">Sign in to register for this event.</p>
                        <AppButton class="mt-3 w-full" :href="route('login')">Sign in to register</AppButton>
                    </template>
                    <template v-else-if="canRegister && event.registration_open">
                        <form class="space-y-4" @submit.prevent="register">
                            <FormField v-if="event.max_guests > 0" label="Who’s coming?" :error="form.errors.guests">
                                <SelectInput v-model="form.guests" :options="guestOptions" />
                            </FormField>
                            <AppButton type="submit" class="w-full" :loading="form.processing">{{ event.seats_left === 0 ? 'Join waitlist' : 'Register' }}</AppButton>
                        </form>
                    </template>
                    <p v-else class="text-sm text-slate-600">{{ event.registration_open ? 'Registration is open to IIITM community members.' : 'Registration is not open.' }}</p>
                </CardPanel>
            </aside>
        </div>
    </SiteLayout>
</template>
