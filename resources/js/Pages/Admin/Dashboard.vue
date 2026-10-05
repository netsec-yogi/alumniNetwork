<script setup lang="ts">
import AlertBox from '@/Components/AlertBox.vue';
import CardPanel from '@/Components/CardPanel.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatCard from '@/Components/StatCard.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    stats: { totalAlumni: number; verified: number; pending: number; rejected: number; newThisMonth: number; activeUsers30d: number };
    byProgramme: { name: string; total: number }[];
    security: {
        failedLogins: number;
        rateLimited: number;
        lockedAccounts: number;
        roleChanges: number;
        adminsWithout2fa: number;
        recent: { id: number; action: string; user: string | null; ip: string | null; at: string }[];
    } | null;
}>();

const can = (p: string) => usePage().props.auth.user!.permissions.includes(p);
const maxProgramme = computed(() => Math.max(1, ...props.byProgramme.map((p) => p.total)));
</script>

<template>
    <AppLayout title="Administration">
        <PageHeader title="Administration" description="Alumni network at a glance." />

        <div class="space-y-6">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard label="Registered alumni" :value="stats.totalAlumni" :hint="`${stats.newThisMonth} new this month`" />
                <StatCard label="Verified" :value="stats.verified" />
                <Link v-if="can('alumni.verify')" :href="route('admin.verification.index')" class="block rounded-xl hover:ring-2 hover:ring-brand-300">
                    <StatCard label="Awaiting verification" :value="stats.pending" :tone="stats.pending > 0 ? 'warning' : 'default'" hint="Open the queue →" />
                </Link>
                <StatCard v-else label="Awaiting verification" :value="stats.pending" />
                <StatCard label="Active users (30 days)" :value="stats.activeUsers30d" />
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <CardPanel title="Verified alumni by programme">
                    <p v-if="byProgramme.length === 0" class="text-sm text-slate-500">No verified alumni yet.</p>
                    <ul v-else class="space-y-3">
                        <li v-for="p in byProgramme" :key="p.name">
                            <div class="flex justify-between text-sm">
                                <span class="truncate text-slate-700">{{ p.name }}</span>
                                <span class="font-medium tabular-nums">{{ p.total }}</span>
                            </div>
                            <div class="mt-1 h-2 rounded-full bg-slate-100">
                                <div class="h-2 rounded-full bg-brand-600" :style="{ width: `${(p.total / maxProgramme) * 100}%` }" />
                            </div>
                        </li>
                    </ul>
                </CardPanel>

                <CardPanel v-if="security" title="Security" description="Last 24 hours unless noted.">
                    <AlertBox v-if="security.adminsWithout2fa > 0" tone="warning" class="mb-4">
                        {{ security.adminsWithout2fa }} privileged {{ security.adminsWithout2fa === 1 ? 'account has' : 'accounts have' }} not enrolled in 2FA yet. They cannot use the portal until they do.
                    </AlertBox>
                    <dl class="grid grid-cols-2 gap-4 text-sm">
                        <div><dt class="text-slate-500">Failed sign-ins</dt><dd class="text-xl font-semibold tabular-nums">{{ security.failedLogins }}</dd></div>
                        <div><dt class="text-slate-500">Rate-limited</dt><dd class="text-xl font-semibold tabular-nums">{{ security.rateLimited }}</dd></div>
                        <div>
                            <dt class="text-slate-500">Locked accounts (now)</dt>
                            <dd :class="['text-xl font-semibold tabular-nums', security.lockedAccounts ? 'text-red-600' : '']">{{ security.lockedAccounts }}</dd>
                        </div>
                        <div><dt class="text-slate-500">Role changes (7 days)</dt><dd class="text-xl font-semibold tabular-nums">{{ security.roleChanges }}</dd></div>
                    </dl>
                    <h3 class="mt-6 text-sm font-medium text-slate-900">Recent security events</h3>
                    <ul class="mt-2 divide-y divide-slate-100 text-sm">
                        <li v-for="e in security.recent" :key="e.id" class="flex justify-between gap-3 py-2">
                            <span><code class="text-xs">{{ e.action }}</code> <span class="text-slate-500">{{ e.user ?? '—' }}</span></span>
                            <span class="shrink-0 text-slate-500">{{ e.at }}</span>
                        </li>
                        <li v-if="security.recent.length === 0" class="py-2 text-slate-500">Nothing recorded.</li>
                    </ul>
                    <Link v-if="can('audit.view')" :href="route('admin.audit-logs.index')" class="mt-3 inline-block text-sm font-medium text-brand-700">Full audit log →</Link>
                </CardPanel>
            </div>
        </div>
    </AppLayout>
</template>
