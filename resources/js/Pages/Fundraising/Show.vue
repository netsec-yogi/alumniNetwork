<script setup lang="ts">
import AutoBreadcrumbs from '@/Components/AutoBreadcrumbs.vue';
import AlertBox from '@/Components/AlertBox.vue';
import AppButton from '@/Components/AppButton.vue';
import BarList from '@/Components/BarList.vue';
import CampaignProgress from '@/Components/CampaignProgress.vue';
import CardPanel from '@/Components/CardPanel.vue';
import FormField from '@/Components/FormField.vue';
import TextArea from '@/Components/TextArea.vue';
import TextInput from '@/Components/TextInput.vue';
import SiteLayout from '@/Layouts/SiteLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

interface Board { label: string; donors: number; raised: number }

const props = defineProps<{
    campaign: {
        slug: string;
        type: string;
        type_label: string;
        title: string;
        summary: string;
        category: string;
        stage: string;
        cover_url: string | null;
        goal: number;
        raised: number;
        matched: number;
        donors: number;
        days_left: number | null;
        matching: { sponsor: string; ratio: number; cap: number | null } | null;
        story_html: string;
        organizer: string;
        community: { name: string; slug: string } | null;
        starts_at: string;
        ends_at: string;
        ends_at_iso: string;
        status: string;
        rejection_reason: string | null;
    };
    recent: { name: string; when: string }[];
    leaderboards: { batches: Board[]; chapters: Board[] } | null;
    updates: { title: string; body: string; author: string | null; at: string }[];
    canManage: boolean;
}>();

// Giving Day countdown.
const now = ref(Date.now());
let timer: ReturnType<typeof setInterval>;
onMounted(() => (timer = setInterval(() => (now.value = Date.now()), 1000)));
onUnmounted(() => clearInterval(timer));
const countdown = computed(() => {
    const ms = new Date(props.campaign.ends_at_iso).getTime() - now.value;
    if (ms <= 0) return null;
    const s = Math.floor(ms / 1000);
    return `${Math.floor(s / 3600)}h ${String(Math.floor((s % 3600) / 60)).padStart(2, '0')}m ${String(s % 60).padStart(2, '0')}s`;
});

const updateForm = useForm({ title: '', body: '' });
const postUpdate = () => updateForm.post(route('fundraising.updates.store', props.campaign.slug), { preserveScroll: true, onSuccess: () => updateForm.reset() });
const boardItems = (rows: Board[]) => rows.map((r) => ({ label: r.label, value: r.donors, hint: `· ₹${r.raised.toLocaleString('en-IN')}` }));
</script>

<template>
    <SiteLayout :title="campaign.title">
        <AutoBreadcrumbs :title="campaign.title" class="mb-4" />
        <AlertBox v-if="campaign.status !== 'published'" :tone="campaign.status === 'rejected' ? 'danger' : 'warning'" class="mb-6" :title="`This campaign is ${campaign.status.replace('_', ' ')}`">
            {{ campaign.rejection_reason ?? 'Only you and the fundraising office can see it.' }}
        </AlertBox>

        <div class="grid gap-8 lg:grid-cols-[1fr_22rem]">
            <article>
                <p class="text-sm font-medium tracking-wide text-accent-600 uppercase">{{ campaign.type_label }} · {{ campaign.category }}</p>
                <h1 class="mt-1 text-3xl font-semibold tracking-tight text-ink">{{ campaign.title }}</h1>
                <p class="mt-2 text-lg text-muted">{{ campaign.summary }}</p>
                <p class="mt-2 text-sm text-muted">
                    Organised by {{ campaign.organizer }}
                    <template v-if="campaign.community"> · <Link :href="route('communities.show', campaign.community.slug)" class="hover:underline">{{ campaign.community.name }}</Link></template>
                </p>
                <img v-if="campaign.cover_url" :src="campaign.cover_url" alt="" class="mt-6 w-full rounded-xl" />
                <!-- Sanitised Markdown (FundraisingCampaign::storyHtml). -->
                <div class="story-body mt-6" v-html="campaign.story_html" />

                <section v-if="updates.length || canManage" class="mt-10">
                    <h2 class="text-lg font-semibold text-ink">Updates</h2>
                    <ul class="mt-3 space-y-4">
                        <li v-for="(u, i) in updates" :key="i" class="card p-5">
                            <p class="font-medium">{{ u.title }}</p>
                            <p class="text-xs text-muted">{{ u.at }}<template v-if="u.author"> · {{ u.author }}</template></p>
                            <p class="mt-2 text-sm whitespace-pre-line text-ink-soft">{{ u.body }}</p>
                        </li>
                    </ul>
                    <form v-if="canManage && campaign.status === 'published'" class="mt-4 space-y-3 rounded-xl bg-surface p-5 ring-1 ring-line" @submit.prevent="postUpdate">
                        <FormField label="Update title" :error="updateForm.errors.title"><TextInput v-model="updateForm.title" /></FormField>
                        <FormField label="Update" :error="updateForm.errors.body"><TextArea v-model="updateForm.body" rows="3" /></FormField>
                        <AppButton type="submit" size="sm" :loading="updateForm.processing">Post update</AppButton>
                    </form>
                </section>
            </article>

            <aside class="space-y-4 lg:sticky lg:top-24 lg:self-start">
                <CardPanel>
                    <p v-if="campaign.type === 'giving_day' && campaign.stage === 'active' && countdown" class="mb-3 text-center text-sm font-semibold text-accent-600 tabular-nums">Ends in {{ countdown }}</p>
                    <CampaignProgress :raised="campaign.raised" :matched="campaign.matched" :goal="campaign.goal" :donors="campaign.donors" :days-left="campaign.days_left" large />
                    <p v-if="campaign.matching" class="mt-3 rounded-lg bg-accent-400/15 p-2 text-sm text-amber-900">
                        {{ campaign.matching.sponsor }} matches every gift {{ campaign.matching.ratio }}:1<template v-if="campaign.matching.cap"> up to ₹{{ campaign.matching.cap.toLocaleString('en-IN') }}</template>.
                    </p>
                    <AppButton v-if="campaign.stage === 'active'" class="mt-4 w-full" :href="route('giving.index', { campaign: campaign.slug })">Donate to this campaign</AppButton>
                    <p v-else-if="campaign.stage === 'upcoming'" class="mt-4 text-center text-sm text-muted">Opens {{ campaign.starts_at }}</p>
                    <p v-else-if="campaign.stage === 'completed'" class="mt-4 text-center text-sm text-muted">Ended {{ campaign.ends_at }} — thank you!</p>
                    <AppButton v-if="canManage && ['draft', 'rejected'].includes(campaign.status)" variant="secondary" class="mt-2 w-full" :href="route('fundraising.edit', campaign.slug)">Edit</AppButton>
                </CardPanel>
                <CardPanel v-if="leaderboards?.batches.length" title="Batch leaderboard" description="Ranked by number of donors."><BarList :items="boardItems(leaderboards.batches)" /></CardPanel>
                <CardPanel v-if="leaderboards?.chapters.length" title="Chapter leaderboard"><BarList :items="boardItems(leaderboards.chapters)" /></CardPanel>
                <CardPanel v-if="recent.length" title="Recent supporters">
                    <ul class="space-y-1 text-sm">
                        <li v-for="(r, i) in recent" :key="i" class="flex justify-between"><span>{{ r.name }}</span><span class="text-subtle">{{ r.when }}</span></li>
                    </ul>
                </CardPanel>
            </aside>
        </div>
    </SiteLayout>
</template>
