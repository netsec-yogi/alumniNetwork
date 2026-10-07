<script setup lang="ts">
import AlertBox from '@/Components/AlertBox.vue';
import AppButton from '@/Components/AppButton.vue';
import BarList from '@/Components/BarList.vue';
import CardPanel from '@/Components/CardPanel.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatCard from '@/Components/StatCard.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { computed } from 'vue';

const props = defineProps<{
    alumnus: Record<string, any>;
    score: { total: number; year: number };
    byMode: Record<string, number>;
    timeline: { type: string; mode: string; date: string; points: number }[];
    counts: Record<'connections' | 'events' | 'mentoring' | 'jobs' | 'communities', number>;
    verifications: { status: string; method: string; at: string; by: string | null; reason: string | null }[];
    consents: { type: string; version: string; granted: boolean; at: string }[];
    audit: { action: string; at: string; ip: string | null }[] | null;
}>();

const modes = computed(() =>
    [
        ['philanthropic', 'Philanthropic'],
        ['volunteer', 'Volunteer'],
        ['experiential', 'Experiential'],
        ['communication', 'Communications'],
    ].map(([k, label]) => ({ label, value: props.byMode[k] ?? 0 })),
);
const human = (t: string) => t.toLowerCase().replace(/_/g, ' ').replace(/^\w/, (c) => c.toUpperCase());
const a = props.alumnus;
</script>

<template>
    <AppLayout :title="a.name">
        <PageHeader :title="a.name" :description="`${a.programme} · ${a.batch} · Roll ${a.roll_number}`">
            <AppButton variant="ghost" :href="route('admin.alumni.index')">← Alumni</AppButton>
            <AppButton v-if="a.status === 'verified'" variant="secondary" :href="route('alumni.show', a.id)">Member view</AppButton>
        </PageHeader>
        <AlertBox tone="info" class="mb-6">Staff view: shows details the alumnus may have hidden from other members. Your visit is recorded in the audit log.</AlertBox>

        <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatCard label="Engagement score" :value="score.total" :hint="`${score.year} in the last 12 months`" />
            <StatCard label="Events attended" :value="counts.events" />
            <StatCard label="Mentorships given" :value="counts.mentoring" />
            <StatCard label="Connections" :value="counts.connections" :hint="`${counts.communities} groups · ${counts.jobs} jobs posted`" />
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <CardPanel title="Profile">
                    <dl class="grid gap-4 text-sm sm:grid-cols-2">
                        <div><dt class="text-muted">Email</dt><dd class="font-medium break-all">{{ a.email }}</dd></div>
                        <div><dt class="text-muted">Phone</dt><dd class="font-medium">{{ a.phone ?? '—' }}</dd></div>
                        <div><dt class="text-muted">Works at</dt><dd class="font-medium">{{ [a.designation, a.company].filter(Boolean).join(', ') || '—' }}</dd></div>
                        <div><dt class="text-muted">Industry</dt><dd class="font-medium">{{ a.industry ?? '—' }}</dd></div>
                        <div><dt class="text-muted">Location</dt><dd class="font-medium">{{ a.location || '—' }}</dd></div>
                        <div><dt class="text-muted">Department</dt><dd class="font-medium">{{ a.department ?? '—' }}</dd></div>
                        <div><dt class="text-muted">Verification</dt><dd><StatusBadge :status="a.status" /></dd></div>
                        <div><dt class="text-muted">Account</dt><dd class="font-medium capitalize">{{ a.account_status }} · last sign-in {{ a.last_login ?? 'never' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-muted">Open to</dt><dd class="font-medium">{{ a.interests.join(', ') || '—' }}</dd></div>
                    </dl>
                </CardPanel>

                <CardPanel title="Engagement timeline" description="Most recent 25 activities.">
                    <p v-if="timeline.length === 0" class="text-sm text-muted">No recorded engagement yet.</p>
                    <ol v-else class="divide-y divide-line-soft text-sm">
                        <li v-for="(t, i) in timeline" :key="i" class="flex justify-between gap-3 py-2">
                            <span>{{ human(t.type) }} <span class="text-subtle">· {{ t.mode }}</span></span>
                            <span class="shrink-0 text-muted">{{ t.date }}<span v-if="t.points" class="ml-2 font-medium text-ink-soft">+{{ t.points }}</span></span>
                        </li>
                    </ol>
                </CardPanel>
            </div>

            <div class="space-y-6">
                <CardPanel title="By CASE mode"><BarList :items="modes" /></CardPanel>
                <CardPanel title="Verification history">
                    <ul class="space-y-3 text-sm">
                        <li v-for="(v, i) in verifications" :key="i">
                            <StatusBadge :status="v.status" /> <span class="text-muted">{{ v.at }} · {{ v.method === 'auto_match' ? 'automatic' : v.by ?? 'manual' }}</span>
                            <p v-if="v.reason" class="mt-1 text-muted">{{ v.reason }}</p>
                        </li>
                    </ul>
                    <p v-if="a.institute_record" class="mt-3 text-xs text-muted">Linked record: {{ a.institute_record.name }} ({{ a.institute_record.roll_number }}, {{ a.institute_record.graduation_year }})</p>
                </CardPanel>
                <CardPanel title="Consent">
                    <ul class="space-y-1 text-sm">
                        <li v-for="(c, i) in consents" :key="i" class="flex justify-between">
                            <span>{{ human(c.type) }} <span class="text-subtle">v{{ c.version }}</span></span>
                            <span :class="c.granted ? 'text-emerald-700' : 'text-muted'">{{ c.granted ? 'Granted' : 'Declined' }} · {{ c.at }}</span>
                        </li>
                    </ul>
                </CardPanel>
                <CardPanel v-if="audit" title="Recent audit entries">
                    <ul class="space-y-1 text-xs">
                        <li v-for="(l, i) in audit" :key="i" class="flex justify-between gap-2"><code>{{ l.action }}</code><span class="text-muted">{{ l.at }}</span></li>
                    </ul>
                </CardPanel>
            </div>
        </div>
    </AppLayout>
</template>
