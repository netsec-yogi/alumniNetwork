<script setup lang="ts">
import { ask } from '@/lib/confirm';
import AppButton from '@/Components/AppButton.vue';
import CardPanel from '@/Components/CardPanel.vue';
import CheckboxInput from '@/Components/CheckboxInput.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FormField from '@/Components/FormField.vue';
import ModalDialog from '@/Components/ModalDialog.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PaginationNav from '@/Components/PaginationNav.vue';
import SelectInput from '@/Components/SelectInput.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextArea from '@/Components/TextArea.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Option, Paginated } from '@/types';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Opp {
    id: number;
    title: string;
    category: string;
    description: string;
    location: string | null;
    dates: string | null;
    slots: number | null;
    taken: number | null;
    hours_estimate: number | null;
    group: string | null;
    can_manage: boolean;
    my_status: string | null;
}

defineProps<{
    opportunities: Paginated<Opp>;
    mySignups: { id: number; title: string; status: string; hours: number | null; can_log: boolean; date: string | null }[];
    totalHours: number;
    category: string | null;
    categories: Option[];
    canOrganise: boolean;
    groups: Option<number>[];
}>();

const opts = { preserveScroll: true };
const signUp = (o: Opp) => router.post(route('volunteering.sign-up', o.id), {}, opts);
const withdraw = (id: number) => ask('Withdraw from this opportunity?').then((ok) => ok && router.post(route('volunteering.withdraw', id), {}, opts));

const logging = ref<number | null>(null);
const hoursForm = useForm({ hours: 2, outcome: '' });
const submitHours = () => hoursForm.post(route('volunteering.hours', logging.value!), { ...opts, onSuccess: () => ((logging.value = null), hoursForm.reset()) });

const creating = ref(false);
const form = useForm({ category: '', title: '', description: '', location: '', is_remote: false, starts_on: '', ends_on: '', slots: '' as number | '', hours_estimate: '' as number | '', community_id: '' as number | '' });
const create = () =>
    form.transform((d) => Object.fromEntries(Object.entries(d).map(([k, v]) => [k, v === '' ? null : v]))).post(route('volunteering.store'), { ...opts, onSuccess: () => ((creating.value = false), form.reset()) });
const badge = (s: string) => ({ completed: 'verified', hours_submitted: 'pending', rejected: 'rejected' })[s] ?? 'active';
</script>

<template>
    <AppLayout title="Volunteering">
        <PageHeader title="Volunteering" description="Give time to students, chapters, admissions and events.">
            <AppButton v-if="canOrganise" @click="creating = true">Post an opportunity</AppButton>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
            <section class="space-y-4">
                <nav class="flex flex-wrap gap-2" aria-label="Category">
                    <Link :href="route('volunteering.index')" :class="['rounded-md px-3 py-1.5 text-[13px] font-medium whitespace-nowrap transition-colors', !category ? 'bg-brand-600 text-white shadow-sm' : 'bg-surface text-ink-soft ring-1 ring-line-strong']">All</Link>
                    <Link
                        v-for="c in categories"
                        :key="c.value"
                        :href="route('volunteering.index', { category: c.value })"
                        :class="['rounded-md px-3 py-1.5 text-[13px] font-medium whitespace-nowrap transition-colors', category === c.value ? 'bg-brand-600 text-white shadow-sm' : 'bg-surface text-ink-soft ring-1 ring-line-strong']"
                        >{{ c.label }}</Link
                    >
                </nav>
                <EmptyState v-if="opportunities.data.length === 0" title="No open opportunities right now" />
                <article v-for="o in opportunities.data" :key="o.id" class="card p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xs font-medium tracking-wide text-accent-600 uppercase">{{ o.category }}<template v-if="o.group"> · {{ o.group }}</template></p>
                            <h2 class="mt-1 font-semibold text-ink">{{ o.title }}</h2>
                            <p class="text-sm text-muted">{{ [o.dates, o.location, o.hours_estimate ? `~${o.hours_estimate} h` : null].filter(Boolean).join(' · ') }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span v-if="o.slots" class="text-xs text-muted">{{ o.taken }}/{{ o.slots }} filled</span>
                            <AppButton v-if="o.can_manage" size="sm" variant="ghost" :href="route('volunteering.manage', o.id)">Manage</AppButton>
                            <StatusBadge v-if="o.my_status && o.my_status !== 'withdrawn'" :status="badge(o.my_status)" :label="o.my_status.replace('_', ' ')" />
                            <AppButton v-else size="sm" :disabled="!!o.slots && (o.taken ?? 0) >= o.slots" @click="signUp(o)">Volunteer</AppButton>
                        </div>
                    </div>
                    <p class="mt-3 text-sm whitespace-pre-line text-ink-soft">{{ o.description }}</p>
                </article>
                <PaginationNav :links="opportunities.links" :from="opportunities.from" :to="opportunities.to" :total="opportunities.total" />
            </section>

            <aside class="space-y-4">
                <CardPanel title="My volunteering" :description="`${totalHours} approved hours`">
                    <p v-if="mySignups.length === 0" class="text-sm text-muted">Nothing yet.</p>
                    <ul v-else class="space-y-3 text-sm">
                        <li v-for="s in mySignups" :key="s.id">
                            <div class="flex items-start justify-between gap-2"><span class="font-medium">{{ s.title }}</span><StatusBadge :status="badge(s.status)" :label="s.status.replace('_', ' ')" /></div>
                            <p v-if="s.hours" class="text-muted">{{ s.hours }} h</p>
                            <div v-if="s.can_log" class="mt-1 flex gap-3 text-xs">
                                <button type="button" class="font-medium text-brand-700" @click="logging = s.id">Log hours</button>
                                <button type="button" class="text-muted hover:text-red-700" @click="withdraw(s.id)">Withdraw</button>
                            </div>
                        </li>
                    </ul>
                </CardPanel>
            </aside>
        </div>

        <ModalDialog :show="logging !== null" title="Log your volunteer hours" @close="logging = null">
            <form id="hours-form" class="space-y-4" @submit.prevent="submitHours">
                <FormField label="Hours" :error="hoursForm.errors.hours" required><TextInput v-model.number="hoursForm.hours" type="number" step="0.5" min="0.5" /></FormField>
                <FormField label="What you did and what came of it" :error="hoursForm.errors.outcome" required><TextArea v-model="hoursForm.outcome" rows="4" /></FormField>
            </form>
            <template #footer>
                <AppButton variant="secondary" @click="logging = null">Cancel</AppButton>
                <AppButton type="submit" form="hours-form" :loading="hoursForm.processing">Submit for approval</AppButton>
            </template>
        </ModalDialog>

        <ModalDialog :show="creating" title="Post a volunteering opportunity" @close="creating = false">
            <form id="volunteer-form" class="grid gap-4 sm:grid-cols-2" @submit.prevent="create">
                <FormField label="Category" :error="form.errors.category" required><SelectInput v-model="form.category" :options="categories" placeholder="Choose" /></FormField>
                <FormField label="Group" :error="form.errors.community_id"><SelectInput v-model="form.community_id" :options="groups" placeholder="Institute-wide" /></FormField>
                <div class="sm:col-span-2"><FormField label="Title" :error="form.errors.title" required><TextInput v-model="form.title" maxlength="200" /></FormField></div>
                <div class="sm:col-span-2"><FormField label="Description" :error="form.errors.description" required><TextArea v-model="form.description" rows="4" /></FormField></div>
                <FormField label="Starts" :error="form.errors.starts_on"><TextInput v-model="form.starts_on" type="date" /></FormField>
                <FormField label="Ends" :error="form.errors.ends_on"><TextInput v-model="form.ends_on" type="date" /></FormField>
                <FormField label="Location" :error="form.errors.location"><TextInput v-model="form.location" /></FormField>
                <div class="flex items-end pb-2"><CheckboxInput v-model="form.is_remote" label="Remote" /></div>
                <FormField label="Slots" :error="form.errors.slots"><TextInput v-model.number="form.slots" type="number" min="1" /></FormField>
                <FormField label="Estimated hours" :error="form.errors.hours_estimate"><TextInput v-model.number="form.hours_estimate" type="number" step="0.5" /></FormField>
            </form>
            <template #footer>
                <AppButton variant="secondary" @click="creating = false">Cancel</AppButton>
                <AppButton type="submit" form="volunteer-form" :loading="form.processing">Post</AppButton>
            </template>
        </ModalDialog>
    </AppLayout>
</template>
