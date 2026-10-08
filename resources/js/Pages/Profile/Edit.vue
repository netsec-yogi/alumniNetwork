<script setup lang="ts">
import { ask } from '@/lib/confirm';
import AlertBox from '@/Components/AlertBox.vue';
import AppButton from '@/Components/AppButton.vue';
import CardPanel from '@/Components/CardPanel.vue';
import CheckboxInput from '@/Components/CheckboxInput.vue';
import FormField from '@/Components/FormField.vue';
import PageHeader from '@/Components/PageHeader.vue';
import SelectInput from '@/Components/SelectInput.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextArea from '@/Components/TextArea.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import AvatarImage from '@/Components/AvatarImage.vue';
import type { Option } from '@/types';
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Visibility = 'public' | 'alumni' | 'connections' | 'private';

const props = defineProps<{
    account: { name: string; email: string; phone: string | null };
    profile: {
        preferred_name: string | null;
        gender: string | null;
        bio: string | null;
        specialization: string | null;
        company: string | null;
        designation: string | null;
        industry: string | null;
        city: string | null;
        state: string | null;
        country: string | null;
        linkedin_url: string | null;
        website_url: string | null;
        roll_number: string;
        graduation_year: number;
        admission_year: number | null;
        programme: string;
        interests: string[];
        visibility: Record<string, Visibility>;
        verification_status: string;
        photo_url: string | null;
        photo_limit_kb: number;
    } | null;
    interestOptions: Record<string, string>;
    emailOptIn: boolean;
    visibilityOptions: Option<Visibility>[];
}>();

const accountForm = useForm({ ...props.account, current_password: '' });
const contactChanged = computed(() => accountForm.email.trim().toLowerCase() !== props.account.email || (accountForm.phone ?? '') !== (props.account.phone ?? ''));

const p = props.profile;
const profileForm = useForm({
    preferred_name: p?.preferred_name ?? '',
    gender: p?.gender ?? '',
    bio: p?.bio ?? '',
    specialization: p?.specialization ?? '',
    company: p?.company ?? '',
    designation: p?.designation ?? '',
    industry: p?.industry ?? '',
    city: p?.city ?? '',
    state: p?.state ?? '',
    country: p?.country ?? '',
    linkedin_url: p?.linkedin_url ?? '',
    website_url: p?.website_url ?? '',
    interests: p?.interests ?? [],
    visibility: { ...(p?.visibility ?? {}) } as Record<string, Visibility>,
});

const genderOptions = [
    { value: 'female', label: 'Female' },
    { value: 'male', label: 'Male' },
    { value: 'non_binary', label: 'Non-binary' },
    { value: 'prefer_not_to_say', label: 'Prefer not to say' },
];

const privacyLabels: Record<string, string> = {
    email: 'Email address',
    phone: 'Mobile number',
    company: 'Company',
    designation: 'Designation',
    location: 'Location',
    linkedin_url: 'LinkedIn',
    bio: 'About me',
};

// Pick → preview → save. The server optimises the photo to the size limit
// (and rejects it if it can't); this is only a preview.
const photoForm = useForm<{ photo: File | null }>({ photo: null });
const photoPreview = ref<string | null>(null);
function pickPhoto(e: Event) {
    const input = e.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    input.value = '';
    photoForm.clearErrors();
    if (!file) return;
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
        photoForm.setError('photo', 'Profile photo must be a JPG, PNG or WebP image.');
        return;
    }
    if (photoPreview.value) URL.revokeObjectURL(photoPreview.value);
    photoForm.photo = file;
    photoPreview.value = URL.createObjectURL(file);
}
function cancelPhoto() {
    if (photoPreview.value) URL.revokeObjectURL(photoPreview.value);
    photoPreview.value = null;
    photoForm.reset();
}
const savePhoto = () => photoForm.post(route('profile.photo.update'), { forceFormData: true, preserveScroll: true, onSuccess: cancelPhoto });
const removePhoto = () => ask('Remove your photo?').then((ok) => ok && router.delete(route('profile.photo.destroy'), { preserveScroll: true }));

const toggleEmail = (granted: boolean) => router.put(route('profile.communications'), { granted }, { preserveScroll: true });

const saveAccount = () => accountForm.put(route('profile.account.update'), { preserveScroll: true, onSuccess: () => accountForm.reset('current_password') });
const saveProfile = () => profileForm.put(route('profile.update'), { preserveScroll: true });
</script>

<template>
    <AppLayout title="My profile">
        <PageHeader title="My profile" description="Keep your details current so batchmates and students can find you." />

        <div class="space-y-6">
            <CardPanel v-if="profile" title="Academic record" description="From your registration. Contact the alumni office to correct these.">
                <dl class="grid gap-4 text-sm sm:grid-cols-4">
                    <div><dt class="text-muted">Programme</dt><dd class="mt-0.5 font-medium">{{ profile.programme }}</dd></div>
                    <div><dt class="text-muted">Roll number</dt><dd class="mt-0.5 font-medium">{{ profile.roll_number }}</dd></div>
                    <div><dt class="text-muted">Batch</dt><dd class="mt-0.5 font-medium">{{ profile.admission_year ? `${profile.admission_year}–` : '' }}{{ profile.graduation_year }}</dd></div>
                    <div><dt class="text-muted">Verification</dt><dd class="mt-0.5"><StatusBadge :status="profile.verification_status" /></dd></div>
                </dl>
            </CardPanel>

            <CardPanel
                v-if="profile"
                title="Profile photo"
                :description="`JPG, PNG or WebP. Saved photos are ${profile.photo_limit_kb} KB or smaller — larger ones are resized and compressed for you, and location and camera data are removed.`"
            >
                <div class="flex flex-wrap items-center gap-5">
                    <div class="relative">
                        <img v-if="photoPreview" :src="photoPreview" alt="Preview of your new photo" class="size-28 rounded-full object-cover ring-4 ring-brand-200" />
                        <AvatarImage v-else :name="account.name" :src="profile.photo_url" size="xl" />
                        <span v-if="photoPreview" class="absolute -bottom-1 left-1/2 -translate-x-1/2 rounded-full bg-brand-600 px-2 py-0.5 text-[11px] font-bold text-white">Preview</span>
                    </div>
                    <div class="space-y-2">
                        <div v-if="photoPreview" class="flex flex-wrap gap-2">
                            <AppButton :loading="photoForm.processing" @click="savePhoto">Save photo</AppButton>
                            <AppButton variant="secondary" :disabled="photoForm.processing" @click="cancelPhoto">Cancel</AppButton>
                        </div>
                        <div v-else class="flex flex-wrap items-center gap-2">
                            <label class="press inline-flex h-10 cursor-pointer items-center rounded-full bg-surface-sunken px-4.5 text-sm font-semibold text-ink hover:bg-line focus-within:outline-2 focus-within:outline-brand-500">
                                {{ profile.photo_url ? 'Change photo' : 'Upload photo' }}
                                <input type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="pickPhoto" />
                            </label>
                            <AppButton v-if="profile.photo_url" variant="danger-ghost" @click="removePhoto">Remove</AppButton>
                        </div>
                        <p class="text-xs text-muted">Visible to signed-in members. It appears publicly only if you're featured on the alumni home page.</p>
                        <p v-if="photoForm.errors.photo" class="text-sm text-red-600" role="alert">{{ photoForm.errors.photo }}</p>
                    </div>
                </div>
            </CardPanel>

            <CardPanel title="Account">
                <form class="grid gap-5 sm:grid-cols-2" @submit.prevent="saveAccount">
                    <FormField label="Full name" :error="accountForm.errors.name" required>
                        <TextInput v-model="accountForm.name" autocomplete="name" required />
                    </FormField>
                    <FormField label="Email address" :error="accountForm.errors.email" hint="Changing it sends a new verification link." required>
                        <TextInput v-model="accountForm.email" type="email" autocomplete="email" required />
                    </FormField>
                    <FormField label="Mobile number" :error="accountForm.errors.phone">
                        <TextInput v-model="accountForm.phone" type="tel" autocomplete="tel" />
                    </FormField>
                    <FormField v-if="contactChanged" label="Current password" :error="accountForm.errors.current_password" hint="Required to change your email or mobile." required>
                        <TextInput v-model="accountForm.current_password" type="password" autocomplete="current-password" required />
                    </FormField>
                    <div class="sm:col-span-2">
                        <AppButton type="submit" :loading="accountForm.processing">Save account</AppButton>
                    </div>
                </form>
            </CardPanel>

            <CardPanel title="Email preferences" description="Account and security emails are always sent.">
                <CheckboxInput :model-value="emailOptIn" label="Send me alumni news, events and opportunities by email" @update:model-value="toggleEmail(!!$event)" />
            </CardPanel>

            <form v-if="profile" class="space-y-6" @submit.prevent="saveProfile">
                <CardPanel title="Professional details">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <FormField label="Preferred name" :error="profileForm.errors.preferred_name" hint="Shown instead of your full name.">
                            <TextInput v-model="profileForm.preferred_name" />
                        </FormField>
                        <FormField label="Gender" :error="profileForm.errors.gender">
                            <SelectInput v-model="profileForm.gender" :options="genderOptions" placeholder="—" />
                        </FormField>
                        <FormField label="Company" :error="profileForm.errors.company">
                            <TextInput v-model="profileForm.company" autocomplete="organization" />
                        </FormField>
                        <FormField label="Designation" :error="profileForm.errors.designation">
                            <TextInput v-model="profileForm.designation" autocomplete="organization-title" />
                        </FormField>
                        <FormField label="Industry" :error="profileForm.errors.industry">
                            <TextInput v-model="profileForm.industry" />
                        </FormField>
                        <FormField label="Specialization" :error="profileForm.errors.specialization">
                            <TextInput v-model="profileForm.specialization" />
                        </FormField>
                        <FormField label="City" :error="profileForm.errors.city">
                            <TextInput v-model="profileForm.city" autocomplete="address-level2" />
                        </FormField>
                        <FormField label="State" :error="profileForm.errors.state">
                            <TextInput v-model="profileForm.state" autocomplete="address-level1" />
                        </FormField>
                        <FormField label="Country" :error="profileForm.errors.country">
                            <TextInput v-model="profileForm.country" autocomplete="country-name" />
                        </FormField>
                        <FormField label="LinkedIn" :error="profileForm.errors.linkedin_url">
                            <TextInput v-model="profileForm.linkedin_url" type="url" placeholder="https://www.linkedin.com/in/…" />
                        </FormField>
                        <FormField label="Website" :error="profileForm.errors.website_url">
                            <TextInput v-model="profileForm.website_url" type="url" placeholder="https://" />
                        </FormField>
                        <div class="sm:col-span-2">
                            <FormField label="About me" :error="profileForm.errors.bio">
                                <TextArea v-model="profileForm.bio" maxlength="2000" />
                            </FormField>
                        </div>
                    </div>
                </CardPanel>

                <CardPanel title="How I'd like to help" description="Shown on your profile so students and the institute know what to ask you about.">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <CheckboxInput v-for="(label, key) in interestOptions" :key="key" v-model="profileForm.interests" :value="String(key)" :label="label" />
                    </div>
                </CardPanel>

                <CardPanel title="Privacy" description="Choose who can see each detail. Your name, programme and batch are visible to everyone who can use the directory.">
                    <AlertBox tone="info" class="mb-5">"Connections only" will take effect when connections launch; until then it behaves like "Only me".</AlertBox>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <FormField v-for="(label, field) in privacyLabels" :key="field" :label="label" :error="profileForm.errors[`visibility.${field}` as keyof typeof profileForm.errors]">
                            <SelectInput v-model="profileForm.visibility[field]" :options="visibilityOptions" />
                        </FormField>
                    </div>
                </CardPanel>

                <div class="flex justify-end">
                    <AppButton type="submit" :loading="profileForm.processing">Save profile</AppButton>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
