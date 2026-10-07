<script setup lang="ts">
import AppButton from '@/Components/AppButton.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FormField from '@/Components/FormField.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PaginationNav from '@/Components/PaginationNav.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextInput from '@/Components/TextInput.vue';
import AvatarImage from '@/Components/AvatarImage.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Paginated } from '@/types';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

interface Card {
    id: number;
    name: string;
    programme: string;
    department: string | null;
    graduation_year: number;
    interests: string[];
    company?: string | null;
    designation?: string | null;
    location?: string | null;
    photo_url: string | null;
}

const props = defineProps<{
    profiles: Paginated<Card>;
    filters: Partial<Record<'q' | 'company' | 'location' | 'interest', string>> & { programme?: number; year?: number };
    programmes: { id: number; name: string }[];
    interestOptions: Record<string, string>;
}>();

const form = reactive({
    q: props.filters.q ?? '',
    programme: props.filters.programme ?? ('' as number | ''),
    year: props.filters.year ?? ('' as number | ''),
    company: props.filters.company ?? '',
    location: props.filters.location ?? '',
    interest: props.filters.interest ?? '',
});

const programmeOptions = computed(() => props.programmes.map((p) => ({ value: p.id, label: p.name })));
const interestSelect = computed(() => Object.entries(props.interestOptions).map(([value, label]) => ({ value, label })));
const hasFilters = computed(() => Object.values(props.filters).some((v) => v !== null && v !== '' && v !== undefined));

function search() {
    const query = Object.fromEntries(Object.entries(form).filter(([, v]) => v !== '' && v !== null));
    router.get(route('directory'), query, { preserveState: true, preserveScroll: true });
}

const aiEnabled = computed(() => usePage().props.features.ai);
const smart = useForm({ query: '' });
const smartSearch = () => smart.post(route('directory.ai-search'), { preserveScroll: true });

function clear() {
    router.get(route('directory'));
}

</script>

<template>
    <AppLayout title="Alumni directory">
        <PageHeader title="Alumni directory" :description="`${profiles.total.toLocaleString('en-IN')} verified alumni${hasFilters ? ' match your search' : ''}`" />

        <form v-if="aiEnabled" class="card mb-6 flex flex-col gap-2 p-4 sm:flex-row sm:items-start" role="search" @submit.prevent="smartSearch">
            <FormField label="Describe who you’re looking for" class="flex-1" :error="smart.errors.query" hint="We turn this into the filters below; results follow everyone’s privacy settings.">
                <TextInput v-model="smart.query" type="search" maxlength="300" placeholder="e.g. 2015 B.Tech alumni working at Google in Bengaluru" />
            </FormField>
            <AppButton type="submit" class="sm:mt-6" :loading="smart.processing">Smart search</AppButton>
        </form>

        <div class="grid gap-6 lg:grid-cols-[18rem_1fr]">
            <aside>
                <form class="card space-y-4 p-5" role="search" @submit.prevent="search">
                    <FormField label="Name">
                        <TextInput v-model="form.q" type="search" placeholder="Search by name" />
                    </FormField>
                    <FormField label="Programme">
                        <SelectInput v-model="form.programme" :options="programmeOptions" placeholder="Any programme" />
                    </FormField>
                    <FormField label="Graduation year">
                        <TextInput v-model.number="form.year" type="number" inputmode="numeric" placeholder="e.g. 2015" />
                    </FormField>
                    <FormField label="Company">
                        <TextInput v-model="form.company" />
                    </FormField>
                    <FormField label="City or country">
                        <TextInput v-model="form.location" />
                    </FormField>
                    <FormField label="Open to">
                        <SelectInput v-model="form.interest" :options="interestSelect" placeholder="Anything" />
                    </FormField>
                    <div class="flex gap-2">
                        <AppButton type="submit" class="flex-1">Search</AppButton>
                        <AppButton v-if="hasFilters" variant="ghost" @click="clear">Clear</AppButton>
                    </div>
                </form>
            </aside>

            <section aria-label="Results" class="space-y-6">
                <EmptyState v-if="profiles.data.length === 0" title="No alumni found" description="Try fewer filters, or a different spelling." />

                <ul v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <li v-for="p in profiles.data" :key="p.id">
                        <Link :href="route('alumni.show', p.id)" class="card flex h-full gap-4 p-5 card-hover">
                            <AvatarImage :name="p.name" :src="p.photo_url" size="lg" />
                            <span class="min-w-0">
                                <span class="block truncate font-medium text-ink">{{ p.name }}</span>
                                <span class="block text-sm text-muted">{{ p.programme }} · {{ p.graduation_year }}</span>
                                <span v-if="p.designation || p.company" class="mt-1 block truncate text-sm text-muted">
                                    {{ [p.designation, p.company].filter(Boolean).join(' at ') }}
                                </span>
                                <span v-if="p.location" class="block truncate text-sm text-muted">{{ p.location }}</span>
                                <span v-if="p.interests.length" class="mt-2 flex flex-wrap gap-1">
                                    <span v-for="i in p.interests.slice(0, 3)" :key="i" class="rounded-full bg-accent-400/15 px-2 py-0.5 text-xs text-amber-800">{{
                                        interestOptions[i]
                                    }}</span>
                                </span>
                            </span>
                        </Link>
                    </li>
                </ul>

                <PaginationNav :links="profiles.links" :from="profiles.from" :to="profiles.to" :total="profiles.total" />
            </section>
        </div>
    </AppLayout>
</template>
