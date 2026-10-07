<script setup lang="ts">
import AppButton from '@/Components/AppButton.vue';
import EmptyState from '@/Components/EmptyState.vue';
import LinkifiedText from '@/Components/LinkifiedText.vue';
import PageHeader from '@/Components/PageHeader.vue';
import TextArea from '@/Components/TextArea.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import { nextTick, ref, watch } from 'vue';

const props = defineProps<{ history: { role: 'user' | 'assistant'; content: string }[] }>();

const form = useForm({ message: '' });
const pending = ref('');
const log = ref<HTMLElement | null>(null);

const examples = ['Who from my batch works in Bengaluru?', 'Any alumni events coming up this month?', 'Find me a mentor for product management', 'What’s waiting for me on the portal?'];

function send(text?: string) {
    if (text) form.message = text;
    if (!form.message.trim() || form.processing) return;
    pending.value = form.message;
    form.post(route('assistant.ask'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onFinish: () => (pending.value = ''),
    });
}

const onKey = (e: KeyboardEvent) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        send();
    }
};

const reset = () => router.delete(route('assistant.reset'), { preserveScroll: true });

watch(
    () => [props.history.length, pending.value],
    async () => {
        await nextTick();
        log.value?.scrollTo({ top: log.value.scrollHeight, behavior: 'smooth' });
    },
);
</script>

<template>
    <AppLayout title="Assistant">
        <PageHeader title="Assistant" description="Ask about alumni, events, jobs and mentors. It only sees what you can see on the portal.">
            <AppButton v-if="history.length" variant="ghost" @click="reset">New conversation</AppButton>
        </PageHeader>

        <div class="card mx-auto flex max-w-3xl flex-col">
            <div ref="log" class="max-h-[60vh] min-h-64 space-y-4 overflow-y-auto p-5" aria-live="polite">
                <template v-if="history.length || pending">
                    <div v-for="(m, i) in history" :key="i" :class="m.role === 'user' ? 'flex justify-end' : 'flex justify-start'">
                        <div :class="['max-w-[85%] rounded-2xl px-4 py-2.5 text-sm whitespace-pre-line', m.role === 'user' ? 'bg-deep-700 text-white' : 'bg-surface-sunken text-ink']">
                            <LinkifiedText v-if="m.role === 'assistant'" :text="m.content" />
                            <template v-else>{{ m.content }}</template>
                        </div>
                    </div>
                    <template v-if="pending">
                        <div class="flex justify-end">
                            <div class="max-w-[85%] rounded-2xl bg-deep-700 px-4 py-2.5 text-sm whitespace-pre-line text-white opacity-80">{{ pending }}</div>
                        </div>
                        <p class="text-sm text-muted" role="status">Looking that up…</p>
                    </template>
                </template>
                <div v-else>
                    <EmptyState title="What would you like to know?" description="Try one of these:" />
                    <div class="mt-3 flex flex-wrap justify-center gap-2">
                        <button v-for="ex in examples" :key="ex" type="button" class="rounded-full bg-brand-50 px-3 py-1.5 text-sm text-brand-800 hover:bg-brand-100" @click="send(ex)">{{ ex }}</button>
                    </div>
                </div>
            </div>

            <form class="flex items-end gap-2 border-t border-line p-4" @submit.prevent="send()">
                <label for="assistant-message" class="sr-only">Message</label>
                <TextArea id="assistant-message" v-model="form.message" rows="2" maxlength="1000" class="flex-1" placeholder="Ask a question…" :disabled="form.processing" @keydown="onKey" />
                <AppButton type="submit" :loading="form.processing">Send</AppButton>
            </form>
            <p v-if="form.errors.message" class="px-4 pb-3 text-sm text-red-600">{{ form.errors.message }}</p>
            <p class="px-4 pb-4 text-xs text-muted">AI answers can be wrong — check the linked pages before acting. Conversations aren’t stored after you sign out.</p>
        </div>
    </AppLayout>
</template>
