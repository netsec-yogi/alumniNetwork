<script setup lang="ts">
import AvatarImage from '@/Components/AvatarImage.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PageHeader from '@/Components/PageHeader.vue';
import SiteLayout from '@/Layouts/SiteLayout.vue';

interface Honouree {
    id: number;
    award_year: number;
    citation: string;
    company: string | null;
    designation: string | null;
    person: { name: string; batch: string; photo_url: string | null };
}

defineProps<{ honourees: Record<string, Honouree[]> }>();
</script>

<template>
    <SiteLayout title="Distinguished alumni">
        <PageHeader title="Distinguished alumni" description="Honouring IIITM graduates whose work has shaped industry, research, public life and society." />
        <EmptyState v-if="Object.keys(honourees).length === 0" title="Honours will be announced soon" />
        <section v-for="(people, category) in honourees" :key="category" class="mb-10">
            <h2 class="mb-4 text-lg font-semibold text-brand-900">{{ category }}</h2>
            <ul class="grid gap-4 md:grid-cols-2">
                <li v-for="h in people" :key="h.id" class="card flex gap-4 p-5">
                    <AvatarImage :name="h.person.name" :src="h.person.photo_url" size="lg" />
                    <div>
                        <p class="font-semibold text-ink">{{ h.person.name }}</p>
                        <p class="text-sm text-muted">{{ h.person.batch }}<template v-if="h.designation || h.company"> · {{ [h.designation, h.company].filter(Boolean).join(', ') }}</template></p>
                        <p class="mt-1 text-xs font-medium text-accent-600">Distinguished Alumnus {{ h.award_year }}</p>
                        <p class="mt-2 text-sm whitespace-pre-line text-ink-soft">{{ h.citation }}</p>
                    </div>
                </li>
            </ul>
        </section>
    </SiteLayout>
</template>
