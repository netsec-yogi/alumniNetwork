<script setup lang="ts">
import ImageGalleryManager, { type GalleryImage } from '@/Components/ImageGalleryManager.vue';
import DataTable from '@/Components/DataTable.vue';
import { ask } from '@/lib/confirm';
import AlertBox from '@/Components/AlertBox.vue';
import AppButton from '@/Components/AppButton.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FormField from '@/Components/FormField.vue';
import ModalDialog from '@/Components/ModalDialog.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PaginationNav from '@/Components/PaginationNav.vue';
import StatCard from '@/Components/StatCard.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextArea from '@/Components/TextArea.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Paginated } from '@/types';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Attendee {
    id: number;
    name: string;
    email: string;
    status: string;
    guests: number;
    registered_at: string;
    checked_in_at: string | null;
}

const props = defineProps<{
    event: { id: number; slug: string; title: string; type_label: string; starts_at: string; venue: string; status: string; capacity: number | null; audience: string; cancellation_reason: string | null; batch_years: number[]; fee: number };
    stats: { confirmed: number; seats: number; waitlisted: number; cancelled: number; checked_in: number };
    attendees: Paginated<Attendee>;
    filter: string | null;
    can: { update: boolean; delete: boolean; attendance: boolean };
    images: GalleryImage[];
    canManageImages: boolean;
    imageLimitKb: number;
}>();

const cancelling = ref(false);
const cancelForm = useForm({ reason: '' });
const cancelEvent = () => cancelForm.post(route('admin.events.cancel', props.event.slug), { onSuccess: () => (cancelling.value = false) });
const publish = () => ask('Publish this event? Members will be able to see and register for it.').then((ok) => ok && router.post(route('admin.events.publish', props.event.slug)));
const inviteBatch = () => ask(`Email and notify alumni of batches ${props.event.batch_years.join(', ')}? Email only reaches those who opted in.`).then((ok) => ok && router.post(route('admin.events.invite-batch', props.event.slug)));
const destroy = () => ask('Delete this draft permanently?').then((ok) => ok && router.delete(route('admin.events.destroy', props.event.slug)));
const filters = [
    { key: null, label: 'All' },
    { key: 'confirmed', label: 'Confirmed' },
    { key: 'waitlisted', label: 'Waitlist' },
    { key: 'checked_in', label: 'Checked in' },
    { key: 'cancelled', label: 'Cancelled' },
];
</script>

<template>
    <AppLayout :title="event.title">
        <PageHeader :title="event.title" :description="`${event.type_label} · ${event.starts_at} · ${event.venue}`">
            <AppButton variant="ghost" :href="route('events.show', event.slug)">View public page</AppButton>
            <AppButton v-if="can.update && event.status !== 'cancelled'" variant="secondary" :href="route('admin.events.edit', event.slug)">Edit</AppButton>
            <AppButton v-if="can.update && event.status === 'draft'" @click="publish">Publish</AppButton>
            <AppButton v-if="can.attendance && event.status === 'published'" :href="route('admin.events.check-in', event.slug)">Check-in desk</AppButton>
            <AppButton v-if="can.update && event.status === 'published' && event.batch_years.length" variant="secondary" @click="inviteBatch">Invite batches {{ event.batch_years.join(', ') }}</AppButton>
        </PageHeader>

        <AlertBox v-if="event.status === 'draft'" tone="info" class="mb-6">This is a draft. Only event staff can see it.</AlertBox>
        <AlertBox v-if="event.status === 'cancelled'" tone="danger" class="mb-6" title="Cancelled">{{ event.cancellation_reason }}</AlertBox>

        <ImageGalleryManager
            v-if="canManageImages"
            class="mb-6"
            type="events"
            :owner-id="event.id"
            :images="images"
            :limit-kb="imageLimitKb"
            title="Event images"
            :description="`The ★ featured image appears on event cards and, for public events, on the landing page. All images form the gallery on the event page. Each is optimised to ${imageLimitKb} KB or less.`"
        />

        <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatCard label="Confirmed" :value="stats.confirmed" :hint="`${stats.seats} seats${event.capacity ? ` of ${event.capacity}` : ''}`" />
            <StatCard label="Waitlist" :value="stats.waitlisted" :tone="stats.waitlisted ? 'warning' : 'default'" />
            <StatCard label="Checked in" :value="stats.checked_in" />
            <StatCard label="Cancelled" :value="stats.cancelled" />
        </div>

        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <nav class="flex flex-wrap gap-2" aria-label="Filter attendees">
                <Link
                    v-for="f in filters"
                    :key="f.label"
                    :href="route('admin.events.show', { event: event.slug, ...(f.key ? { status: f.key } : {}) })"
                    preserve-scroll
                    :class="['rounded-md px-3 py-1.5 text-[13px] font-medium whitespace-nowrap transition-colors', filter === f.key ? 'bg-brand-600 text-white shadow-sm' : 'bg-surface text-ink-soft ring-1 ring-line-strong']"
                    >{{ f.label }}</Link
                >
            </nav>
            <a v-if="can.attendance" :href="route('admin.events.export', event.slug)" class="text-sm font-medium text-brand-700 hover:underline">Download CSV</a>
        </div>

        <DataTable :empty="attendees.data.length === 0" empty-title="No registrations yet">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Guests</th>
                        <th scope="col">Status</th>
                        <th scope="col">Registered</th>
                        <th scope="col">Checked in</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="a in attendees.data" :key="a.id">
                        <td><p class="font-medium">{{ a.name }}</p><p class="text-muted">{{ a.email }}</p></td>
                        <td class="tabular-nums">{{ a.guests }}</td>
                        <td><StatusBadge :status="a.status === 'confirmed' ? 'active' : a.status === 'waitlisted' ? 'pending' : 'deactivated'" :label="a.status" /></td>
                        <td class="text-muted">{{ a.registered_at }}</td>
                        <td class="text-muted">{{ a.checked_in_at ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>
            <template #footer><PaginationNav :links="attendees.links" :from="attendees.from" :to="attendees.to" :total="attendees.total" /></template>
        </DataTable>

        <div v-if="can.update && (event.status === 'published' || can.delete)" class="mt-10 border-t border-line pt-6">
            <h2 class="text-sm font-semibold text-ink">Danger zone</h2>
            <div class="mt-3 flex gap-2">
                <AppButton v-if="event.status === 'published'" variant="danger" @click="cancelling = true">Cancel event</AppButton>
                <AppButton v-if="can.delete" variant="danger" @click="destroy">Delete draft</AppButton>
            </div>
        </div>

        <ModalDialog :show="cancelling" title="Cancel this event?" @close="cancelling = false">
            <form id="cancel-event" @submit.prevent="cancelEvent">
                <p class="mb-4 text-sm text-muted">Everyone registered or waitlisted will be emailed with your reason.</p>
                <FormField label="Reason" :error="cancelForm.errors.reason" required><TextArea v-model="cancelForm.reason" rows="3" /></FormField>
            </form>
            <template #footer>
                <AppButton variant="secondary" @click="cancelling = false">Keep event</AppButton>
                <AppButton type="submit" form="cancel-event" variant="danger" :loading="cancelForm.processing">Cancel event</AppButton>
            </template>
        </ModalDialog>
    </AppLayout>
</template>
