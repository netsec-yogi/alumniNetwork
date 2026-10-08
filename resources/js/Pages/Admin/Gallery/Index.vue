<script setup lang="ts">
import AppButton from '@/Components/AppButton.vue';
import CheckboxInput from '@/Components/CheckboxInput.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FileUpload from '@/Components/FileUpload.vue';
import FormField from '@/Components/FormField.vue';
import ModalDialog from '@/Components/ModalDialog.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PaginationNav from '@/Components/PaginationNav.vue';
import SelectInput from '@/Components/SelectInput.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TabsNav from '@/Components/TabsNav.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ask } from '@/lib/confirm';
import type { Option, Paginated } from '@/types';
import { router, useForm } from '@inertiajs/vue3';
import { Images, Plus, Star } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Item {
    id: number;
    title: string;
    caption: string | null;
    category: string;
    status: 'draft' | 'published' | 'archived';
    is_featured: boolean;
    display_order: number;
    event_id: number | null;
    visible_from: string | null;
    visible_until: string | null;
    thumb: string;
    event: string | null;
}

const props = defineProps<{ items: Paginated<Item>; status: string | null; categories: Option[]; events: Option<number>[] }>();

const tabs = computed(() =>
    [
        { key: 'all', label: 'All', status: null },
        { key: 'published', label: 'Published', status: 'published' },
        { key: 'draft', label: 'Drafts', status: 'draft' },
        { key: 'archived', label: 'Archived', status: 'archived' },
    ].map((t) => ({ key: t.key, label: t.label, href: route('admin.gallery.index', t.status ? { status: t.status } : {}), active: (props.status ?? null) === t.status })),
);
const statusOptions = [
    { value: 'draft', label: 'Draft (not public)' },
    { value: 'published', label: 'Published (public)' },
    { value: 'archived', label: 'Archived (not public)' },
];
const categoryLabel = (v: string) => props.categories.find((c) => c.value === v)?.label ?? v;

const editing = ref<Item | 'new' | null>(null);
const blank = { photo: null as File | null, title: '', caption: '', category: '', event_id: '' as number | '', status: 'draft', is_featured: false, display_order: 0, visible_from: '', visible_until: '' };
const form = useForm({ ...blank });

function open(item: Item | 'new') {
    editing.value = item;
    form.clearErrors();
    if (item === 'new') Object.assign(form, { ...blank });
    else
        Object.assign(form, {
            photo: null,
            title: item.title,
            caption: item.caption ?? '',
            category: item.category,
            event_id: item.event_id ?? '',
            status: item.status,
            is_featured: item.is_featured,
            display_order: item.display_order,
            visible_from: item.visible_from ?? '',
            visible_until: item.visible_until ?? '',
        });
}

function save() {
    const opts = { preserveScroll: true, onSuccess: () => (editing.value = null) };
    form.transform((d) => {
        const out: Record<string, unknown> = Object.fromEntries(Object.entries(d).map(([k, v]) => [k, v === '' ? null : v]));
        if (editing.value !== 'new') delete out.photo;
        return out;
    });
    if (editing.value === 'new') form.post(route('admin.gallery.store'), { ...opts, forceFormData: true });
    else form.put(route('admin.gallery.update', (editing.value as Item).id), opts);
}

const remove = (item: Item) => ask(`Delete “${item.title}”? The photo is removed everywhere.`).then((ok) => ok && router.delete(route('admin.gallery.destroy', item.id), { preserveScroll: true }));
</script>

<template>
    <AppLayout title="Gallery">
        <PageHeader title="Gallery" description="Photos for the public landing page. Only published photos are visible to visitors — and only within their date window.">
            <AppButton :icon="Plus" @click="open('new')">Add photo</AppButton>
        </PageHeader>

        <TabsNav :items="tabs" class="mb-5" />

        <EmptyState v-if="items.data.length === 0" :icon="Images" title="No photos here yet" description="Add photos from alumni meets, reunions and campus events.">
            <AppButton :icon="Plus" @click="open('new')">Add photo</AppButton>
        </EmptyState>
        <ul v-else class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-4">
            <li v-for="g in items.data" :key="g.id" class="card group overflow-hidden">
                <button type="button" class="relative block w-full" :aria-label="`Edit ${g.title}`" @click="open(g)">
                    <img :src="g.thumb" :alt="g.title" loading="lazy" class="aspect-square w-full object-cover transition group-hover:brightness-95" />
                    <span v-if="g.is_featured" class="absolute top-2 left-2 inline-flex items-center gap-1 rounded-full bg-accent-500 px-2 py-0.5 text-[11px] font-bold text-white"><Star :size="11" fill="currentColor" />Featured</span>
                </button>
                <div class="p-3">
                    <p class="truncate text-sm font-bold text-ink">{{ g.title }}</p>
                    <p class="truncate text-xs text-muted">{{ categoryLabel(g.category) }}<template v-if="g.event"> · {{ g.event }}</template></p>
                    <div class="mt-2 flex items-center justify-between gap-2">
                        <StatusBadge :status="g.status" />
                        <span class="flex">
                            <AppButton size="sm" variant="ghost" @click="open(g)">Edit</AppButton>
                            <AppButton size="sm" variant="danger-ghost" @click="remove(g)">Delete</AppButton>
                        </span>
                    </div>
                </div>
            </li>
        </ul>
        <PaginationNav class="mt-6" :links="items.links" :from="items.from" :to="items.to" :total="items.total" />

        <ModalDialog :show="editing !== null" :title="editing === 'new' ? 'Add photo' : 'Edit photo'" size="lg" @close="editing = null">
            <form id="gallery-form" class="grid gap-4 sm:grid-cols-2" @submit.prevent="save">
                <div v-if="editing === 'new'" class="sm:col-span-2">
                    <FormField label="Photo" :error="form.errors.photo" required>
                        <FileUpload v-model="form.photo" accept="image/jpeg,image/png,image/webp" :max-mb="10" hint="JPEG, PNG or WebP, up to 10 MB" :progress="form.progress?.percentage ?? null" />
                    </FormField>
                </div>
                <FormField label="Title" :error="form.errors.title" required><TextInput v-model="form.title" maxlength="150" /></FormField>
                <FormField label="Category" :error="form.errors.category" required><SelectInput v-model="form.category" :options="categories" placeholder="Choose" /></FormField>
                <div class="sm:col-span-2"><FormField label="Caption (optional)" :error="form.errors.caption"><TextInput v-model="form.caption" maxlength="300" /></FormField></div>
                <FormField label="Event (optional)" :error="form.errors.event_id"><SelectInput v-model="form.event_id" :options="events" placeholder="None" /></FormField>
                <FormField label="Status" :error="form.errors.status" required><SelectInput v-model="form.status" :options="statusOptions" /></FormField>
                <FormField label="Visible from (optional)" :error="form.errors.visible_from"><TextInput v-model="form.visible_from" type="date" /></FormField>
                <FormField label="Visible until (optional)" :error="form.errors.visible_until"><TextInput v-model="form.visible_until" type="date" /></FormField>
                <FormField label="Display order" :error="form.errors.display_order" hint="Lower numbers first."><TextInput v-model.number="form.display_order" type="number" min="0" /></FormField>
                <div class="pt-6"><CheckboxInput v-model="form.is_featured" label="Featured" description="Shown first, and in the hero collage." /></div>
            </form>
            <template #footer>
                <AppButton variant="secondary" @click="editing = null">Cancel</AppButton>
                <AppButton type="submit" form="gallery-form" :loading="form.processing">Save</AppButton>
            </template>
        </ModalDialog>
    </AppLayout>
</template>
