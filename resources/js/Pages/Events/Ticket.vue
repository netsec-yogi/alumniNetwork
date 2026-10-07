<script setup lang="ts">
import AppButton from '@/Components/AppButton.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps<{
    event: { slug: string; title: string; starts_at: string; venue: string | null; is_online: boolean; online_url: string | null };
    holder: string;
    guests: number;
    reference: string;
    checkedIn: string | null;
    qrSvg: string;
}>();
</script>

<template>
    <AppLayout title="Ticket">
        <div class="mx-auto max-w-sm">
            <AppButton variant="ghost" class="-ml-3 mb-4" :href="route('events.show', event.slug)">← Event</AppButton>
            <div class="overflow-hidden rounded-2xl bg-surface shadow-sm ring-1 ring-line">
                <div class="bg-deep-800 px-6 py-5 text-white">
                    <p class="text-xs tracking-wide text-white/70 uppercase">Admit {{ 1 + guests }}</p>
                    <h1 class="mt-1 text-lg font-semibold">{{ event.title }}</h1>
                    <p class="text-sm text-white/85">{{ event.starts_at }}</p>
                    <p class="text-sm text-white/85">{{ event.is_online ? 'Online' : event.venue }}</p>
                </div>
                <div class="flex flex-col items-center px-6 py-6">
                    <!-- Server-generated SVG; encodes the staff check-in link for this ticket. -->
                    <div class="rounded-lg bg-surface p-2 ring-1 ring-line" v-html="qrSvg" />
                    <p class="mt-4 font-medium text-ink">{{ holder }}<span v-if="guests"> + {{ guests }}</span></p>
                    <p class="text-sm text-muted">Ref {{ reference }}</p>
                    <p v-if="checkedIn" class="mt-3 rounded-full bg-emerald-50 px-3 py-1 text-sm font-medium text-emerald-700">Checked in {{ checkedIn }}</p>
                    <p v-else class="mt-3 text-center text-sm text-muted">Show this code at the entrance. Don’t share it — it admits one booking once.</p>
                    <a v-if="event.online_url" :href="event.online_url" target="_blank" rel="noopener noreferrer" class="mt-4 text-sm font-medium text-brand-700 hover:underline">Join online ↗</a>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
