<script setup lang="ts">
import { Images, Star } from 'lucide-vue-next';
import { ref } from 'vue';
import LightboxViewer from './LightboxViewer.vue';

/** Responsive photo grid (featured image first and larger) with a lightbox: keyboard, swipe, previous/next. */
defineProps<{ items: { thumb: string; full: string; title: string; featured?: boolean }[]; title?: string }>();
const open = ref<number | null>(null);
</script>

<template>
    <section v-if="items.length" aria-label="Photo gallery">
        <h2 class="mb-3 flex items-center gap-2 text-lg font-bold text-ink"><Images :size="19" class="text-brand-600" aria-hidden="true" />{{ title ?? 'Gallery' }} <span class="text-sm font-medium text-muted">· {{ items.length }}</span></h2>
        <ul class="grid grid-cols-2 gap-2 sm:grid-cols-3 sm:gap-3 lg:grid-cols-4">
            <li v-for="(img, i) in items" :key="img.full" :class="i === 0 && items.length > 2 ? 'col-span-2 row-span-2' : ''">
                <button type="button" class="group relative block size-full overflow-hidden rounded-2xl bg-surface-sunken" :aria-label="`Open photo ${i + 1} of ${items.length}: ${img.title}`" @click="open = i">
                    <img :src="i === 0 && items.length > 2 ? img.full : img.thumb" :alt="img.title" loading="lazy" class="aspect-square size-full object-cover transition duration-500 group-hover:scale-105" />
                    <span v-if="img.featured" class="absolute top-2 left-2 inline-flex items-center gap-1 rounded-full bg-black/45 px-2 py-0.5 text-[11px] font-bold text-white backdrop-blur"><Star :size="11" fill="currentColor" aria-hidden="true" />Featured</span>
                </button>
            </li>
        </ul>
        <LightboxViewer v-model="open" :items="items" />
    </section>
</template>
