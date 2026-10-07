<script setup lang="ts">
import AutoBreadcrumbs from '@/Components/AutoBreadcrumbs.vue';
import AppButton from '@/Components/AppButton.vue';
import CardPanel from '@/Components/CardPanel.vue';
import FormField from '@/Components/FormField.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref } from 'vue';

const props = defineProps<{
    event: { slug: string; title: string; starts_at: string };
    code: string | null;
    checkedIn: number;
    expected: number;
    recent: { name: string; guests: number; at: string }[];
}>();

const page = usePage();
const result = computed(() => page.props.flash);
const form = useForm({ code: props.code ?? '' });
const input = ref<InstanceType<typeof TextInput>>();

// Staff arriving from a scanned ticket confirm with one tap; a USB/Bluetooth
// scanner types the code and presses Enter into the focused field.
const submit = () => form.post(route('admin.events.check-in.store', props.event.slug), { preserveScroll: true, onSuccess: () => form.reset() });

onMounted(async () => {
    await nextTick();
    (input.value?.$el as HTMLInputElement | undefined)?.focus();
});
</script>

<template>
    <AppLayout title="Check-in">
        <AutoBreadcrumbs title="Check-in" class="mb-4" />
        <div class="mx-auto max-w-xl space-y-6">
            <div>
                <AppButton variant="ghost" class="-ml-3" :href="route('admin.events.show', event.slug)">← {{ event.title }}</AppButton>
                <h1 class="mt-2 text-2xl font-semibold text-ink">Check-in desk</h1>
                <p class="text-sm text-muted">{{ event.starts_at }} · <span class="font-medium tabular-nums">{{ checkedIn }} / {{ expected }}</span> checked in</p>
            </div>

            <div
                v-if="result.success || result.error"
                :class="['rounded-xl p-6 text-center text-lg font-semibold', result.success ? 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200' : 'bg-red-50 text-red-800 ring-1 ring-red-200']"
                role="status"
            >
                {{ result.success ?? result.error }}
            </div>

            <CardPanel>
                <form class="space-y-4" @submit.prevent="submit">
                    <FormField label="Ticket code" :error="form.errors.code" hint="Scan the attendee’s QR code, or paste the code.">
                        <TextInput ref="input" v-model="form.code" autocomplete="off" required />
                    </FormField>
                    <AppButton type="submit" class="w-full" :loading="form.processing">{{ code ? 'Confirm check-in' : 'Check in' }}</AppButton>
                </form>
            </CardPanel>

            <CardPanel v-if="recent.length" title="Recently checked in">
                <ul class="divide-y divide-line-soft text-sm">
                    <li v-for="(r, i) in recent" :key="i" class="flex justify-between py-2">
                        <span>{{ r.name }}<span v-if="r.guests" class="text-muted"> + {{ r.guests }}</span></span>
                        <span class="text-muted">{{ r.at }}</span>
                    </li>
                </ul>
            </CardPanel>
        </div>
    </AppLayout>
</template>
