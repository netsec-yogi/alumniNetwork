<script setup lang="ts">
import { computed } from 'vue';

/**
 * Plain text with http(s) links made clickable. Built from text nodes and
 * <a> elements, never v-html, so member-written text can't inject markup.
 */
const props = defineProps<{ text: string }>();

const parts = computed(() =>
    props.text.split(/(https?:\/\/[^\s<>"']+)/g).map((chunk) => {
        if (!/^https?:\/\//.test(chunk)) return { text: chunk };
        const trimmed = chunk.replace(/[.,;:!?)\]]+$/, '');
        return { link: trimmed, rest: chunk.slice(trimmed.length) };
    }),
);
</script>

<template>
    <span class="break-words whitespace-pre-line">
        <template v-for="(p, i) in parts" :key="i">
            <template v-if="p.link"
                ><a :href="p.link" target="_blank" rel="noopener noreferrer nofollow ugc" class="text-brand-700 hover:underline">{{ p.link }}</a>{{ p.rest }}</template
            >
            <template v-else>{{ p.text }}</template>
        </template>
    </span>
</template>
