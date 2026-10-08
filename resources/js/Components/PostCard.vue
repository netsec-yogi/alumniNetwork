<script setup lang="ts">
import { toast } from '@/lib/toast';
import { Bookmark, Ellipsis, Heart, Link2, Megaphone, MessageCircle, Pin, Send, Trophy, Trash2 } from 'lucide-vue-next';
import { ask } from '@/lib/confirm';
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppTooltip from './AppTooltip.vue';
import AvatarImage from './AvatarImage.vue';
import DropdownMenu from './DropdownMenu.vue';
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
    comment_preview?: { id: number; author: string; photo_url: string | null; body: string }[];
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

const popping = ref(false);
function toggleLike() {
    liked.value = !liked.value;
    if (liked.value) {
        popping.value = true;
        setTimeout(() => (popping.value = false), 360);
    }
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
// Share: the native share sheet where there is one (phones), otherwise copy the link.
async function share() {
    const url = new URL(route('posts.show', props.post.id), window.location.origin).toString();
    try {
        if (navigator.share) {
            await navigator.share({ title: `Post by ${props.post.author.name}`, url });
            return;
        }
        await navigator.clipboard.writeText(url);
        toast('Link copied — paste it anywhere to share.', 'info');
    } catch {
        /* share sheet dismissed */
    }
}
const domain = computed(() => {
    try {
        return props.post.link_url ? new URL(props.post.link_url).hostname.replace(/^www\./, '') : '';
    } catch {
        return '';
    }
});
const pin = () => router.post(route('posts.pin', props.post.id), {}, { preserveScroll: true });

const long = computed(() => props.post.body.length > 600);
const body = computed(() => (long.value && !expanded.value ? props.post.body.slice(0, 600) + '…' : props.post.body));
</script>

<template>
    <article v-if="!removed" :class="['card relative overflow-hidden p-4 sm:p-5', post.kind === 'announcement' && 'ring-2 ring-accent-400/60']">
        <span v-if="post.kind === 'announcement'" class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-brand-500 to-accent-500" aria-hidden="true" />
        <header class="flex items-start gap-3">
            <Link v-if="post.author.profile_id" :href="route('alumni.show', post.author.profile_id)" tabindex="-1" aria-hidden="true"><AvatarImage :name="post.author.name" :src="post.author.photo_url" /></Link>
            <AvatarImage v-else :name="post.author.name" :src="post.author.photo_url" />
            <div class="min-w-0 flex-1">
                <p class="text-[15px] leading-snug">
                    <Link v-if="post.author.profile_id" :href="route('alumni.show', post.author.profile_id)" class="font-bold text-ink hover:underline">{{ post.author.name }}</Link>
                    <span v-else class="font-bold text-ink">{{ post.author.name }}</span>
                    <template v-if="post.community">
                        <span class="text-subtle"> in </span>
                        <Link :href="route('communities.show', post.community.slug)" class="font-semibold text-brand-600 hover:underline dark:text-brand-300">{{ post.community.name }}</Link>
                    </template>
                </p>
                <p class="mt-0.5 flex flex-wrap items-center gap-x-1.5 text-[13px] text-muted">
                    <span v-if="post.author.subtitle">{{ post.author.subtitle }}</span>
                    <span v-if="post.author.subtitle" aria-hidden="true">·</span>
                    <span>{{ post.at }}</span>
                    <span v-if="post.pinned" class="inline-flex items-center gap-1 font-semibold text-brand-600"><Pin :size="12" aria-hidden="true" />Pinned</span>
                </p>
            </div>
            <span v-if="post.kind === 'announcement'" class="inline-flex items-center gap-1 rounded-full bg-accent-50 px-2.5 py-1 text-xs font-bold text-accent-600"><Megaphone :size="13" aria-hidden="true" />Announcement</span>
            <span v-else-if="post.kind === 'achievement'" class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-700"><Trophy :size="13" aria-hidden="true" />Achievement</span>
            <DropdownMenu v-if="post.can.pin || post.can.delete" label="Post options" width="w-44">
                <template #trigger="{ toggle, open }">
                    <button type="button" class="press -mt-1 -mr-1 rounded-full p-2 text-subtle hover:bg-surface-sunken hover:text-ink" aria-label="Post options" aria-haspopup="menu" :aria-expanded="open" @click.stop="toggle"><Ellipsis :size="18" /></button>
                </template>
                <button v-if="post.can.pin" type="button" class="dropdown-item" @click="pin"><Pin :size="16" />{{ post.pinned ? 'Unpin' : 'Pin to top' }}</button>
                <button v-if="post.can.delete" type="button" class="dropdown-item !text-red-600" @click="remove"><Trash2 :size="16" />Delete post</button>
            </DropdownMenu>
        </header>

        <div class="mt-3 text-[15px] leading-relaxed whitespace-pre-line text-ink">
            <LinkifiedText :text="body" />
            <button v-if="long && !expanded" type="button" class="ml-1 font-semibold text-brand-600" @click="expanded = true">See more</button>
        </div>

        <a
            v-if="post.link_url"
            :href="post.link_url"
            target="_blank"
            rel="noopener noreferrer nofollow ugc"
            class="group mt-3 flex items-center gap-3 overflow-hidden rounded-2xl bg-surface-muted p-3 ring-1 ring-line transition hover:ring-brand-300"
        >
            <span class="grid size-12 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-brand-100 to-accent-50 text-brand-600 dark:from-brand-500/20 dark:to-accent-500/10"><Link2 :size="20" aria-hidden="true" /></span>
            <span class="min-w-0">
                <span class="block truncate text-sm font-semibold text-ink group-hover:underline">{{ domain || 'Link' }}</span>
                <span class="block truncate text-xs text-muted">{{ post.link_url }}</span>
            </span>
        </a>

        <Link
            v-if="post.shared"
            :href="post.shared.url"
            class="mt-3 block overflow-hidden rounded-2xl bg-gradient-to-br from-deep-800 to-deep-950 p-4 text-white ring-1 ring-white/10 transition hover:brightness-110"
        >
            <span class="inline-flex rounded-full bg-white/15 px-2 py-0.5 text-[11px] font-bold tracking-wide uppercase">{{ post.shared.kind }}</span>
            <span class="mt-2 block text-lg leading-snug font-bold">{{ post.shared.title }}</span>
            <span class="mt-0.5 block text-sm text-white/75">{{ post.shared.subtitle }}</span>
        </Link>

        <footer class="mt-3 flex items-center gap-1 text-sm">
            <button
                type="button"
                :disabled="!post.can.interact"
                :aria-pressed="liked"
                :aria-label="`${liked ? 'Unlike' : 'Like'}${likes ? ` (${likes} likes)` : ''}`"
                :class="['press inline-flex items-center gap-1.5 rounded-full px-3 py-2 font-semibold disabled:opacity-50', liked ? 'bg-accent-50 text-accent-600' : 'text-muted hover:bg-surface-sunken']"
                @click="toggleLike"
            >
                <Heart :size="18" :fill="liked ? 'currentColor' : 'none'" :class="popping && 'animate-pop'" aria-hidden="true" /><span class="tabular-nums">{{ likes || '' }}</span>
            </button>
            <Link
                v-if="!full"
                :href="route('posts.show', post.id)"
                class="press inline-flex items-center gap-1.5 rounded-full px-3 py-2 font-semibold text-muted hover:bg-surface-sunken"
                :aria-label="`Comments${post.comments ? ` (${post.comments})` : ''}`"
            >
                <MessageCircle :size="18" aria-hidden="true" /><span class="tabular-nums">{{ post.comments || '' }}</span>
            </Link>
            <AppTooltip text="Share">
                <button type="button" class="press inline-flex items-center rounded-full px-3 py-2 font-semibold text-muted hover:bg-surface-sunken" aria-label="Share post" @click="share"><Send :size="18" /></button>
            </AppTooltip>
            <span class="ml-auto flex items-center">
                <ReportButton type="post" :id="post.id" :reasons="reportReasons" icon-only />
                <AppTooltip :text="saved ? 'Saved' : 'Save'">
                    <button
                        type="button"
                        :aria-pressed="saved"
                        :aria-label="saved ? 'Remove from saved' : 'Save post'"
                        :class="['press rounded-full p-2 hover:bg-surface-sunken', saved ? 'text-brand-600 dark:text-brand-300' : 'text-muted']"
                        @click="toggleSave"
                    >
                        <Bookmark :size="18" :fill="saved ? 'currentColor' : 'none'" />
                    </button>
                </AppTooltip>
            </span>
        </footer>

        <!-- Comment preview -->
        <Link v-if="!full && post.comment_preview?.length" :href="route('posts.show', post.id)" class="mt-1 block space-y-2 border-t border-line-soft pt-3">
            <span v-for="c in post.comment_preview" :key="c.id" class="flex items-start gap-2">
                <AvatarImage :name="c.author" :src="c.photo_url" size="xs" class="mt-0.5" />
                <span class="min-w-0 rounded-2xl rounded-tl-md bg-surface-muted px-3 py-1.5 text-[13px]">
                    <span class="font-bold text-ink">{{ c.author }}</span>
                    <span class="ml-1 text-ink-soft">{{ c.body }}</span>
                </span>
            </span>
            <span v-if="post.comments > post.comment_preview.length" class="block pl-8 text-[13px] font-semibold text-muted hover:text-ink">View all {{ post.comments }} comments</span>
        </Link>
    </article>
</template>
