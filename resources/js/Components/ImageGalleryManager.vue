<script setup lang="ts">
import { ask } from '@/lib/confirm';
import { router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, CloudUpload, GripVertical, Images, RefreshCw, Star, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import AppTooltip from './AppTooltip.vue';
import CardPanel from './CardPanel.vue';

/**
 * Admin gallery editor for an event or a news item: upload several images
 * (drag & drop), drag to reorder (or use the arrow buttons), star one as
 * the featured image, replace or delete. Every change is saved at once.
 */
export interface GalleryImage {
    id: number;
    thumb: string;
    full: string;
    is_featured: boolean;
}

const props = defineProps<{ type: 'events' | 'stories'; ownerId: number; images: GalleryImage[]; limitKb: number; title?: string; description?: string }>();

const items = ref<GalleryImage[]>([...props.images]);
watch(
    () => props.images,
    (v) => (items.value = [...v]),
);

const opts = { preserveScroll: true, preserveState: true };

// Upload
const upload = useForm<{ images: File[] }>({ images: [] });
const dragging = ref(false);
const input = ref<HTMLInputElement>();
function send(files: FileList | File[] | null | undefined) {
    const list = [...(files ?? [])].filter((f) => f.type.startsWith('image/')).slice(0, 10);
    if (!list.length) return;
    upload.images = list;
    upload.post(route('admin.media.store', { type: props.type, id: props.ownerId }), { ...opts, forceFormData: true, onFinish: () => upload.reset() });
}

// Reorder (drag & drop, or keyboard-friendly arrow buttons)
const dragIndex = ref<number | null>(null);
function saveOrder() {
    router.put(route('admin.media.reorder', { type: props.type, id: props.ownerId }), { order: items.value.map((i) => i.id) }, opts);
}
function onDrop(target: number) {
    if (dragIndex.value === null || dragIndex.value === target) return;
    const list = [...items.value];
    const [moved] = list.splice(dragIndex.value, 1);
    list.splice(target, 0, moved!);
    items.value = list;
    dragIndex.value = null;
    saveOrder();
}
function move(i: number, d: number) {
    const j = i + d;
    if (j < 0 || j >= items.value.length) return;
    const list = [...items.value];
    [list[i], list[j]] = [list[j]!, list[i]!];
    items.value = list;
    saveOrder();
}

const feature = (img: GalleryImage) => router.post(route('admin.media.feature', { type: props.type, id: props.ownerId, image: img.id }), {}, opts);
const remove = (img: GalleryImage) =>
    ask(`Delete this image?${img.is_featured ? ' It is the featured image — the next one will be featured instead.' : ''}`).then(
        (ok) => ok && router.delete(route('admin.media.destroy', { type: props.type, id: props.ownerId, image: img.id }), opts),
    );
function replace(img: GalleryImage, e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (file) router.post(route('admin.media.replace', { type: props.type, id: props.ownerId, image: img.id }), { image: file }, { ...opts, forceFormData: true });
}
</script>

<template>
    <CardPanel :title="title ?? 'Images'" :description="description ?? `Up to 10 at a time. Each image is optimised to ${limitKb} KB or less. The ★ featured image is used on cards and the landing page.`" :icon="Images">
        <!-- Drop zone -->
        <label
            :class="[
                'flex cursor-pointer flex-col items-center justify-center gap-1.5 rounded-2xl border-2 border-dashed px-4 py-6 text-center transition-colors',
                dragging ? 'border-brand-500 bg-brand-50/60' : 'border-line-strong hover:border-brand-400 hover:bg-surface-muted',
                upload.processing && 'pointer-events-none opacity-60',
            ]"
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="(dragging = false), send($event.dataTransfer?.files)"
        >
            <span class="grid size-11 place-items-center rounded-2xl bg-brand-50 text-brand-600"><CloudUpload :size="22" aria-hidden="true" /></span>
            <span class="text-sm text-ink-soft"><span class="font-semibold text-brand-600">Upload images</span> or drag them here</span>
            <span class="text-xs text-muted">JPG, PNG or WebP · up to 10 at once</span>
            <input ref="input" type="file" multiple accept="image/jpeg,image/png,image/webp" class="sr-only" :disabled="upload.processing" @change="send(($event.target as HTMLInputElement).files)" />
        </label>
        <div v-if="upload.progress" class="mt-3 h-1.5 overflow-hidden rounded-full bg-surface-sunken" role="progressbar" :aria-valuenow="upload.progress.percentage" aria-valuemin="0" aria-valuemax="100" aria-label="Uploading">
            <div class="h-full rounded-full bg-brand-500 transition-[width]" :style="{ width: `${upload.progress.percentage}%` }" />
        </div>
        <ul v-if="Object.keys(upload.errors).length" class="mt-3 space-y-1 text-sm text-red-600" role="alert">
            <li v-for="(msg, key) in upload.errors" :key="key">{{ msg }}</li>
        </ul>

        <!-- Gallery -->
        <p v-if="items.length === 0" class="mt-4 text-sm text-muted">No images yet.</p>
        <template v-else>
            <p class="mt-4 mb-2 flex items-center gap-1.5 text-xs text-muted"><GripVertical :size="14" aria-hidden="true" />Drag to reorder, or use the arrows.</p>
            <ol class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                <li
                    v-for="(img, i) in items"
                    :key="img.id"
                    draggable="true"
                    :class="['group relative overflow-hidden rounded-2xl bg-surface-muted ring-2 transition', img.is_featured ? 'ring-accent-500' : 'ring-transparent', dragIndex === i && 'opacity-40']"
                    @dragstart="dragIndex = i"
                    @dragend="dragIndex = null"
                    @dragover.prevent
                    @drop.prevent="onDrop(i)"
                >
                    <img :src="img.thumb" :alt="`Image ${i + 1}`" class="aspect-square w-full cursor-grab object-cover active:cursor-grabbing" />
                    <span v-if="img.is_featured" class="absolute top-2 left-2 inline-flex items-center gap-1 rounded-full bg-accent-500 px-2 py-0.5 text-[11px] font-bold text-white shadow"><Star :size="11" fill="currentColor" />Featured</span>
                    <span class="absolute top-2 right-2 rounded-full bg-black/50 px-2 py-0.5 text-[11px] font-bold text-white tabular-nums">{{ i + 1 }}</span>
                    <div class="flex items-center justify-between gap-1 bg-surface px-1.5 py-1.5">
                        <span class="flex">
                            <AppTooltip text="Move earlier"><button type="button" class="rounded-full p-1.5 text-muted hover:bg-surface-sunken disabled:opacity-30" :disabled="i === 0" :aria-label="`Move image ${i + 1} earlier`" @click="move(i, -1)"><ArrowLeft :size="15" /></button></AppTooltip>
                            <AppTooltip text="Move later"><button type="button" class="rounded-full p-1.5 text-muted hover:bg-surface-sunken disabled:opacity-30" :disabled="i === items.length - 1" :aria-label="`Move image ${i + 1} later`" @click="move(i, 1)"><ArrowRight :size="15" /></button></AppTooltip>
                        </span>
                        <span class="flex">
                            <AppTooltip :text="img.is_featured ? 'Featured image' : 'Make featured'">
                                <button type="button" :aria-pressed="img.is_featured" :class="['rounded-full p-1.5 hover:bg-surface-sunken', img.is_featured ? 'text-accent-500' : 'text-muted']" :aria-label="`Make image ${i + 1} the featured image`" @click="!img.is_featured && feature(img)">
                                    <Star :size="15" :fill="img.is_featured ? 'currentColor' : 'none'" />
                                </button>
                            </AppTooltip>
                            <AppTooltip text="Replace">
                                <label class="cursor-pointer rounded-full p-1.5 text-muted hover:bg-surface-sunken focus-within:outline-2 focus-within:outline-brand-500" :aria-label="`Replace image ${i + 1}`">
                                    <RefreshCw :size="15" aria-hidden="true" />
                                    <input type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="replace(img, $event)" />
                                </label>
                            </AppTooltip>
                            <AppTooltip text="Delete"><button type="button" class="rounded-full p-1.5 text-muted hover:bg-red-50 hover:text-red-600" :aria-label="`Delete image ${i + 1}`" @click="remove(img)"><Trash2 :size="15" /></button></AppTooltip>
                        </span>
                    </div>
                </li>
            </ol>
        </template>
    </CardPanel>
</template>
