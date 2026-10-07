<script setup lang="ts">
import AvatarImage from '@/Components/AvatarImage.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PaginationNav from '@/Components/PaginationNav.vue';
import AppButton from '@/Components/AppButton.vue';
import SiteLayout from '@/Layouts/SiteLayout.vue';
import type { Option, Paginated } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';

interface Item {
    id: number;
    title: string;
    category: string;
    description: string | null;
    date: string | null;
    link_url: string | null;
    image_url: string | null;
    person: { name: string; batch: string; photo_url: string | null; profile_id: number };
}

defineProps<{ achievements: Paginated<Item>; category: string | null; categories: Option[] }>();
const user = usePage().props.auth.user;
</script>

<template>
    <SiteLayout title="Achievements">
        <PageHeader title="Alumni achievements" description="Awards, promotions, publications, patents and milestones from the IIITM community.">
            <AppButton v-if="user?.verification_status === 'verified'" :href="route('achievements.mine')">Share an achievement</AppButton>
        </PageHeader>

        <nav class="mb-6 flex flex-wrap gap-2" aria-label="Category">
            <Link :href="route('achievements.index')" :class="['rounded-md px-3 py-1.5 text-[13px] font-medium whitespace-nowrap transition-colors', !category ? 'bg-brand-600 text-white shadow-sm' : 'bg-surface text-ink-soft ring-1 ring-line-strong']">All</Link>
            <Link
                v-for="c in categories"
                :key="c.value"
                :href="route('achievements.index', { category: c.value })"
                :class="['rounded-md px-3 py-1.5 text-[13px] font-medium whitespace-nowrap transition-colors', category === c.value ? 'bg-brand-600 text-white shadow-sm' : 'bg-surface text-ink-soft ring-1 ring-line-strong']"
                >{{ c.label }}</Link
            >
        </nav>

        <EmptyState v-if="achievements.data.length === 0" title="No achievements published yet" />
        <ul v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <li v-for="a in achievements.data" :key="a.id" class="card flex flex-col overflow-hidden">
                <img v-if="a.image_url" :src="a.image_url" alt="" class="h-40 w-full object-cover" loading="lazy" />
                <div class="flex flex-1 flex-col p-5">
                    <p class="text-xs font-medium tracking-wide text-accent-600 uppercase">{{ a.category }}<span v-if="a.date"> · {{ a.date }}</span></p>
                    <h2 class="mt-1 font-semibold text-ink">{{ a.title }}</h2>
                    <p v-if="a.description" class="mt-2 line-clamp-4 text-sm text-muted">{{ a.description }}</p>
                    <a v-if="a.link_url" :href="a.link_url" target="_blank" rel="noopener noreferrer nofollow" class="mt-2 text-sm text-brand-700 hover:underline">Read more ↗</a>
                    <div class="mt-auto flex items-center gap-2 pt-4">
                        <AvatarImage :name="a.person.name" :src="a.person.photo_url" size="sm" />
                        <span class="text-sm"><span class="font-medium text-ink">{{ a.person.name }}</span> <span class="text-muted">· {{ a.person.batch }}</span></span>
                    </div>
                </div>
            </li>
        </ul>
        <PaginationNav class="mt-6" :links="achievements.links" :from="achievements.from" :to="achievements.to" :total="achievements.total" />
    </SiteLayout>
</template>
