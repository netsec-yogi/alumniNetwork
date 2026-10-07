<script setup lang="ts">
import { Download } from 'lucide-vue-next';
import AppButton from '@/Components/AppButton.vue';
import BarList from '@/Components/BarList.vue';
import CardPanel from '@/Components/CardPanel.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatCard from '@/Components/StatCard.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

const props = defineProps<{
    range: { from: string; to: string };
    alumni: { verified: number; new: number; engaged: number; engagement_rate: number };
    case: { mode: string; label: string; alumni: number; activities: number; rate: number }[];
    byType: Record<string, number>;
    events: { held: number; registrations: number; attended: number };
    career: { jobs: number; internships: number; referrals: number };
    mentoring: { requests: number; accepted: number; completed: number };
    community: { communities: number; chapters: number; posts: number };
    giving: { raised: number; gifts: number; donors: number };
    volunteering: { hours: number; volunteers: number; talks: number };
    leaders: { id: number; name: string | null; programme: string | null; score: number; activities: number }[];
    weights: Record<string, number>;
    canExport: boolean;
}>();

const form = reactive({ ...props.range });
const apply = () => router.get(route('admin.reports.index'), form, { preserveState: true });
const human = (t: string) => t.toLowerCase().replace(/_/g, ' ').replace(/^\w/, (c) => c.toUpperCase());
const caseBars = computed(() => props.case.map((c) => ({ label: c.label, value: c.rate, hint: `· ${c.alumni} alumni` })));
const typeBars = computed(() => Object.entries(props.byType).map(([t, n]) => ({ label: human(t), value: n })));
const attendance = computed(() => (props.events.registrations ? Math.round((props.events.attended / props.events.registrations) * 100) : 0));
</script>

<template>
    <AppLayout title="Reports">
        <PageHeader title="Engagement reports" description="Counts come from recorded activity; the score is shown alongside, not instead (SRS 55).">
            <AppButton v-if="canExport" external :href="route('admin.reports.export', form)" :icon="Download">Download CSV</AppButton>
        </PageHeader>

        <form class="mb-6 flex flex-wrap items-end gap-3" @submit.prevent="apply">
            <label class="text-sm text-muted">From <TextInput v-model="form.from" type="date" class="mt-1" /></label>
            <label class="text-sm text-muted">To <TextInput v-model="form.to" type="date" class="mt-1" /></label>
            <AppButton type="submit" variant="secondary">Apply</AppButton>
        </form>

        <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatCard label="Verified alumni" :value="alumni.verified" :hint="`${alumni.new} registered in range`" />
            <StatCard label="Engaged alumni" :value="alumni.engaged" :hint="`${alumni.engagement_rate}% of verified`" />
            <StatCard label="Event attendance" :value="`${attendance}%`" :hint="`${events.attended} of ${events.registrations} registrations · ${events.held} events`" />
            <StatCard label="Mentorships completed" :value="mentoring.completed" :hint="`${mentoring.accepted} accepted of ${mentoring.requests} requests`" />
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <CardPanel title="CASE engagement rate" description="Share of verified alumni with at least one activity in each mode.">
                <BarList :items="caseBars" suffix="%" :max="100" />
            </CardPanel>
            <CardPanel title="Activities by type">
                <p v-if="typeBars.length === 0" class="text-sm text-muted">No activity in this range.</p>
                <BarList v-else :items="typeBars" />
            </CardPanel>
            <CardPanel title="Most engaged alumni" :description="`Score weights: ${Object.entries(weights).map(([k, v]) => `${human(k)} ${v}`).join(', ')}`">
                <p v-if="leaders.length === 0" class="text-sm text-muted">No activity in this range.</p>
                <ol v-else class="divide-y divide-line-soft text-sm">
                    <li v-for="(l, i) in leaders" :key="l.id" class="flex justify-between gap-3 py-2">
                        <span><span class="mr-2 text-subtle tabular-nums">{{ i + 1 }}.</span><Link :href="route('admin.alumni.show', l.id)" class="font-medium text-brand-800 hover:underline">{{ l.name }}</Link> <span class="text-muted">{{ l.programme }}</span></span>
                        <span class="font-medium tabular-nums">{{ l.score }} <span class="font-normal text-muted">· {{ l.activities }} acts</span></span>
                    </li>
                </ol>
            </CardPanel>
            <CardPanel title="Giving & volunteering">
                <dl class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
                    <div><dt class="text-muted">Raised</dt><dd class="text-xl font-semibold tabular-nums">₹{{ giving.raised.toLocaleString('en-IN') }}</dd></div>
                    <div><dt class="text-muted">Gifts</dt><dd class="text-xl font-semibold tabular-nums">{{ giving.gifts }}</dd></div>
                    <div><dt class="text-muted">Donors</dt><dd class="text-xl font-semibold tabular-nums">{{ giving.donors }}</dd></div>
                    <div><dt class="text-muted">Volunteer hours</dt><dd class="text-xl font-semibold tabular-nums">{{ volunteering.hours }}</dd></div>
                    <div><dt class="text-muted">Volunteers</dt><dd class="text-xl font-semibold tabular-nums">{{ volunteering.volunteers }}</dd></div>
                    <div><dt class="text-muted">Guest talks given</dt><dd class="text-xl font-semibold tabular-nums">{{ volunteering.talks }}</dd></div>
                </dl>
            </CardPanel>
            <CardPanel title="Career & community">
                <dl class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
                    <div><dt class="text-muted">Jobs posted</dt><dd class="text-xl font-semibold tabular-nums">{{ career.jobs }}</dd></div>
                    <div><dt class="text-muted">Internships</dt><dd class="text-xl font-semibold tabular-nums">{{ career.internships }}</dd></div>
                    <div><dt class="text-muted">Referrals given</dt><dd class="text-xl font-semibold tabular-nums">{{ career.referrals }}</dd></div>
                    <div><dt class="text-muted">Communities</dt><dd class="text-xl font-semibold tabular-nums">{{ community.communities }}</dd></div>
                    <div><dt class="text-muted">Chapters</dt><dd class="text-xl font-semibold tabular-nums">{{ community.chapters }}</dd></div>
                    <div><dt class="text-muted">Posts</dt><dd class="text-xl font-semibold tabular-nums">{{ community.posts }}</dd></div>
                </dl>
            </CardPanel>
        </div>
    </AppLayout>
</template>
