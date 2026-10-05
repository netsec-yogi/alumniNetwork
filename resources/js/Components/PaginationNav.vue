<script setup lang="ts">
import type { PaginationLink } from '@/types';
import { Link } from '@inertiajs/vue3';

defineProps<{ links: PaginationLink[]; from: number | null; to: number | null; total: number }>();

const label = (l: string) => l.replace('&laquo;', '‹').replace('&raquo;', '›');
</script>

<template>
    <nav v-if="total > 0" class="flex flex-wrap items-center justify-between gap-3 text-sm" aria-label="Pagination">
        <p class="text-slate-600">Showing {{ from }}–{{ to }} of {{ total }}</p>
        <div v-if="links.length > 3" class="flex flex-wrap gap-1">
            <template v-for="(link, i) in links" :key="i">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    preserve-scroll
                    :aria-current="link.active ? 'page' : undefined"
                    :class="[
                        'min-w-9 rounded-md px-2.5 py-1.5 text-center ring-1 ring-inset',
                        link.active ? 'bg-brand-800 text-white ring-brand-800' : 'bg-white text-slate-700 ring-slate-300 hover:bg-slate-50',
                    ]"
                    >{{ label(link.label) }}</Link
                >
                <span v-else class="min-w-9 rounded-md px-2.5 py-1.5 text-center text-slate-400">{{ label(link.label) }}</span>
            </template>
        </div>
    </nav>
</template>
