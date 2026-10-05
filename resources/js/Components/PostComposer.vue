<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppButton from './AppButton.vue';
import TextInput from './TextInput.vue';

const props = defineProps<{ communities?: { id: number; name: string }[]; communityId?: number; canAnnounce?: boolean; placeholder?: string }>();

const form = useForm({ body: '', link_url: '', community_id: props.communityId ?? ('' as number | ''), kind: 'post' });
const showLink = ref(false);

function submit() {
    form.transform((d) => ({ ...d, link_url: d.link_url || null, community_id: d.community_id || null }));
    form.post(route('posts.store'), { onSuccess: () => (form.reset(), (showLink.value = false)) });
}
</script>

<template>
    <form class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200" @submit.prevent="submit">
        <label for="composer" class="sr-only">Write a post</label>
        <textarea
            id="composer"
            v-model="form.body"
            rows="3"
            maxlength="5000"
            :placeholder="placeholder ?? 'Share news, an achievement or a question with the network…'"
            class="block w-full resize-y rounded-lg border-0 p-2 text-sm ring-1 ring-slate-200 ring-inset placeholder:text-slate-400 focus:ring-2 focus:ring-brand-600"
        />
        <p v-if="form.errors.body" class="mt-1 text-sm text-red-600">{{ form.errors.body }}</p>
        <div v-if="showLink" class="mt-2">
            <TextInput v-model="form.link_url" type="url" placeholder="https://" aria-label="Link" />
            <p v-if="form.errors.link_url" class="mt-1 text-sm text-red-600">{{ form.errors.link_url }}</p>
        </div>
        <div class="mt-3 flex flex-wrap items-center gap-2">
            <button type="button" class="rounded-md px-2 py-1 text-sm text-slate-600 hover:bg-slate-50" @click="showLink = !showLink">🔗 Link</button>
            <select v-if="communities?.length && !communityId" v-model="form.community_id" class="rounded-md border-0 py-1 pr-8 text-sm ring-1 ring-slate-200" aria-label="Post to">
                <option value="">Everyone</option>
                <option v-for="c in communities" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
            <select v-model="form.kind" class="rounded-md border-0 py-1 pr-8 text-sm ring-1 ring-slate-200" aria-label="Post type">
                <option value="post">Post</option>
                <option value="achievement">Achievement</option>
                <option v-if="canAnnounce" value="announcement">Announcement</option>
            </select>
            <AppButton type="submit" size="sm" class="ml-auto" :loading="form.processing" :disabled="!form.body.trim()">Post</AppButton>
        </div>
    </form>
</template>
