<script setup lang="ts">
import type { Branding } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * The portal logo, from the published branding (Admin → Branding). `place`
 * picks the logo for that spot; in headers, phones get the mobile logo.
 * With no custom logo, the built-in mark shows the portal's initials.
 * `inverse` is for dark backgrounds; `branding` overrides the shared one
 * (used by the admin preview).
 */
const props = withDefaults(defineProps<{ compact?: boolean; inverse?: boolean; place?: 'header' | 'footer' | 'login'; branding?: Branding }>(), { place: 'header' });

const fallback: Branding = { name: 'Alumni Connect', tagline: 'ABV-IIITM Gwalior', show_name: true, logos: { header: null, mobile: null, footer: null, login: null }, favicon: null };
const page = usePage();
const b = computed<Branding>(() => props.branding ?? (page.props.branding as Branding | undefined) ?? fallback);

const src = computed(() => b.value.logos[props.place]);
// Phones use the mobile logo in headers, when it differs.
const mobileSrc = computed(() => (props.place === 'header' && b.value.logos.mobile !== src.value ? b.value.logos.mobile : null));
const initials = computed(
    () =>
        b.value.name
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map((w) => w[0]!.toUpperCase())
            .join('') || 'AC',
);
const showText = computed(() => !props.compact && (b.value.show_name || !src.value));
const imgClass = computed(() => ['h-9 w-auto max-w-40 object-contain', props.inverse && 'rounded-lg bg-white/95 p-1']);
</script>

<template>
    <span class="inline-flex items-center gap-2.5">
        <template v-if="src">
            <img v-if="mobileSrc" :src="mobileSrc" :alt="showText ? '' : b.name" :class="[imgClass, 'sm:hidden']" />
            <img :src="src" :alt="showText ? '' : b.name" :class="[imgClass, mobileSrc && 'hidden sm:block']" />
        </template>
        <span
            v-else
            :class="[
                'grid size-9 shrink-0 place-items-center rounded-lg text-[13px] font-bold tracking-tight shadow-sm',
                inverse ? 'bg-white text-brand-600' : 'bg-gradient-to-br from-brand-600 to-deep-800 text-white',
            ]"
            aria-hidden="true"
            >{{ initials }}</span
        >
        <span v-if="showText" class="leading-tight">
            <span :class="['block text-[15px] font-semibold', inverse ? 'text-white' : 'text-ink']">{{ b.name }}</span>
            <span v-if="b.tagline" :class="['block text-xs', inverse ? 'text-white/60' : 'text-muted']">{{ b.tagline }}</span>
        </span>
        <span v-else-if="!src" class="sr-only">{{ b.name }}</span>
    </span>
</template>
