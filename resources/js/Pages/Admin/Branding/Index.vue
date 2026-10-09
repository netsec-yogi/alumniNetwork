<script setup lang="ts">
import AppButton from '@/Components/AppButton.vue';
import AppLogo from '@/Components/AppLogo.vue';
import CardPanel from '@/Components/CardPanel.vue';
import CheckboxInput from '@/Components/CheckboxInput.vue';
import FormField from '@/Components/FormField.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PublishPanel, { type PublishStatus } from '@/Components/PublishPanel.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ask } from '@/lib/confirm';
import type { Branding } from '@/types';
import { router, useForm } from '@inertiajs/vue3';
import { Check, Eye, ImagePlus, MonitorSmartphone, Palette, RefreshCw, Save, Send, Trash2, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, reactive, watch } from 'vue';

interface Slot {
    key: 'header' | 'mobile' | 'footer' | 'login' | 'favicon';
    label: string;
    hint: string;
    file: { url: string; name: string; width: number | null; height: number | null; kb: number; svg: boolean } | null;
}

const props = defineProps<{
    values: { name: string; tagline: string; show_name: boolean };
    slots: Slot[];
    preview: Branding;
    status: PublishStatus;
    history: { id: number; at: string; by: string | null }[];
    maxKb: number;
}>();

const opts = { preserveScroll: true };
const form = useForm({ ...props.values });
watch(
    () => props.values,
    (v) => {
        form.defaults({ ...v });
        form.reset();
    },
);
const saveText = (then?: () => void) =>
    form.put(route('admin.branding.update'), {
        ...opts,
        onSuccess: () => then?.(),
    });

// Choosing a file shows it locally (here and in the previews) before anything is uploaded.
const pending = reactive<Partial<Record<Slot['key'], { file: File; url: string }>>>({});
const errors = reactive<Partial<Record<Slot['key'], string>>>({});
const busy = reactive<Partial<Record<Slot['key'], boolean>>>({});
function choose(slot: Slot['key'], e: Event) {
    const input = e.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';
    if (!file) return;
    discard(slot);
    errors[slot] = undefined;
    if (file.size > props.maxKb * 1024) {
        errors[slot] = `That file is larger than ${Math.round(props.maxKb / 1024)} MB.`;
        return;
    }
    pending[slot] = { file, url: URL.createObjectURL(file) };
}
function discard(slot: Slot['key']) {
    if (pending[slot]) URL.revokeObjectURL(pending[slot]!.url);
    delete pending[slot];
}
function upload(slot: Slot['key']) {
    const p = pending[slot];
    if (!p) return;
    busy[slot] = true;
    router.post(route('admin.branding.logo', { slot }), { logo: p.file }, {
        ...opts,
        forceFormData: true,
        onSuccess: () => discard(slot),
        onError: (e) => (errors[slot] = e.logo ?? Object.values(e)[0]),
        onFinish: () => (busy[slot] = false),
    });
}
const remove = (s: Slot) =>
    ask(`Remove the ${s.label.toLowerCase()} from the draft? ${s.key === 'header' ? 'The built-in logo will be used' : 'The header logo (or the built-in one) will be used'} once you publish.`).then(
        (ok) => ok && router.delete(route('admin.branding.logo.destroy', { slot: s.key }), opts),
    );
onBeforeUnmount(() => Object.keys(pending).forEach((k) => discard(k as Slot['key'])));

// What the previews show: the saved draft, with unsaved name edits and chosen files applied.
const live = computed<Branding>(() => {
    const own = Object.fromEntries(props.slots.map((s) => [s.key, pending[s.key]?.url ?? s.file?.url ?? null])) as Record<Slot['key'], string | null>;
    const header = own.header;
    return {
        name: form.name || props.preview.name,
        tagline: form.tagline,
        show_name: form.show_name,
        logos: { header, mobile: own.mobile ?? header, footer: own.footer ?? header, login: own.login ?? header },
        favicon: own.favicon,
    };
});

const hasPendingFiles = computed(() => Object.keys(pending).length > 0);
function previewPage() {
    const tab = window.open('about:blank', '_blank');
    const go = () => tab && (tab.location.href = route('admin.landing.preview'));
    if (form.isDirty) saveText(go);
    else go();
}
const publish = () =>
    ask(`Publish this branding? The new logos and name appear across the portal straight away.${hasPendingFiles.value ? ' Files you chose but didn’t upload are not included.' : ''}`, { confirmLabel: 'Publish' }).then((ok) => {
        if (!ok) return;
        const send = () => router.post(route('admin.branding.publish'), {}, opts);
        if (form.isDirty) saveText(send);
        else send();
    });
</script>

<template>
    <AppLayout title="Branding">
        <PageHeader title="Branding" description="The portal’s name, logos and favicon. Changes go into a draft; preview them, then publish to update every page.">
            <AppButton variant="secondary" :icon="Eye" @click="previewPage">Preview landing page</AppButton>
            <AppButton :icon="Send" :disabled="form.processing || (!form.isDirty && !status.has_changes)" @click="publish">Publish</AppButton>
        </PageHeader>

        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <div class="space-y-6">
                <CardPanel title="Name" description="Shown beside the logo and used as its text alternative." :icon="Palette">
                    <form class="grid gap-5 sm:grid-cols-2" novalidate @submit.prevent="saveText()">
                        <FormField label="Portal name" :error="form.errors.name" required>
                            <TextInput v-model="form.name" maxlength="60" />
                        </FormField>
                        <FormField label="Tagline" :error="form.errors.tagline" hint="Optional, under the name.">
                            <TextInput v-model="form.tagline" maxlength="80" />
                        </FormField>
                        <CheckboxInput v-model="form.show_name" class="sm:col-span-2" label="Show the name next to custom logos" description="Turn this off if your logo already contains the name." />
                        <div class="sm:col-span-2">
                            <AppButton type="submit" variant="secondary" :icon="Save" :loading="form.processing" :disabled="!form.isDirty">Save draft</AppButton>
                        </div>
                    </form>
                </CardPanel>

                <CardPanel title="Logos" :description="`PNG, JPG, WebP or SVG, up to ${Math.round(maxKb / 1024)} MB (SVG up to 200 KB). Images are checked, cleaned and optimised on upload. A place without its own logo uses the header logo.`" :icon="ImagePlus">
                    <ul class="grid gap-4 sm:grid-cols-2">
                        <li v-for="s in slots" :key="s.key" class="rounded-2xl p-4 ring-1 ring-line">
                            <p class="font-semibold text-ink">{{ s.label }}</p>
                            <p class="text-xs text-muted">{{ s.hint }}</p>

                            <!-- Light and dark swatches, so contrast problems show before publishing. -->
                            <div class="mt-3 grid grid-cols-2 overflow-hidden rounded-xl ring-1 ring-line">
                                <div v-for="bg in ['bg-surface', 'bg-deep-950']" :key="bg" :class="['grid h-24 place-items-center p-3', bg]">
                                    <img v-if="pending[s.key] || s.file" :src="pending[s.key]?.url ?? s.file!.url" :alt="`${s.label} preview`" :class="s.key === 'favicon' ? 'size-8 object-contain' : 'max-h-14 max-w-full object-contain'" />
                                    <span v-else :class="['text-xs font-semibold', bg === 'bg-surface' ? 'text-subtle' : 'text-white/50']">{{ s.key === 'header' || s.key === 'favicon' ? 'Built-in' : 'Uses header logo' }}</span>
                                </div>
                            </div>

                            <template v-if="pending[s.key]">
                                <p class="mt-2 truncate text-xs font-semibold text-amber-700 dark:text-amber-300">Not uploaded yet: {{ pending[s.key]!.file.name }}</p>
                                <div class="mt-2 flex gap-2">
                                    <AppButton size="sm" :icon="Check" :loading="busy[s.key]" @click="upload(s.key)">Use this {{ s.key === 'favicon' ? 'icon' : 'logo' }}</AppButton>
                                    <AppButton size="sm" variant="ghost" :icon="X" @click="discard(s.key)">Cancel</AppButton>
                                </div>
                            </template>
                            <template v-else>
                                <p v-if="s.file" class="mt-2 truncate text-xs text-muted">
                                    {{ s.file.name }} · {{ s.file.svg ? 'SVG' : `${s.file.width}×${s.file.height}` }} · {{ s.file.kb }} KB
                                </p>
                                <div class="mt-2 flex gap-2">
                                    <label class="press inline-flex h-8 cursor-pointer items-center gap-1.5 rounded-full bg-surface-sunken px-3 text-sm font-semibold text-ink focus-within:outline-2 focus-within:outline-brand-500 hover:bg-line">
                                        <component :is="s.file ? RefreshCw : ImagePlus" :size="15" aria-hidden="true" />{{ s.file ? 'Replace' : 'Upload' }}
                                        <input type="file" accept="image/png,image/jpeg,image/webp,image/svg+xml,.svg" class="sr-only" @change="choose(s.key, $event)" />
                                    </label>
                                    <AppButton v-if="s.file" size="sm" variant="danger-ghost" :icon="Trash2" @click="remove(s)">Remove</AppButton>
                                </div>
                            </template>
                            <p v-if="errors[s.key]" class="mt-2 text-[13px] text-red-600" role="alert">{{ errors[s.key] }}</p>
                        </li>
                    </ul>
                </CardPanel>

                <CardPanel title="Preview" description="The draft in the portal’s real places, including files you’ve chosen but not uploaded." :icon="MonitorSmartphone">
                    <div class="space-y-4">
                        <figure>
                            <figcaption class="mb-1.5 text-xs font-semibold text-muted">Landing page header</figcaption>
                            <div class="flex h-16 items-center justify-between rounded-xl bg-deep-950 px-4 text-white">
                                <AppLogo :branding="live" inverse />
                                <span class="rounded-full bg-white px-4 py-1.5 text-xs font-bold text-brand-700">Join</span>
                            </div>
                        </figure>
                        <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_14rem]">
                            <figure>
                                <figcaption class="mb-1.5 text-xs font-semibold text-muted">App header (desktop)</figcaption>
                                <div class="flex h-16 items-center rounded-xl bg-topbar px-4 ring-1 ring-line"><AppLogo :branding="{ ...live, logos: { ...live.logos, mobile: live.logos.header } }" /></div>
                            </figure>
                            <figure>
                                <figcaption class="mb-1.5 text-xs font-semibold text-muted">Phone header</figcaption>
                                <div class="flex h-14 items-center rounded-xl bg-topbar px-3 ring-1 ring-line"><AppLogo :branding="{ ...live, logos: { ...live.logos, header: live.logos.mobile } }" /></div>
                            </figure>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <figure>
                                <figcaption class="mb-1.5 text-xs font-semibold text-muted">Sign-in page</figcaption>
                                <div class="rounded-xl bg-gradient-to-br from-deep-800 to-deep-950 p-5"><AppLogo :branding="live" place="login" inverse /></div>
                            </figure>
                            <figure>
                                <figcaption class="mb-1.5 text-xs font-semibold text-muted">Footer</figcaption>
                                <div class="rounded-xl bg-canvas p-5 ring-1 ring-line"><AppLogo :branding="live" place="footer" /></div>
                            </figure>
                        </div>
                        <figure>
                            <figcaption class="mb-1.5 text-xs font-semibold text-muted">Browser tab</figcaption>
                            <div class="inline-flex items-center gap-2 rounded-t-xl bg-surface-sunken px-3 py-2 text-xs text-ink ring-1 ring-line">
                                <img :src="live.favicon ?? '/favicon.ico'" alt="" class="size-4 object-contain" />
                                <span class="max-w-48 truncate">{{ live.name }}</span>
                            </div>
                        </figure>
                    </div>
                </CardPanel>
            </div>

            <aside class="lg:sticky lg:top-24">
                <PublishPanel :status="status" :history="history" what="branding" unpublish-route="admin.branding.unpublish" restore-route="admin.branding.restore" />
            </aside>
        </div>
    </AppLayout>
</template>
