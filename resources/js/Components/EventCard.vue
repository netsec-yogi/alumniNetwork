<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

export interface EventSummary {
    id: number;
    slug: string;
    title: string;
    type_label: string;
    summary: string | null;
    starts_at: string;
    date_badge: { day: string; month: string };
    venue: string | null;
    is_online: boolean;
    status: string;
    has_ended: boolean;
    attending: number | null;
}

defineProps<{ event: EventSummary }>();
</script>

<template>
    <Link :href="route('events.show', event.slug)" class="flex h-full gap-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200 transition hover:ring-brand-300">
        <span class="flex size-14 shrink-0 flex-col items-center justify-center rounded-lg bg-brand-800 text-white" aria-hidden="true">
            <span class="text-xs uppercase">{{ event.date_badge.month }}</span>
            <span class="text-xl leading-none font-semibold">{{ event.date_badge.day }}</span>
        </span>
        <span class="min-w-0">
            <span class="text-xs font-medium tracking-wide text-accent-600 uppercase">{{ event.type_label }}</span>
            <span class="block font-semibold text-slate-900">{{ event.title }}</span>
            <span class="block text-sm text-slate-600">{{ event.starts_at }}</span>
            <span class="block truncate text-sm text-slate-500">{{ event.is_online ? 'Online' : event.venue }}</span>
            <span v-if="event.status === 'cancelled'" class="mt-1 inline-block text-xs font-semibold text-red-700">Cancelled</span>
            <span v-else-if="event.attending" class="mt-1 block text-xs text-slate-500">{{ event.attending }} attending</span>
        </span>
    </Link>
</template>
