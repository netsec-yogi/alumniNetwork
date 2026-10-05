<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppButton from '@/Components/AppButton.vue';
import CardPanel from '@/Components/CardPanel.vue';
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
}>();

const opts = { preserveScroll: true };
const connecting = ref(false);
const connectForm = useForm({ message: '' });
const sendRequest = () => connectForm.post(route('connections.store', props.profile.id), { ...opts, onSuccess: () => ((connecting.value = false), connectForm.reset()) });
const accept = () => router.post(route('connections.accept', props.relationship!.connection_id!), {}, opts);
const remove = (q: string) => confirm(q) && router.delete(route('connections.destroy', props.relationship!.connection_id!), opts);
const toggleFollow = () =>
    props.relationship!.following ? router.delete(route('alumni.unfollow', props.profile.id), opts) : router.post(route('alumni.follow', props.profile.id), {}, opts);
const block = () =>
    confirm(`Block ${props.profile.name}? You will no longer see each other in the directory, and any connection is removed.`) &&
    router.post(route('alumni.block', props.profile.id));
</script>

<template>
    <AppLayout :title="profile.name">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <AppButton variant="ghost" :href="route('directory')" class="-ml-3">← Directory</AppButton>
            <AppButton v-if="isOwner" variant="secondary" :href="route('profile.edit')">Edit my profile</AppButton>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <CardPanel>
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h1 class="text-2xl font-semibold text-slate-900">{{ profile.name }}</h1>
                            <p v-if="profile.designation || profile.company" class="mt-1 text-slate-700">
                                {{ [profile.designation, profile.company].filter(Boolean).join(' at ') }}
                            </p>
                            <p v-if="profile.location" class="text-sm text-slate-500">{{ profile.location }}</p>
                        </div>
                        <StatusBadge v-if="profile.is_verified" status="verified" label="Verified alumnus" />
                    </div>

                    <div v-if="relationship" class="mt-5 flex flex-wrap items-center gap-2">
                        <AppButton v-if="relationship.state === 'none'" @click="connecting = true">Connect</AppButton>
                        <AppButton v-else-if="relationship.state === 'sent'" variant="secondary" @click="remove('Withdraw your request?')">Request sent · Withdraw</AppButton>
                        <AppButton v-else-if="relationship.state === 'received'" @click="accept">Accept request</AppButton>
                        <AppButton v-else variant="secondary" @click="remove(`Remove ${profile.name} from your connections?`)">Connected ✓</AppButton>
                        <AppButton variant="ghost" @click="toggleFollow">{{ relationship.following ? 'Following' : 'Follow' }}</AppButton>
                        <span v-if="relationship.mutual" class="text-sm text-slate-500">{{ relationship.mutual }} mutual {{ relationship.mutual === 1 ? 'connection' : 'connections' }}</span>
                        <span class="ml-auto flex gap-4">
                            <ReportButton type="alumni_profile" :id="profile.id" :reasons="reportReasons" />
                            <button type="button" class="text-sm text-slate-500 hover:text-red-700" @click="block">Block</button>
                        </span>
                    </div>
                    <p v-if="profile.bio" class="mt-5 text-sm leading-relaxed whitespace-pre-line text-slate-700">{{ profile.bio }}</p>
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
                        <div><dt class="text-slate-500">Programme</dt><dd class="font-medium">{{ profile.programme }}</dd></div>
                        <div v-if="profile.department"><dt class="text-slate-500">Department</dt><dd class="font-medium">{{ profile.department }}</dd></div>
                        <div><dt class="text-slate-500">Batch</dt><dd class="font-medium">{{ profile.admission_year ? `${profile.admission_year}–` : '' }}{{ profile.graduation_year }}</dd></div>
                        <div v-if="profile.specialization"><dt class="text-slate-500">Specialization</dt><dd class="font-medium">{{ profile.specialization }}</dd></div>
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
