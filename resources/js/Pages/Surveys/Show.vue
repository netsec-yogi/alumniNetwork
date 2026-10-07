<script setup lang="ts">
import AutoBreadcrumbs from '@/Components/AutoBreadcrumbs.vue';
import AlertBox from '@/Components/AlertBox.vue';
import AppButton from '@/Components/AppButton.vue';
import CardPanel from '@/Components/CardPanel.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm } from '@inertiajs/vue3';

interface Question {
    id: number;
    type: 'single' | 'multiple' | 'text' | 'rating' | 'nps';
    prompt: string;
    options: string[] | null;
    required: boolean;
}

const props = defineProps<{
    survey: { slug: string; title: string; description: string | null; anonymous: boolean; open: boolean; closes_at: string | null; questions: Question[] };
    done: boolean;
}>();

const form = useForm<{ answers: Record<string, string | number | string[]> }>({
    answers: Object.fromEntries(props.survey.questions.map((q) => [String(q.id), q.type === 'multiple' ? [] : ''])) as Record<string, string | number | string[]>,
});
const submit = () => form.post(route('surveys.submit', props.survey.slug), { preserveScroll: true });
const err = (id: number) => (form.errors as Record<string, string>)[`answers.${id}`];
</script>

<template>
    <AppLayout :title="survey.title">
        <AutoBreadcrumbs :title="survey.title" class="mb-4" />
        <div class="mx-auto max-w-2xl">
            <h1 class="text-2xl font-semibold text-ink">{{ survey.title }}</h1>
            <p v-if="survey.description" class="mt-2 text-muted">{{ survey.description }}</p>
            <p class="mt-2 text-sm text-muted">
                {{ survey.anonymous ? 'Anonymous: your answers are stored without your name.' : 'Your name is recorded with your answers.' }}
                <template v-if="survey.closes_at"> Closes {{ survey.closes_at }}.</template>
            </p>

            <AlertBox v-if="done" tone="success" class="mt-6" title="Thank you!">You’ve already answered this survey.</AlertBox>
            <AlertBox v-else-if="!survey.open" tone="info" class="mt-6">This survey is closed.</AlertBox>

            <form v-else class="mt-6 space-y-4" @submit.prevent="submit">
                <CardPanel v-for="(q, i) in survey.questions" :key="q.id">
                    <fieldset>
                        <legend class="font-medium text-ink">{{ i + 1 }}. {{ q.prompt }}<span v-if="q.required" class="text-red-600" aria-hidden="true"> *</span></legend>
                        <div class="mt-3">
                            <div v-if="q.type === 'single'" class="space-y-2">
                                <label v-for="o in q.options ?? []" :key="o" class="flex items-center gap-2 text-sm"><input v-model="form.answers[q.id]" type="radio" :value="o" class="text-brand-700" /> {{ o }}</label>
                            </div>
                            <div v-else-if="q.type === 'multiple'" class="space-y-2">
                                <label v-for="o in q.options ?? []" :key="o" class="flex items-center gap-2 text-sm"><input v-model="form.answers[q.id]" type="checkbox" :value="o" class="rounded text-brand-700" /> {{ o }}</label>
                            </div>
                            <textarea
                                v-else-if="q.type === 'text'"
                                v-model="form.answers[q.id] as string"
                                rows="3"
                                maxlength="2000"
                                :aria-label="q.prompt"
                                class="block w-full rounded-lg border-0 text-sm ring-1 ring-line-strong ring-inset focus:ring-2 focus:ring-brand-600"
                            />
                            <div v-else class="flex flex-wrap gap-1.5">
                                <label
                                    v-for="n in q.type === 'rating' ? [1, 2, 3, 4, 5] : [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10]"
                                    :key="n"
                                    :class="['grid size-10 cursor-pointer place-items-center rounded-lg text-sm ring-1', form.answers[q.id] === n ? 'bg-brand-600 text-white ring-brand-600' : 'ring-line-strong hover:bg-surface-muted']"
                                >
                                    <input v-model="form.answers[q.id]" type="radio" :value="n" class="sr-only" />{{ n }}
                                </label>
                                <p v-if="q.type === 'nps'" class="w-full text-xs text-muted">0 = not at all likely, 10 = extremely likely</p>
                            </div>
                        </div>
                        <p v-if="err(q.id)" class="mt-2 text-sm text-red-600" role="alert">{{ err(q.id) }}</p>
                    </fieldset>
                </CardPanel>
                <AppButton type="submit" :loading="form.processing">Submit answers</AppButton>
            </form>
        </div>
    </AppLayout>
</template>
