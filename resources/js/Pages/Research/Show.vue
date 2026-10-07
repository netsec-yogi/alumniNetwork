<script setup lang="ts">
import { ask } from '@/lib/confirm';
import AutoBreadcrumbs from '@/Components/AutoBreadcrumbs.vue';
import AlertBox from '@/Components/AlertBox.vue';
import AppButton from '@/Components/AppButton.vue';
import CardPanel from '@/Components/CardPanel.vue';
import FormField from '@/Components/FormField.vue';
import TextArea from '@/Components/TextArea.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    opportunity: { id: number; type: string; title: string; description: string; organization: string | null; areas: string[]; closes_on: string | null; is_open: boolean; poster: { name: string; profile_id: number | null } };
    isPoster: boolean;
    myInterest: string | null;
    interests: { name: string; email: string; note: string; at: string }[];
}>();
const form = useForm({ note: props.myInterest ?? '' });
const submit = () => form.post(route('research.interest', props.opportunity.id), { preserveScroll: true });
const close = () => ask('Close this opportunity?').then((ok) => ok && router.post(route('research.close', props.opportunity.id)));
</script>

<template>
    <AppLayout :title="opportunity.title">
        <AutoBreadcrumbs :title="opportunity.title" class="mb-4" />
        <div class="mb-6 flex items-center justify-between">
            <AppButton variant="ghost" class="-ml-3" :href="route('research.index')">← Research</AppButton>
            <AppButton v-if="isPoster && opportunity.is_open" variant="secondary" @click="close">Close</AppButton>
        </div>
        <AlertBox v-if="!opportunity.is_open" tone="info" class="mb-6">This opportunity is closed.</AlertBox>
        <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
            <CardPanel>
                <p class="text-sm font-medium tracking-wide text-accent-600 uppercase">{{ opportunity.type }}</p>
                <h1 class="mt-1 text-2xl font-semibold text-ink">{{ opportunity.title }}</h1>
                <p class="text-sm text-muted">
                    <Link v-if="opportunity.poster.profile_id" :href="route('alumni.show', opportunity.poster.profile_id)" class="hover:underline">{{ opportunity.poster.name }}</Link>
                    <template v-else>{{ opportunity.poster.name }}</template>
                    <template v-if="opportunity.organization"> · {{ opportunity.organization }}</template>
                    <template v-if="opportunity.closes_on"> · closes {{ opportunity.closes_on }}</template>
                </p>
                <div v-if="opportunity.areas.length" class="mt-3 flex flex-wrap gap-1"><span v-for="a in opportunity.areas" :key="a" class="rounded bg-surface-sunken px-2 py-0.5 text-xs text-ink-soft">{{ a }}</span></div>
                <p class="mt-6 text-sm leading-relaxed whitespace-pre-line text-ink-soft">{{ opportunity.description }}</p>
            </CardPanel>
            <aside>
                <CardPanel v-if="isPoster" :title="`Interested (${interests.length})`">
                    <p v-if="interests.length === 0" class="text-sm text-muted">No responses yet.</p>
                    <ul v-else class="space-y-4 text-sm">
                        <li v-for="(i, n) in interests" :key="n">
                            <p class="font-medium">{{ i.name }} <span class="font-normal text-subtle">· {{ i.at }}</span></p>
                            <a :href="`mailto:${i.email}`" class="text-brand-700">{{ i.email }}</a>
                            <p class="mt-1 whitespace-pre-line text-ink-soft">{{ i.note }}</p>
                        </li>
                    </ul>
                </CardPanel>
                <CardPanel v-else-if="opportunity.is_open" title="I’m interested">
                    <form class="space-y-3" @submit.prevent="submit">
                        <FormField label="How you can contribute" :error="form.errors.note" hint="Your email is shared with the poster." required><TextArea v-model="form.note" rows="5" maxlength="2000" /></FormField>
                        <AppButton type="submit" class="w-full" :loading="form.processing">{{ myInterest ? 'Update' : 'Send' }}</AppButton>
                    </form>
                </CardPanel>
            </aside>
        </div>
    </AppLayout>
</template>
