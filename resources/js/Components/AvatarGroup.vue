<script setup lang="ts">
import AvatarImage from './AvatarImage.vue';

/** Overlapping avatars ("Priya, Arjun and 12 others"). The label carries the meaning for screen readers. */
withDefaults(defineProps<{ people: { name: string; photo_url?: string | null }[]; max?: number; total?: number; size?: 'xs' | 'sm' }>(), { max: 4, size: 'sm' });
</script>

<template>
    <span class="inline-flex items-center">
        <span class="flex -space-x-2" aria-hidden="true">
            <AvatarImage v-for="p in people.slice(0, max)" :key="p.name" :name="p.name" :src="p.photo_url" :size="size" class="ring-2 ring-surface" />
            <span
                v-if="(total ?? people.length) > max"
                :class="['grid shrink-0 place-items-center rounded-full bg-surface-sunken font-semibold text-muted ring-2 ring-surface', size === 'xs' ? 'size-6 text-[9px]' : 'size-8 text-[11px]']"
                >+{{ (total ?? people.length) - max }}</span
            >
        </span>
        <span class="sr-only">{{ people.slice(0, 3).map((p) => p.name).join(', ') }}{{ (total ?? people.length) > 3 ? ` and ${(total ?? people.length) - 3} others` : '' }}</span>
    </span>
</template>
