<script setup lang="ts">
import AppButton from '@/Components/AppButton.vue';
import CardPanel from '@/Components/CardPanel.vue';
import FormField from '@/Components/FormField.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PublishPanel, { type PublishStatus } from '@/Components/PublishPanel.vue';
import RichTextInput from '@/Components/RichTextInput.vue';
import TextArea from '@/Components/TextArea.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ask } from '@/lib/confirm';
import { router, useForm } from '@inertiajs/vue3';
import { Eye, RotateCcw, Save, Send, Type } from 'lucide-vue-next';
import { computed, watch } from 'vue';

interface Field {
    key: string;
    section: string;
    label: string;
    type: 'text' | 'textarea' | 'rich';
    default: string;
    required: boolean;
    max: number;
    hint: string | null;
}

const props = defineProps<{ sections: Record<string, string>; fields: Field[]; values: Record<string, string>; status: PublishStatus; history: { id: number; at: string; by: string | null }[] }>();

const form = useForm({ values: { ...props.values } });
// A restored version replaces the draft in the editor; other reloads keep unsaved edits.
watch(
    () => props.values,
    (next, prev) => {
        if (JSON.stringify(next) === JSON.stringify(prev)) return;
        form.defaults({ values: { ...next } });
        form.reset();
    },
);
const grouped = computed(() => Object.entries(props.sections).map(([key, label]) => ({ key, label, fields: props.fields.filter((f) => f.section === key) })));
const opts = { preserveScroll: true };

const saveDraft = (then?: () => void) =>
    form.put(route('admin.landing.content.update'), {
        ...opts,
        onSuccess: () => {
            form.defaults();
            then?.();
        },
    });

// Preview shows the saved draft, so unsaved edits are saved first. The tab
// opens straight away (inside the click) so pop-up blockers allow it.
function preview() {
    const tab = window.open('about:blank', '_blank');
    const go = () => tab && (tab.location.href = route('admin.landing.preview'));
    if (form.isDirty) saveDraft(go);
    else go();
}

const publish = () =>
    ask('Publish this text? Visitors see it on the landing page straight away.', { confirmLabel: 'Publish' }).then((ok) => {
        if (!ok) return;
        const send = () => router.post(route('admin.landing.content.publish'), {}, opts);
        if (form.isDirty) saveDraft(send);
        else send();
    });

const errorFor = (key: string) => (form.errors as Record<string, string>)[`values.${key}`];
const hintFor = (f: Field) => [f.hint, f.required ? null : 'Optional — leave blank to hide it.'].filter(Boolean).join(' ') || undefined;
</script>

<template>
    <AppLayout title="Landing page text">
        <PageHeader title="Landing page text" description="Titles, descriptions and button text on the public home page. Save a draft, preview it on the real page, then publish.">
            <AppButton variant="secondary" :icon="Eye" @click="preview">Preview</AppButton>
            <AppButton variant="secondary" :icon="Save" :loading="form.processing" :disabled="!form.isDirty" @click="saveDraft()">Save draft</AppButton>
            <AppButton :icon="Send" :disabled="form.processing || (!form.isDirty && !status.has_changes)" @click="publish">Publish</AppButton>
        </PageHeader>

        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <form class="space-y-6" novalidate @submit.prevent="saveDraft()">
                <CardPanel v-for="g in grouped" :id="`section-${g.key}`" :key="g.key" :title="g.label" :icon="Type" class="scroll-mt-24">
                    <div class="space-y-5">
                        <FormField v-for="f in g.fields" :key="f.key" :label="f.label" :error="errorFor(f.key)" :hint="hintFor(f)" :required="f.required">
                            <TextInput v-if="f.type === 'text'" v-model="form.values[f.key]" :maxlength="f.max" :placeholder="f.default" />
                            <TextArea v-else-if="f.type === 'textarea'" v-model="form.values[f.key]" :maxlength="f.max" :placeholder="f.default" rows="3" />
                            <RichTextInput v-else v-model="form.values[f.key]" :maxlength="f.max" :placeholder="f.default" />
                            <div class="mt-1 flex items-center justify-between gap-3 text-[11px] text-subtle">
                                <code>{{ f.key }}</code>
                                <span class="flex items-center gap-2">
                                    <span class="tabular-nums">{{ (form.values[f.key] ?? '').length }}/{{ f.max }}</span>
                                    <button v-if="f.default && form.values[f.key] !== f.default" type="button" class="inline-flex items-center gap-1 font-semibold text-brand-600 hover:underline dark:text-brand-300" @click="form.values[f.key] = f.default">
                                        <RotateCcw :size="11" aria-hidden="true" />Reset to default
                                    </button>
                                </span>
                            </div>
                        </FormField>
                    </div>
                </CardPanel>
                <div class="flex justify-end gap-2">
                    <AppButton type="submit" variant="secondary" :icon="Save" :loading="form.processing" :disabled="!form.isDirty">Save draft</AppButton>
                </div>
            </form>

            <aside class="space-y-6 lg:sticky lg:top-24">
                <PublishPanel :status="status" :history="history" what="landing page text" unpublish-route="admin.landing.content.unpublish" restore-route="admin.landing.content.restore" />
                <CardPanel title="Sections" class="hidden lg:block">
                    <nav class="-mx-2 grid text-sm" aria-label="Jump to section">
                        <a v-for="g in grouped" :key="g.key" :href="`#section-${g.key}`" class="rounded-lg px-2 py-1.5 text-ink-soft hover:bg-surface-sunken hover:text-ink">{{ g.label }}</a>
                    </nav>
                </CardPanel>
            </aside>
        </div>
    </AppLayout>
</template>
