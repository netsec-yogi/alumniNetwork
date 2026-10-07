<script setup lang="ts">
import { Bookmark, Heart, Link2, MessageCircle } from 'lucide-vue-next';
import { ask } from '@/lib/confirm';
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AvatarImage from './AvatarImage.vue';
import LinkifiedText from './LinkifiedText.vue';
import ReportButton from './ReportButton.vue';

export interface FeedPost {
    id: number;
    kind: string;
    body: string;
    link_url: string | null;
    author: { name: string; subtitle: string | null; profile_id: number | null; photo_url: string | null };
    community: { name: string; slug: string } | null;
    shared: { kind: string; title: string; subtitle: string; url: string } | null;
    pinned: boolean;
    likes: number;
    comments: number;
    liked: boolean;
    saved: boolean;
    at: string;
    can: { delete: boolean; pin: boolean; interact: boolean };
}

const props = defineProps<{ post: FeedPost; reportReasons: Record<string, string>; full?: boolean }>();

// Optimistic toggles: update now, send in the background, and reload only
// flash messages so already-loaded feed pages are kept.
const liked = ref(props.post.liked);
const likes = ref(props.post.likes);
const saved = ref(props.post.saved);
const removed = ref(false);
const expanded = ref(props.full ?? false);
const quiet = { preserveScroll: true, preserveState: true, only: ['flash'] };

function toggleLike() {
    liked.value = !liked.value;
    likes.value += liked.value ? 1 : -1;
    router.post(route('posts.like', props.post.id), {}, { ...quiet, onError: () => ((liked.value = !liked.value), (likes.value += liked.value ? 1 : -1)) });
}
function toggleSave() {
    saved.value = !saved.value;
    router.post(route('posts.save', props.post.id), {}, quiet);
}
async function remove() {
    if (!(await ask('Delete this post? It is removed for everyone, including comments.'))) return;
    router.delete(route('posts.destroy', props.post.id), { ...quiet, onSuccess: () => (removed.value = true) });
}
const pin = () => router.post(route('posts.pin', props.post.id), {}, { preserveScroll: true });

const long = computed(() => props.post.body.length > 600);
const body = computed(() => (long.value && !expanded.value ? props.post.body.slice(0, 600) + '…' : props.post.body));
</script>

<template>
    <article v-if="!removed" :class="['rounded-xl bg-surface p-5 shadow-sm ring-1', post.kind === 'announcement' ? 'ring-accent-400' : 'ring-line']">
        <header class="flex items-start gap-3">
            <AvatarImage :name="post.author.name" :src="post.author.photo_url" />
            <div class="min-w-0 flex-1">
                <p class="text-sm">
                    <Link v-if="post.author.profile_id" :href="route('alumni.show', post.author.profile_id)" class="font-semibold text-ink hover:underline">{{ post.author.name }}</Link>
                    <span v-else class="font-semibold text-ink">{{ post.author.name }}</span>
                    <template v-if="post.community">
                        <span class="text-subtle"> in </span>
                        <Link :href="route('communities.show', post.community.slug)" class="font-medium text-brand-700 hover:underline">{{ post.community.name }}</Link>
                    </template>
                </p>
                <p class="text-xs text-muted">
                    {{ [post.author.subtitle, post.at].filter(Boolean).join(' · ') }}
                    <span v-if="post.pinned" class="ml-1 font-medium text-accent-600">· Pinned</span>
                    <span v-if="post.kind !== 'post'" class="ml-1 font-medium text-accent-600 capitalize">· {{ post.kind }}</span>
                </p>
            </div>
        </header>

        <div class="mt-3 text-sm leading-relaxed text-ink">
            <LinkifiedText :text="body" />
            <button v-if="long && !expanded" type="button" class="ml-1 font-medium text-brand-700" @click="expanded = true">See more</button>
        </div>

        <a v-if="post.link_url" :href="post.link_url" target="_blank" rel="noopener noreferrer nofollow ugc" class="mt-3 flex items-center gap-2 truncate rounded-md bg-surface-muted px-3 py-2 text-sm text-brand-700 ring-1 ring-line hover:bg-surface-sunken">
            <Link2 :size="15" class="shrink-0" aria-hidden="true" /><span class="truncate">{{ post.link_url }}</span>
        </a>

        <Link v-if="post.shared" :href="post.shared.url" class="mt-3 block rounded-lg bg-brand-50 px-4 py-3 ring-1 ring-brand-100 hover:bg-brand-100">
            <span class="text-xs font-medium tracking-wide text-brand-600 uppercase">{{ post.shared.kind }}</span>
            <span class="block font-medium text-brand-900">{{ post.shared.title }}</span>
            <span class="block text-sm text-brand-700">{{ post.shared.subtitle }}</span>
        </Link>

        <footer class="mt-4 flex flex-wrap items-center gap-1 border-t border-line-soft pt-3 text-sm">
            <button
                type="button"
                :disabled="!post.can.interact"
                :aria-pressed="liked"
                :class="['inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 font-medium disabled:opacity-50', liked ? 'text-red-600' : 'text-muted hover:bg-surface-muted']"
                @click="toggleLike"
            >
                <Heart :size="16" :fill="liked ? 'currentColor' : 'none'" aria-hidden="true" />{{ likes || '' }} Like
            </button>
            <Link v-if="!full" :href="route('posts.show', post.id)" class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 font-medium text-muted hover:bg-surface-muted"><MessageCircle :size="16" aria-hidden="true" />{{ post.comments || '' }} Comment</Link>
            <button type="button" :aria-pressed="saved" :class="['inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 font-medium', saved ? 'text-brand-700' : 'text-muted hover:bg-surface-muted']" @click="toggleSave">
                <Bookmark :size="16" :fill="saved ? 'currentColor' : 'none'" aria-hidden="true" />{{ saved ? 'Saved' : 'Save' }}
            </button>
            <span class="ml-auto flex items-center gap-3">
                <button v-if="post.can.pin" type="button" class="text-muted hover:text-ink" @click="pin">{{ post.pinned ? 'Unpin' : 'Pin' }}</button>
                <button v-if="post.can.delete" type="button" class="text-muted hover:text-red-700" @click="remove">Delete</button>
                <ReportButton type="post" :id="post.id" :reasons="reportReasons" />
            </span>
        </footer>
    </article>
</template>
