<script setup lang="ts">
import AppButton from '@/Components/AppButton.vue';
import AvatarImage from '@/Components/AvatarImage.vue';
import CheckboxInput from '@/Components/CheckboxInput.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PaginationNav from '@/Components/PaginationNav.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Option, Paginated } from '@/types';
import { Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

interface Card {
    slug: string;
    name: string;
    tagline: string | null;
    industry: string;
    location: string | null;
    stage: string;
    is_hiring: boolean;
    logo_url: string | null;
    founder_names: string;
}

const props = defineProps<{ startups: Paginated<Card>; filters: { q?: string; stage?: string; hiring?: boolean | string }; stages: Option[]; canCreate: boolean }>();
const f = reactive({ q: props.filters.q ?? '', stage: props.filters.stage ?? '', hiring: props.filters.hiring === true || props.filters.hiring === '1' });
const search = () => router.get(route('startups.index'), Object.fromEntries(Object.entries({ ...f, hiring: f.hiring ? 1 : '' }).filter(([, v]) => v)) as Record<string, string>, { preserveState: true });
</script>

<template>
    <AppLayout title="Startups">
        <PageHeader title="Alumni startups" description="Companies founded by IIITM graduates — many are hiring.">
            <AppButton v-if="canCreate" :href="route('startups.create')">Add your startup</AppButton>
        </PageHeader>
        <form class="mb-6 flex flex-wrap items-center gap-3" role="search" @submit.prevent="search">
            <TextInput v-model="f.q" type="search" placeholder="Name or industry" aria-label="Search" class="w-64" />
            <SelectInput v-model="f.stage" :options="stages" placeholder="Any stage" aria-label="Stage" class="w-44" />
            <CheckboxInput v-model="f.hiring" label="Hiring now" />
            <AppButton type="submit" variant="secondary">Search</AppButton>
        </form>
        <EmptyState v-if="startups.data.length === 0" title="No startups listed yet" />
        <ul v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <li v-for="s in startups.data" :key="s.slug">
                <Link :href="route('startups.show', s.slug)" class="card flex h-full gap-4 p-5 card-hover">
                    <img v-if="s.logo_url" :src="s.logo_url" alt="" class="size-14 shrink-0 rounded-lg object-cover ring-1 ring-line" />
                    <AvatarImage v-else :name="s.name" size="lg" />
                    <span class="min-w-0">
                        <span class="block font-semibold text-ink">{{ s.name }}</span>
                        <span v-if="s.tagline" class="block text-sm text-muted">{{ s.tagline }}</span>
                        <span class="mt-1 block text-xs text-muted">{{ s.industry }} · {{ s.stage }}<template v-if="s.location"> · {{ s.location }}</template></span>
                        <span class="mt-1 block truncate text-xs text-subtle">by {{ s.founder_names }}</span>
                        <span v-if="s.is_hiring" class="mt-2 inline-block rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Hiring</span>
                    </span>
                </Link>
            </li>
        </ul>
        <PaginationNav class="mt-6" :links="startups.links" :from="startups.from" :to="startups.to" :total="startups.total" />
    </AppLayout>
</template>
