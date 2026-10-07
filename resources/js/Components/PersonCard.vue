<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AvatarImage from './AvatarImage.vue';

defineProps<{ name: string; subtitle?: string | null; profileId?: number | null; photoUrl?: string | null }>();

</script>

<template>
    <div class="flex items-start gap-3">
        <AvatarImage :name="name" :src="photoUrl" />
        <div class="min-w-0 flex-1">
            <Link v-if="profileId" :href="route('alumni.show', profileId)" class="block truncate font-medium text-ink hover:underline">{{ name }}</Link>
            <p v-else class="truncate font-medium text-ink">{{ name }}</p>
            <p v-if="subtitle" class="truncate text-sm text-muted">{{ subtitle }}</p>
            <slot />
        </div>
        <div v-if="$slots.actions" class="flex shrink-0 flex-wrap justify-end gap-2"><slot name="actions" /></div>
    </div>
</template>
