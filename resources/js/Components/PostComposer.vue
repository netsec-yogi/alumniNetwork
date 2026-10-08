<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { Globe, Link2, Megaphone, PenLine, Trophy, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AvatarImage from './AvatarImage.vue';
import AppButton from './AppButton.vue';
import TextInput from './TextInput.vue';

const props = defineProps<{ communities?: { id: number; name: string }[]; communityId?: number; canAnnounce?: boolean; placeholder?: string }>();

const form = useForm({ body: '', link_url: '', community_id: props.communityId ?? ('' as number | ''), kind: 'post' });
const showLink = ref(false);

const me = computed(() => usePage().props.auth.user!);
const kinds = computed(() => [
    { value: 'post', label: 'Post', icon: PenLine },
    { value: 'achievement', label: 'Achievement', icon: Trophy },
    ...(props.canAnnounce ? [{ value: 'announcement', label: 'Announcement', icon: Megaphone }] : []),
]);
const LIMIT = 5000;
const textarea = ref<HTMLTextAreaElement>();
// Grow with the text, up to a comfortable height.
function grow() {
    const el = textarea.value;
    if (!el) return;
    el.style.height = 'auto';
    el.style.height = `${Math.min(el.scrollHeight, 320)}px`;
}

function submit() {
    form.transform((d) => ({ ...d, link_url: d.link_url || null, community_id: d.community_id || null }));
    form.post(route('posts.store'), { preserveScroll: true, onSuccess: () => (form.reset(), (showLink.value = false), grow()) });
}
</script>

<template>
    <form class="card p-4 sm:p-5" @submit.prevent="submit">
        <div class="flex items-start gap-3">
            <AvatarImage :name="me.name" class="hidden sm:grid" />
            <div class="min-w-0 flex-1">
                <label for="composer" class="sr-only">Write a post</label>
                <textarea
                    id="composer"
                    ref="textarea"
                    v-model="form.body"
                    rows="2"
                    :maxlength="LIMIT"
                    :placeholder="placeholder ?? `What’s new, ${me.name.split(' ')[0]}? Share news, a win or a question…`"
                    class="block w-full resize-none border-0 !bg-transparent p-1 text-[15px] leading-relaxed text-ink placeholder:text-subtle focus:ring-0"
                    @input="grow"
                />
                <p v-if="form.errors.body" class="mt-1 text-sm text-red-600" role="alert">{{ form.errors.body }}</p>
                <div v-if="showLink" class="mt-2">
                    <TextInput v-model="form.link_url" type="url" placeholder="Paste a link — https://…" aria-label="Link" :icon="Link2" />
                    <p v-if="form.errors.link_url" class="mt-1 text-sm text-red-600" role="alert">{{ form.errors.link_url }}</p>
                </div>
            </div>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-line-soft pt-3">
            <div class="flex flex-wrap gap-1.5" role="radiogroup" aria-label="Post type">
                <button
                    v-for="k in kinds"
                    :key="k.value"
                    type="button"
                    role="radio"
                    :aria-checked="form.kind === k.value"
                    :class="['press inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-[13px] font-semibold transition', form.kind === k.value ? 'bg-brand-600 text-white' : 'bg-surface-sunken text-ink-soft hover:bg-line']"
                    @click="form.kind = k.value"
                >
                    <component :is="k.icon" :size="14" aria-hidden="true" />{{ k.label }}
                </button>
            </div>
            <button
                type="button"
                :class="['press inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-[13px] font-semibold', showLink ? 'bg-brand-50 text-brand-700' : 'text-muted hover:bg-surface-sunken']"
                :aria-pressed="showLink"
                @click="showLink = !showLink"
            >
                <Link2 :size="14" aria-hidden="true" />Link
            </button>
            <label v-if="communities?.length && !communityId" class="relative inline-flex items-center">
                <span class="sr-only">Post to</span>
                <component :is="form.community_id ? Users : Globe" :size="14" class="pointer-events-none absolute left-3 text-muted" aria-hidden="true" />
                <select v-model="form.community_id" class="h-8 rounded-full border-0 !bg-surface-sunken py-0 pr-8 pl-8 text-[13px] font-semibold text-ink-soft focus:ring-2 focus:ring-brand-500">
                    <option value="">Everyone</option>
                    <option v-for="c in communities" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
            </label>
            <span class="ml-auto flex items-center gap-3">
                <span v-if="form.body.length > LIMIT * 0.8" :class="['text-xs tabular-nums', form.body.length >= LIMIT ? 'text-red-600' : 'text-muted']">{{ LIMIT - form.body.length }}</span>
                <AppButton type="submit" :loading="form.processing" :disabled="!form.body.trim()">Post</AppButton>
            </span>
        </div>
    </form>
</template>
