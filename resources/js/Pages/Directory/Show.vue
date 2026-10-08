<script setup lang="ts">
import { toast } from '@/lib/toast';
import { Ban, Briefcase, Building2, Check, Ellipsis, Globe, GraduationCap, Link2, Mail, MapPin, MessageCircle, Phone, Rss, Share2, Sparkles, Star, Trophy, UserPlus, UserRoundPen } from 'lucide-vue-next';
import { ask } from '@/lib/confirm';
import AutoBreadcrumbs from '@/Components/AutoBreadcrumbs.vue';
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppButton from '@/Components/AppButton.vue';
import CardPanel from '@/Components/CardPanel.vue';
import DropdownMenu from '@/Components/DropdownMenu.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PostCard, { type FeedPost } from '@/Components/PostCard.vue';
import TabsNav from '@/Components/TabsNav.vue';
import AvatarImage from '@/Components/AvatarImage.vue';
import FormField from '@/Components/FormField.vue';
import ModalDialog from '@/Components/ModalDialog.vue';
import ReportButton from '@/Components/ReportButton.vue';
import TextArea from '@/Components/TextArea.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

interface Profile {
    id: number;
    name: string;
    programme: string;
    department: string | null;
    graduation_year: number;
    admission_year: number | null;
    is_verified: boolean;
    photo_url: string | null;
    interests: string[];
    specialization: string | null;
    industry: string | null;
    website_url: string | null;
    // Present only when the owner's privacy settings allow this viewer.
    company?: string | null;
    designation?: string | null;
    location?: string | null;
    bio?: string | null;
    linkedin_url?: string | null;
    email?: string;
    phone?: string | null;
}

interface Relationship {
    state: 'none' | 'sent' | 'received' | 'connected';
    connection_id: number | null;
    following: boolean;
    blocked: boolean;
    mutual: number;
}

const props = defineProps<{
    profile: Profile;
    interestOptions: Record<string, string>;
    isOwner: boolean;
    relationship: Relationship | null;
    reportReasons: Record<string, string>;
    achievements: { title: string; category: string; date: string | null; link_url: string | null }[];
    distinguished: { category: string; year: number } | null;
    stats: { connections: number; followers: number; following: number };
    posts: FeedPost[];
}>();

const tab = ref<'about' | 'posts'>('about');
const tabs = computed(() => [
    { key: 'about' as const, label: 'About', icon: Sparkles },
    { key: 'posts' as const, label: 'Posts', icon: Rss, count: props.posts.length || undefined },
]);
const headline = computed(() => [props.profile.designation, props.profile.company].filter(Boolean).join(' at '));
const fmt = (n: number) => (n >= 1000 ? `${(n / 1000).toFixed(1)}k` : String(n));

async function shareProfile() {
    const url = window.location.href;
    try {
        if (navigator.share) return void (await navigator.share({ title: props.profile.name, url }));
        await navigator.clipboard.writeText(url);
        toast('Profile link copied.', 'info');
    } catch {
        /* dismissed */
    }
}

const opts = { preserveScroll: true };
const connecting = ref(false);
const connectForm = useForm({ message: '' });
const sendRequest = () => connectForm.post(route('connections.store', props.profile.id), { ...opts, onSuccess: () => ((connecting.value = false), connectForm.reset()) });
const accept = () => router.post(route('connections.accept', props.relationship!.connection_id!), {}, opts);
const remove = (q: string) => ask(q).then((ok) => ok && router.delete(route('connections.destroy', props.relationship!.connection_id!), opts));
const messaging = ref(false);
const messageForm = useForm({ body: '' });
const sendMessage = () => messageForm.post(route('messages.start', props.profile.id));
const toggleFollow = () =>
    props.relationship!.following ? router.delete(route('alumni.unfollow', props.profile.id), opts) : router.post(route('alumni.follow', props.profile.id), {}, opts);
const block = () =>
    ask(`Block ${props.profile.name}? You will no longer see each other in the directory, and any connection is removed.`).then((ok) => ok && router.post(route('alumni.block', props.profile.id)));
</script>

<template>
    <AppLayout :title="profile.name">
        <AutoBreadcrumbs :title="profile.name" class="mb-4" />

        <div class="mx-auto max-w-5xl space-y-5">
            <!-- Header -->
            <section class="card overflow-hidden">
                <div class="relative h-32 overflow-hidden bg-gradient-to-br from-deep-800 via-brand-500 to-accent-400 sm:h-44" aria-hidden="true">
                    <span class="absolute -top-10 right-10 size-40 rounded-full bg-white/15 blur-2xl" />
                    <span class="absolute bottom-0 left-1/3 size-32 rounded-full bg-accent-400/40 blur-3xl" />
                </div>
                <div class="px-5 pb-5 sm:px-7">
                    <div class="-mt-14 flex flex-wrap items-end justify-between gap-3 sm:-mt-16">
                        <AvatarImage :name="profile.name" :src="profile.photo_url" size="xl" class="relative z-10 ring-[5px] ring-surface" />
                        <div class="flex flex-wrap items-center gap-2 pb-1">
                            <template v-if="isOwner">
                                <AppButton :icon="UserRoundPen" :href="route('profile.edit')">Edit profile</AppButton>
                                <AppButton variant="secondary" :icon="Share2" aria-label="Share profile" @click="shareProfile" />
                            </template>
                            <template v-else-if="relationship">
                                <AppButton v-if="relationship.state === 'none'" :icon="UserPlus" @click="connecting = true">Connect</AppButton>
                                <AppButton v-else-if="relationship.state === 'sent'" variant="secondary" @click="remove('Withdraw your request?')">Requested</AppButton>
                                <AppButton v-else-if="relationship.state === 'received'" :icon="Check" @click="accept">Accept request</AppButton>
                                <AppButton v-else variant="secondary" :icon="Check" @click="remove(`Remove ${profile.name} from your connections?`)">Connected</AppButton>
                                <AppButton variant="secondary" :icon="MessageCircle" @click="messaging = true">Message</AppButton>
                                <AppButton :variant="relationship.following ? 'secondary' : 'outline'" @click="toggleFollow">{{ relationship.following ? 'Following' : 'Follow' }}</AppButton>
                                <ReportButton type="alumni_profile" :id="profile.id" :reasons="reportReasons" icon-only />
                                <DropdownMenu label="More options" width="w-44">
                                    <template #trigger="{ toggle, open }">
                                        <AppButton variant="secondary" :icon="Ellipsis" aria-label="More options" aria-haspopup="menu" :aria-expanded="open" @click.stop="toggle" />
                                    </template>
                                    <button type="button" class="dropdown-item" @click="shareProfile"><Share2 :size="16" />Share profile</button>
                                    <button type="button" class="dropdown-item !text-red-600" @click="block"><Ban :size="16" />Block</button>
                                </DropdownMenu>
                            </template>
                        </div>
                    </div>

                    <div class="mt-3">
                        <h1 class="flex flex-wrap items-center gap-2 text-2xl font-extrabold tracking-tight text-ink sm:text-3xl">
                            {{ profile.name }}
                            <StatusBadge v-if="profile.is_verified" status="verified" label="Verified" />
                        </h1>
                        <p v-if="headline" class="mt-1 text-base font-medium text-ink-soft">{{ headline }}</p>
                        <p class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted">
                            <span class="inline-flex items-center gap-1.5"><GraduationCap :size="15" aria-hidden="true" />{{ profile.programme }} · {{ profile.graduation_year }}</span>
                            <span v-if="profile.location" class="inline-flex items-center gap-1.5"><MapPin :size="15" aria-hidden="true" />{{ profile.location }}</span>
                        </p>
                        <span v-if="distinguished" class="mt-3 inline-flex items-center gap-1.5 rounded-full bg-gradient-to-r from-amber-100 to-accent-50 px-3 py-1 text-xs font-bold text-amber-800">
                            <Star :size="13" fill="currentColor" aria-hidden="true" />Distinguished Alumnus {{ distinguished.year }} · {{ distinguished.category }}
                        </span>
                    </div>

                    <dl class="mt-5 flex flex-wrap gap-x-6 gap-y-2 text-sm">
                        <div class="flex items-baseline gap-1.5"><dd class="text-lg font-extrabold text-ink tabular-nums">{{ fmt(stats.connections) }}</dd><dt class="text-muted">connections</dt></div>
                        <div class="flex items-baseline gap-1.5"><dd class="text-lg font-extrabold text-ink tabular-nums">{{ fmt(stats.followers) }}</dd><dt class="text-muted">followers</dt></div>
                        <div class="flex items-baseline gap-1.5"><dd class="text-lg font-extrabold text-ink tabular-nums">{{ fmt(stats.following) }}</dd><dt class="text-muted">following</dt></div>
                        <div v-if="relationship?.mutual" class="flex items-baseline gap-1.5"><dd class="text-lg font-extrabold text-brand-600 tabular-nums">{{ relationship.mutual }}</dd><dt class="text-muted">mutual</dt></div>
                    </dl>
                </div>
                <div class="px-3 sm:px-5"><TabsNav v-model="tab" :items="tabs" /></div>
            </section>

            <!-- About -->
            <div v-if="tab === 'about'" class="grid grid-cols-[minmax(0,1fr)] gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <div class="min-w-0 space-y-5">
                    <CardPanel v-if="profile.bio" title="About">
                        <p class="text-[15px] leading-relaxed whitespace-pre-line text-ink-soft">{{ profile.bio }}</p>
                    </CardPanel>

                    <CardPanel v-if="profile.interests.length" title="Open to" :icon="Sparkles">
                        <ul class="flex flex-wrap gap-2">
                            <li v-for="i in profile.interests" :key="i" class="rounded-full bg-gradient-to-r from-brand-50 to-accent-50 px-3.5 py-1.5 text-sm font-semibold text-brand-700 ring-1 ring-brand-100">
                                {{ interestOptions[i] }}
                            </li>
                        </ul>
                    </CardPanel>

                    <CardPanel v-if="profile.designation || profile.company || profile.industry" title="Experience" :icon="Briefcase">
                        <div class="flex items-start gap-3">
                            <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-surface-sunken text-muted"><Building2 :size="20" aria-hidden="true" /></span>
                            <div>
                                <p class="font-bold text-ink">{{ profile.designation ?? 'Current role' }}</p>
                                <p v-if="profile.company" class="text-sm text-ink-soft">{{ profile.company }}</p>
                                <p v-if="profile.industry" class="text-sm text-muted">{{ profile.industry }}</p>
                            </div>
                        </div>
                    </CardPanel>

                    <CardPanel v-if="achievements.length" title="Achievements" :icon="Trophy">
                        <ol class="relative space-y-4 border-l-2 border-line pl-5">
                            <li v-for="(a, i) in achievements" :key="i" class="relative">
                                <span class="absolute top-1 -left-[1.6rem] size-3 rounded-full bg-gradient-to-br from-amber-400 to-accent-500 ring-4 ring-surface" aria-hidden="true" />
                                <p class="font-bold text-ink">
                                    <a v-if="a.link_url" :href="a.link_url" target="_blank" rel="noopener noreferrer nofollow" class="hover:underline">{{ a.title }}</a>
                                    <template v-else>{{ a.title }}</template>
                                </p>
                                <p class="text-sm text-muted">{{ a.category }}<span v-if="a.date"> · {{ a.date }}</span></p>
                            </li>
                        </ol>
                    </CardPanel>

                    <EmptyState
                        v-if="!profile.bio && !profile.interests.length && !achievements.length && !headline"
                        :icon="Sparkles"
                        :title="isOwner ? 'Your profile is a blank canvas' : `${profile.name.split(' ')[0]} hasn’t added much yet`"
                        :description="isOwner ? 'Add a bio, your role and what you’re open to — it helps batchmates and students find you.' : 'Connect or say hi — maybe they’ll share more.'"
                    >
                        <AppButton v-if="isOwner" :href="route('profile.edit')">Complete profile</AppButton>
                    </EmptyState>
                </div>

                <div class="space-y-5">
                    <CardPanel title="At IIITM" :icon="GraduationCap">
                        <dl class="space-y-3 text-sm">
                            <div><dt class="text-xs font-semibold text-muted">Programme</dt><dd class="font-semibold text-ink">{{ profile.programme }}</dd></div>
                            <div v-if="profile.department"><dt class="text-xs font-semibold text-muted">Department</dt><dd class="font-semibold text-ink">{{ profile.department }}</dd></div>
                            <div><dt class="text-xs font-semibold text-muted">Batch</dt><dd class="font-semibold text-ink">{{ profile.admission_year ? `${profile.admission_year}–` : '' }}{{ profile.graduation_year }}</dd></div>
                            <div v-if="profile.specialization"><dt class="text-xs font-semibold text-muted">Specialization</dt><dd class="font-semibold text-ink">{{ profile.specialization }}</dd></div>
                        </dl>
                    </CardPanel>

                    <CardPanel v-if="profile.email || profile.phone || profile.linkedin_url || profile.website_url" title="Contact & links" :icon="Link2">
                        <ul class="space-y-2">
                            <li v-if="profile.email">
                                <a :href="`mailto:${profile.email}`" class="flex items-center gap-3 rounded-xl p-2 text-sm font-semibold text-ink hover:bg-surface-muted"
                                    ><span class="grid size-8 place-items-center rounded-lg bg-brand-50 text-brand-600"><Mail :size="16" /></span><span class="truncate">{{ profile.email }}</span></a
                                >
                            </li>
                            <li v-if="profile.phone" class="flex items-center gap-3 p-2 text-sm font-semibold text-ink">
                                <span class="grid size-8 place-items-center rounded-lg bg-emerald-50 text-emerald-600"><Phone :size="16" /></span>{{ profile.phone }}
                            </li>
                            <li v-if="profile.linkedin_url">
                                <a :href="profile.linkedin_url" rel="noopener noreferrer nofollow" target="_blank" class="flex items-center gap-3 rounded-xl p-2 text-sm font-semibold text-ink hover:bg-surface-muted"
                                    ><span class="grid size-8 place-items-center rounded-lg bg-sky-100 text-sky-700"><Briefcase :size="16" /></span>LinkedIn</a
                                >
                            </li>
                            <li v-if="profile.website_url">
                                <a :href="profile.website_url" rel="noopener noreferrer nofollow" target="_blank" class="flex items-center gap-3 rounded-xl p-2 text-sm font-semibold text-ink hover:bg-surface-muted"
                                    ><span class="grid size-8 place-items-center rounded-lg bg-accent-50 text-accent-600"><Globe :size="16" /></span>Website</a
                                >
                            </li>
                        </ul>
                    </CardPanel>
                </div>
            </div>

            <!-- Posts -->
            <div v-else class="mx-auto max-w-2xl space-y-4">
                <EmptyState v-if="posts.length === 0" :icon="Rss" :title="isOwner ? 'You haven’t posted yet' : 'No posts yet'" :description="isOwner ? 'Share an update with the network from your home feed.' : 'Their updates will show up here.'">
                    <AppButton v-if="isOwner" :href="route('feed')">Write a post</AppButton>
                </EmptyState>
                <PostCard v-for="p in posts" :key="p.id" :post="p" :report-reasons="reportReasons" />
            </div>
        </div>

        <ModalDialog :show="messaging" :title="`Message ${profile.name}`" @close="messaging = false">
            <form id="message-form" @submit.prevent="sendMessage">
                <p v-if="relationship?.state !== 'connected'" class="mb-3 text-sm text-muted">You aren’t connected, so this goes as a message request. You can send one message until they accept.</p>
                <FormField label="Message" :error="messageForm.errors.body" required><TextArea v-model="messageForm.body" rows="4" maxlength="4000" /></FormField>
            </form>
            <template #footer>
                <AppButton variant="secondary" @click="messaging = false">Cancel</AppButton>
                <AppButton type="submit" form="message-form" :loading="messageForm.processing">Send</AppButton>
            </template>
        </ModalDialog>

        <ModalDialog :show="connecting" :title="`Connect with ${profile.name}`" @close="connecting = false">
            <form id="connect-form" @submit.prevent="sendRequest">
                <FormField label="Add a note (optional)" :error="connectForm.errors.message" hint="e.g. how you know each other.">
                    <TextArea v-model="connectForm.message" rows="3" maxlength="300" />
                </FormField>
            </form>
            <template #footer>
                <AppButton variant="secondary" @click="connecting = false">Cancel</AppButton>
                <AppButton type="submit" form="connect-form" :loading="connectForm.processing">Send request</AppButton>
            </template>
        </ModalDialog>
    </AppLayout>
</template>
