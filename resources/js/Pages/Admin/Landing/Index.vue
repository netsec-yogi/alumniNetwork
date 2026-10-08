<script setup lang="ts">
import AppButton from '@/Components/AppButton.vue';
import CardPanel from '@/Components/CardPanel.vue';
import CheckboxInput from '@/Components/CheckboxInput.vue';
import PageHeader from '@/Components/PageHeader.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, BarChart3, Eye, EyeOff, LayoutTemplate } from 'lucide-vue-next';

const props = defineProps<{
    settings: { sections: { key: string; label: string; visible: boolean }[]; stats: Record<string, boolean> };
    statLabels: Record<string, string>;
    content: Record<string, number>;
}>();

const form = useForm({ sections: props.settings.sections.map((s) => ({ ...s })), stats: { ...props.settings.stats } });

function move(i: number, d: number) {
    const j = i + d;
    if (j < 0 || j >= form.sections.length) return;
    const list = [...form.sections];
    [list[i], list[j]] = [list[j]!, list[i]!];
    form.sections = list;
}
const save = () => form.put(route('admin.landing.update'), { preserveScroll: true });

// How much content backs each section (sections with nothing show an empty state).
const sourceCount = (key: string) => props.content[key];
const manageLink: Record<string, string | undefined> = {
    events: route().has('admin.events.index') ? route('admin.events.index') : undefined,
    news: route('admin.stories.index'),
    stories: route('admin.stories.index'),
    distinguished: route('admin.distinguished.index'),
    gallery: route('admin.gallery.index'),
    chapters: route().has('admin.communities.index') ? route('admin.communities.index') : undefined,
};
</script>

<template>
    <AppLayout title="Landing page">
        <PageHeader title="Landing page" description="What visitors see at the public home page. Only published, public content is ever shown there.">
            <AppButton variant="secondary" external :href="route('home')" target="_blank" :icon="Eye">Preview</AppButton>
            <AppButton :loading="form.processing" @click="save">Save changes</AppButton>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
            <CardPanel title="Sections" description="Order and visibility. The hero is always first." :icon="LayoutTemplate" flush>
                <ol class="divide-y divide-line-soft">
                    <li v-for="(s, i) in form.sections" :key="s.key" :class="['flex items-center gap-3 px-5 py-3', !s.visible && 'opacity-60']">
                        <span class="w-6 text-sm font-bold text-subtle tabular-nums">{{ i + 1 }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-ink">{{ s.label }}</p>
                            <p v-if="sourceCount(s.key) !== undefined" class="text-xs text-muted">
                                {{ sourceCount(s.key) }} item{{ sourceCount(s.key) === 1 ? '' : 's' }} available
                                <template v-if="sourceCount(s.key) === 0"> — visitors will see an empty state</template>
                                <template v-if="manageLink[s.key]"> · <a :href="manageLink[s.key]" class="font-semibold text-brand-600 hover:underline">manage</a></template>
                            </p>
                        </div>
                        <AppButton size="sm" variant="ghost" :icon="ArrowUp" :aria-label="`Move ${s.label} up`" :disabled="i === 0" @click="move(i, -1)" />
                        <AppButton size="sm" variant="ghost" :icon="ArrowDown" :aria-label="`Move ${s.label} down`" :disabled="i === form.sections.length - 1" @click="move(i, 1)" />
                        <AppButton size="sm" :variant="s.visible ? 'secondary' : 'outline'" :icon="s.visible ? Eye : EyeOff" :aria-pressed="s.visible" @click="s.visible = !s.visible">{{ s.visible ? 'Shown' : 'Hidden' }}</AppButton>
                    </li>
                </ol>
            </CardPanel>

            <CardPanel title="Statistics" description="Live counts from the database. A statistic that is zero is never shown." :icon="BarChart3">
                <div class="space-y-3">
                    <CheckboxInput v-for="(label, key) in statLabels" :key="key" v-model="form.stats[key]" :label="label" />
                </div>
            </CardPanel>
        </div>
    </AppLayout>
</template>
