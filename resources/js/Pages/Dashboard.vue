<script setup lang="ts">
import AlertBox from '@/Components/AlertBox.vue';
import AppButton from '@/Components/AppButton.vue';
import CardPanel from '@/Components/CardPanel.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    profile: {
        id: number;
        programme: string;
        graduation_year: number;
        verification_status: string;
        rejection_reason: string | null;
        completion: number;
    } | null;
    canBrowseDirectory: boolean;
    alumniCount: number;
    myEvents: { slug: string; title: string; starts_at: string }[];
    pending: { mentoring: number; referrals: number };
}>();

const user = computed(() => usePage().props.auth.user!);
const firstName = computed(() => user.value.name.split(' ')[0]);
</script>

<template>
    <AppLayout title="Dashboard">
        <PageHeader :title="`Welcome, ${firstName}`" :description="profile ? `${profile.programme} · Batch of ${profile.graduation_year}` : undefined" />

        <div class="space-y-6">
            <AlertBox v-if="profile?.verification_status === 'pending'" tone="warning" title="Verification in progress">
                We couldn't match your details to the institute's records automatically, so the alumni office is reviewing them. You'll get an email when it's done
                — usually within a few working days. The directory opens once you're verified.
            </AlertBox>
            <AlertBox v-else-if="profile?.verification_status === 'rejected'" tone="danger" title="We couldn't verify your alumni status">
                <p>{{ profile.rejection_reason }}</p>
                <p class="mt-1">Please contact the alumni office if you think this is a mistake.</p>
            </AlertBox>
            <AlertBox v-if="pending.mentoring || pending.referrals" tone="info" title="Waiting on you">
                <ul class="space-y-1">
                    <li v-if="pending.mentoring"><Link :href="route('mentoring.index', { tab: 'mentoring' })" class="underline">{{ pending.mentoring }} mentoring {{ pending.mentoring === 1 ? 'request' : 'requests' }}</Link></li>
                    <li v-if="pending.referrals"><Link :href="route('jobs.referrals')" class="underline">{{ pending.referrals }} referral {{ pending.referrals === 1 ? 'request' : 'requests' }}</Link></li>
                </ul>
            </AlertBox>
            <AlertBox v-if="!user.two_factor_enabled && !user.two_factor_required" tone="info" title="Protect your account">
                <p>Turn on two-factor authentication so a stolen password alone can't get anyone into your account.</p>
                <div class="mt-2"><AppButton variant="ghost" size="sm" :href="route('profile.security')" class="-ml-3">Set up 2FA →</AppButton></div>
            </AlertBox>

            <div class="grid gap-6 lg:grid-cols-3">
                <CardPanel v-if="profile" title="Your profile">
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-slate-500">Status</dt>
                            <dd><StatusBadge :status="profile.verification_status" /></dd>
                        </div>
                        <div>
                            <div class="flex justify-between">
                                <dt class="text-slate-500">Completeness</dt>
                                <dd class="font-medium tabular-nums">{{ profile.completion }}%</dd>
                            </div>
                            <div class="mt-2 h-2 rounded-full bg-slate-100" role="progressbar" :aria-valuenow="profile.completion" aria-valuemin="0" aria-valuemax="100">
                                <div class="h-2 rounded-full bg-brand-600" :style="{ width: `${profile.completion}%` }" />
                            </div>
                        </div>
                    </dl>
                    <div class="mt-5 flex flex-wrap gap-2">
                        <AppButton size="sm" :href="route('profile.edit')">Edit profile</AppButton>
                        <AppButton v-if="profile.verification_status === 'verified'" size="sm" variant="secondary" :href="route('alumni.show', profile.id)">View public profile</AppButton>
                    </div>
                </CardPanel>

                <CardPanel title="Alumni directory" :description="`${alumniCount.toLocaleString('en-IN')} verified alumni`">
                    <p class="text-sm text-slate-600">Search by batch, programme, company and city.</p>
                    <AppButton v-if="canBrowseDirectory" size="sm" class="mt-4" :href="route('directory')">Browse directory</AppButton>
                    <p v-else class="mt-4 text-sm text-slate-500">Available once your alumni status is verified.</p>
                </CardPanel>

                <CardPanel title="Your upcoming events">
                    <p v-if="myEvents.length === 0" class="text-sm text-slate-600">Nothing booked yet.</p>
                    <ul v-else class="space-y-3 text-sm">
                        <li v-for="e in myEvents" :key="e.slug">
                            <Link :href="route('events.show', e.slug)" class="font-medium text-brand-700 hover:underline">{{ e.title }}</Link>
                            <p class="text-slate-500">{{ e.starts_at }}</p>
                        </li>
                    </ul>
                    <AppButton size="sm" variant="secondary" class="mt-4" :href="route('events.index')">Browse events</AppButton>
                </CardPanel>
            </div>
        </div>
    </AppLayout>
</template>
