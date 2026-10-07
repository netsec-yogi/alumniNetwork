<script setup lang="ts">
import { Download } from 'lucide-vue-next';
import AppButton from '@/Components/AppButton.vue';
import BarList from '@/Components/BarList.vue';
import CardPanel from '@/Components/CardPanel.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatCard from '@/Components/StatCard.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

interface Result {
    id: number;
    prompt: string;
    type: string;
    answered: number;
    options?: { label: string; value: number }[];
    average?: number | null;
    nps?: number | null;
    texts?: string[];
}

defineProps<{ survey: { slug: string; title: string; status: string; anonymous: boolean }; responses: number; invited: number; results: Result[] }>();
</script>

<template>
    <AppLayout :title="`Results · ${survey.title}`">
        <PageHeader :title="survey.title" :description="survey.anonymous ? 'Anonymous survey — individual answers aren’t linked to people.' : 'Named survey.'">
            <AppButton variant="ghost" :href="route('admin.surveys.index')">← Surveys</AppButton>
            <AppButton external :href="route('admin.surveys.export', survey.slug)" :icon="Download">Export CSV</AppButton>
        </PageHeader>
        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            <StatCard label="Responses" :value="responses" />
            <StatCard label="Invited" :value="invited" />
            <StatCard label="Response rate" :value="invited ? `${Math.round((responses / invited) * 100)}%` : '—'" />
        </div>
        <div class="space-y-4">
            <CardPanel v-for="(r, i) in results" :key="r.id" :title="`${i + 1}. ${r.prompt}`" :description="`${r.answered} answered`">
                <p v-if="r.type === 'nps'" class="text-3xl font-semibold tabular-nums">{{ r.nps ?? '—' }} <span class="text-sm font-normal text-muted">Net Promoter Score (−100 to +100)</span></p>
                <template v-else-if="r.type === 'rating'">
                    <p class="mb-3 text-sm text-muted">Average <strong class="tabular-nums">{{ r.average ?? '—' }}</strong> / 5</p>
                    <BarList :items="r.options!" />
                </template>
                <BarList v-else-if="r.options" :items="r.options" />
                <ul v-else-if="r.texts" class="max-h-80 space-y-2 overflow-y-auto text-sm">
                    <li v-for="(t, j) in r.texts" :key="j" class="rounded-lg bg-surface-muted p-3 whitespace-pre-line text-ink-soft">{{ t }}</li>
                    <li v-if="r.texts.length === 0" class="text-muted">No answers yet.</li>
                </ul>
            </CardPanel>
        </div>
    </AppLayout>
</template>
