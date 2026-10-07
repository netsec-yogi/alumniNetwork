<script setup lang="ts">
import BarList from '@/Components/BarList.vue';
import CardPanel from '@/Components/CardPanel.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatCard from '@/Components/StatCard.vue';
import TrendBars from '@/Components/TrendBars.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

type Point = { label: string; value: number };
type Item = { label: string; value: number; hint?: string };

defineProps<{
    registrations: Point[];
    engaged: Point[];
    modes: { mode: string; label: string; series: Point[] }[];
    retention: { previous: number; retained: number; rate: number | null; new: number };
    cohorts: Item[];
    countries: Item[];
    cities: Item[];
    industries: Item[];
    companies: Item[];
}>();
</script>

<template>
    <AppLayout title="Analytics">
        <PageHeader title="Analytics" description="Last 12 months. Aggregates only; individual records stay in the alumni 360° view." />

        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            <StatCard label="Engagement retention" :value="retention.rate === null ? '—' : `${retention.rate}%`" :hint="`${retention.retained} of ${retention.previous} engaged the year before came back`" />
            <StatCard label="Newly engaged" :value="retention.new" hint="engaged this year, not last year" />
            <StatCard label="Engaged this month" :value="engaged[engaged.length - 1]?.value ?? 0" />
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <CardPanel title="New registrations per month"><TrendBars :data="registrations" label="New registrations per month" /></CardPanel>
            <CardPanel title="Engaged alumni per month" description="Distinct alumni with any activity."><TrendBars :data="engaged" label="Engaged alumni per month" /></CardPanel>
        </div>

        <h2 class="mt-8 mb-3 text-sm font-semibold tracking-wide text-muted uppercase">Activity by CASE mode</h2>
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            <CardPanel v-for="m in modes" :key="m.mode" :title="m.label" :description="`${m.series.reduce((s, p) => s + p.value, 0)} activities`">
                <TrendBars :data="m.series" :label="`${m.label} activities per month`" :height="90" />
            </CardPanel>
        </div>

        <div class="mt-8 grid gap-6 lg:grid-cols-2">
            <CardPanel title="Participation by batch" description="Share of verified alumni in each 5-year band engaged in the last 12 months."><BarList :items="cohorts" suffix="%" :max="100" /></CardPanel>
            <CardPanel title="Top employers"><BarList :items="companies" /></CardPanel>
            <CardPanel title="Countries"><BarList :items="countries" /></CardPanel>
            <CardPanel title="Cities"><BarList :items="cities" /></CardPanel>
            <CardPanel title="Industries"><BarList :items="industries" /></CardPanel>
        </div>
    </AppLayout>
</template>
