<script setup lang="ts">
import { Star } from 'lucide-vue-next';
import { ask } from '@/lib/confirm';
import AutoBreadcrumbs from '@/Components/AutoBreadcrumbs.vue';
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppButton from '@/Components/AppButton.vue';
import CardPanel from '@/Components/CardPanel.vue';
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
}>();

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
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <AppButton variant="ghost" :href="route('directory')" class="-ml-3">← Directory</AppButton>
            <AppButton v-if="isOwner" variant="secondary" :href="route('profile.edit')">Edit my profile</AppButton>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <CardPanel>
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="flex items-start gap-4">
                            <AvatarImage :name="profile.name" :src="profile.photo_url" size="xl" />
                            <div>
                            <h1 class="text-2xl font-semibold text-ink">{{ profile.name }}</h1>
                            <p v-if="profile.designation || profile.company" class="mt-1 text-ink-soft">
                                {{ [profile.designation, profile.company].filter(Boolean).join(' at ') }}
                            </p>
                            <p v-if="profile.location" class="text-sm text-muted">{{ profile.location }}</p>
                            </div>
                        </div>
                        <span class="flex flex-col items-end gap-1">
                            <StatusBadge v-if="profile.is_verified" status="verified" label="Verified alumnus" />
                            <span v-if="distinguished" class="inline-flex items-center gap-1 rounded-full bg-accent-400/20 px-2 py-0.5 text-xs font-semibold text-amber-900"><Star :size="12" fill="currentColor" aria-hidden="true" />Distinguished Alumnus {{ distinguished.year }}</span>
                        </span>
                    </div>

                    <div v-if="relationship" class="mt-5 flex flex-wrap items-center gap-2">
                        <AppButton v-if="relationship.state === 'none'" @click="connecting = true">Connect</AppButton>
                        <AppButton v-else-if="relationship.state === 'sent'" variant="secondary" @click="remove('Withdraw your request?')">Request sent · Withdraw</AppButton>
                        <AppButton v-else-if="relationship.state === 'received'" @click="accept">Accept request</AppButton>
                        <AppButton v-else variant="secondary" @click="remove(`Remove ${profile.name} from your connections?`)">Connected ✓</AppButton>
                        <AppButton variant="secondary" @click="messaging = true">Message</AppButton>
                        <AppButton variant="ghost" @click="toggleFollow">{{ relationship.following ? 'Following' : 'Follow' }}</AppButton>
                        <span v-if="relationship.mutual" class="text-sm text-muted">{{ relationship.mutual }} mutual {{ relationship.mutual === 1 ? 'connection' : 'connections' }}</span>
                        <span class="ml-auto flex gap-4">
                            <ReportButton type="alumni_profile" :id="profile.id" :reasons="reportReasons" />
                            <button type="button" class="text-sm text-muted hover:text-red-700" @click="block">Block</button>
                        </span>
                    </div>
                    <p v-if="profile.bio" class="mt-5 text-sm leading-relaxed whitespace-pre-line text-ink-soft">{{ profile.bio }}</p>
                </CardPanel>

                <CardPanel v-if="achievements.length" title="Achievements">
                    <ul class="space-y-3 text-sm">
                        <li v-for="(a, i) in achievements" :key="i">
                            <p class="font-medium text-ink">
                                <a v-if="a.link_url" :href="a.link_url" target="_blank" rel="noopener noreferrer nofollow" class="hover:underline">{{ a.title }}</a>
                                <template v-else>{{ a.title }}</template>
                            </p>
                            <p class="text-muted">{{ a.category }}<span v-if="a.date"> · {{ a.date }}</span></p>
                        </li>
                    </ul>
                </CardPanel>

                <CardPanel v-if="profile.interests.length" title="Happy to help with">
                    <ul class="flex flex-wrap gap-2">
                        <li v-for="i in profile.interests" :key="i" class="rounded-full bg-accent-400/15 px-3 py-1 text-sm text-amber-800">{{ interestOptions[i] }}</li>
                    </ul>
                </CardPanel>
            </div>

            <div class="space-y-6">
                <CardPanel title="At IIITM">
                    <dl class="space-y-3 text-sm">
                        <div><dt class="text-muted">Programme</dt><dd class="font-medium">{{ profile.programme }}</dd></div>
                        <div v-if="profile.department"><dt class="text-muted">Department</dt><dd class="font-medium">{{ profile.department }}</dd></div>
                        <div><dt class="text-muted">Batch</dt><dd class="font-medium">{{ profile.admission_year ? `${profile.admission_year}–` : '' }}{{ profile.graduation_year }}</dd></div>
                        <div v-if="profile.specialization"><dt class="text-muted">Specialization</dt><dd class="font-medium">{{ profile.specialization }}</dd></div>
                    </dl>
                </CardPanel>

                <CardPanel v-if="profile.email || profile.phone || profile.linkedin_url || profile.website_url" title="Contact">
                    <ul class="space-y-2 text-sm">
                        <li v-if="profile.email"><a :href="`mailto:${profile.email}`" class="text-brand-700 hover:underline">{{ profile.email }}</a></li>
                        <li v-if="profile.phone">{{ profile.phone }}</li>
                        <li v-if="profile.linkedin_url"><a :href="profile.linkedin_url" rel="noopener noreferrer nofollow" target="_blank" class="text-brand-700 hover:underline">LinkedIn profile ↗</a></li>
                        <li v-if="profile.website_url"><a :href="profile.website_url" rel="noopener noreferrer nofollow" target="_blank" class="text-brand-700 hover:underline">Website ↗</a></li>
                    </ul>
                </CardPanel>
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
