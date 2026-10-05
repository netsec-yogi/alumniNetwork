<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import AppLayout from './AppLayout.vue';
import PublicLayout from './PublicLayout.vue';

/** Pages open to guests: the app shell when signed in, the public one otherwise. */
defineProps<{ title: string }>();
const page = usePage();
</script>

<template>
    <AppLayout v-if="page.props.auth.user" :title="title"><slot /></AppLayout>
    <PublicLayout v-else :title="title"><slot /></PublicLayout>
</template>
