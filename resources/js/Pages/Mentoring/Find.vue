<script setup lang="ts">
import AppButton from '@/Components/AppButton.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FormField from '@/Components/FormField.vue';
import ModalDialog from '@/Components/ModalDialog.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PersonCard from '@/Components/PersonCard.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TagInput from '@/Components/TagInput.vue';
import TextArea from '@/Components/TextArea.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Option } from '@/types';
import { router, useForm } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

interface Result {
    user_id: number;
    name: string;
    profile_id: number | null;
    subtitle: string;
    bio: string | null;
    categories: string[];
    expertise: string[];
    availability: string | null;
    mode: string | null;
    score: number;
    breakdown: Record<string, number>;
}

const props = defineProps<{
    criteria: { category?: string; interests?: string[]; industry?: string; location?: string; programme_id?: number };
    searched: boolean;
    results: Result[];
    categories: Option[];
    programmes: Option<number>[];
    defaultProgramme: number | null;
}>();

const search = reactive({
    category: props.criteria.category ?? '',
    interests: props.criteria.interests ?? ([] as string[]),
    industry: props.criteria.industry ?? '',
    location: props.criteria.location ?? '',
    programme_id: props.criteria.programme_id ?? props.defaultProgramme ?? ('' as number | ''),
});

function run() {
    const q = Object.fromEntries(Object.entries(search).filter(([, v]) => (Array.isArray(v) ? v.length : v !== '' && v !== null)));
    router.get(route('mentoring.find'), q, { preserveState: true });
}

const target = ref<Result | null>(null);
const form = useForm({ category: '', goals: '', score: 0 });
function ask(r: Result) {
    target.value = r;
    form.category = search.category;
    form.score = r.score;
}
const send = () => form.post(route('mentoring.store', target.value!.user_id), { preserveScroll: true, onSuccess: () => ((target.value = null), form.reset()) });

const labels: Record<string, string> = {
    programme: 'Programme',
    skills: 'Skills',
    career_interest: 'Interest',
    industry: 'Industry',
    experience: 'Experience',
    mentor_preference: 'Preference',
    location: 'Location',
    availability: 'Availability',
};
</script>

<template>
    <AppLayout title="Find a mentor">
        <PageHeader title="Find a mentor" description="Tell us what you’re after; we rank alumni mentors by fit.">
            <AppButton variant="ghost" :href="route('mentoring.index')">← Mentoring</AppButton>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-[18rem_1fr]">
            <form class="h-fit space-y-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200" @submit.prevent="run">
                <FormField label="I want help with"><SelectInput v-model="search.category" :options="categories" placeholder="Anything" /></FormField>
                <FormField label="Skills or topics"><TagInput v-model="search.interests" placeholder="e.g. ML, GATE, startups" /></FormField>
                <FormField label="Industry"><TextInput v-model="search.industry" placeholder="e.g. Finance" /></FormField>
                <FormField label="City or country"><TextInput v-model="search.location" /></FormField>
                <FormField label="My programme"><SelectInput v-model="search.programme_id" :options="programmes" placeholder="—" /></FormField>
                <AppButton type="submit" class="w-full">Find mentors</AppButton>
            </form>

            <section class="space-y-4">
                <EmptyState v-if="!searched" title="Start with what you’re looking for" description="Even one field is enough to get suggestions." />
                <EmptyState v-else-if="results.length === 0" title="No mentors available for that yet" description="Try a broader search — more alumni join as mentors every month." />
                <article v-for="r in results" :key="r.user_id" class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                    <PersonCard :name="r.name" :subtitle="r.subtitle" :profile-id="r.profile_id">
                        <p v-if="r.bio" class="mt-2 text-sm text-slate-700">{{ r.bio }}</p>
                        <div class="mt-2 flex flex-wrap gap-1">
                            <span v-for="c in r.categories" :key="c" class="rounded-full bg-brand-50 px-2 py-0.5 text-xs text-brand-800">{{ c }}</span>
                            <span v-for="e in r.expertise" :key="e" class="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-700">{{ e }}</span>
                        </div>
                        <p v-if="r.availability || r.mode" class="mt-2 text-xs text-slate-500">{{ [r.availability, r.mode].filter(Boolean).join(' · ') }}</p>
                        <p class="mt-2 text-xs text-slate-400">
                            Why: <span v-for="(pts, key) in r.breakdown" :key="key" class="mr-2">{{ labels[key] ?? key }} +{{ pts }}</span>
                        </p>
                        <template #actions>
                            <span class="rounded-lg bg-accent-400/15 px-2.5 py-1 text-sm font-semibold text-amber-800 tabular-nums" :aria-label="`Match score ${r.score} out of 100`">{{ r.score }}</span>
                            <AppButton size="sm" @click="ask(r)">Request</AppButton>
                        </template>
                    </PersonCard>
                </article>
            </section>
        </div>

        <ModalDialog :show="target !== null" :title="`Ask ${target?.name} to mentor you`" @close="target = null">
            <form id="mentor-request" class="space-y-4" @submit.prevent="send">
                <FormField label="Area" :error="form.errors.category" required><SelectInput v-model="form.category" :options="categories" placeholder="Choose one" /></FormField>
                <FormField label="What would you like help with?" :error="form.errors.goals" hint="Be specific — at least 30 characters." required>
                    <TextArea v-model="form.goals" rows="5" maxlength="2000" />
                </FormField>
            </form>
            <template #footer>
                <AppButton variant="secondary" @click="target = null">Cancel</AppButton>
                <AppButton type="submit" form="mentor-request" :loading="form.processing">Send request</AppButton>
            </template>
        </ModalDialog>
    </AppLayout>
</template>
