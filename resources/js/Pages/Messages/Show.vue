<script setup lang="ts">
import { Paperclip } from 'lucide-vue-next';
import { ask } from '@/lib/confirm';
import AutoBreadcrumbs from '@/Components/AutoBreadcrumbs.vue';
import AlertBox from '@/Components/AlertBox.vue';
import AppButton from '@/Components/AppButton.vue';
import AvatarImage from '@/Components/AvatarImage.vue';
import LinkifiedText from '@/Components/LinkifiedText.vue';
import ReportButton from '@/Components/ReportButton.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router, useForm, usePoll } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

interface Msg {
    id: number;
    mine: boolean;
    body: string | null;
    removed: boolean;
    attachment: { url: string; thumb: string | null; name: string; is_image: boolean } | null;
    at: string;
}

const props = defineProps<{
    conversation: { id: number; status: string; is_requester: boolean; other: { name: string; photo_url: string | null; profile_id: number | null } | null };
    messages: Msg[];
    reportReasons: Record<string, string>;
}>();

// New messages arrive by polling just this page's message list.
usePoll(8000, { only: ['messages', 'conversation', 'counts'] });

const form = useForm<{ body: string; attachment: File | null }>({ body: '', attachment: null });
const fileInput = ref<HTMLInputElement>();
const thread = ref<HTMLElement>();

const waitingForAccept = computed(() => props.conversation.status === 'request' && props.conversation.is_requester && props.messages.some((m) => m.mine));
const canSend = computed(() => props.conversation.status !== 'declined' && !waitingForAccept.value);
const incomingRequest = computed(() => props.conversation.status === 'request' && !props.conversation.is_requester);

function send() {
    form.post(route('messages.store', props.conversation.id), {
        forceFormData: !!form.attachment,
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            if (fileInput.value) fileInput.value.value = '';
        },
    });
}
function onKey(e: KeyboardEvent) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        if (form.body.trim() || form.attachment) send();
    }
}
const respond = (decision: 'accept' | 'decline') => router.post(route('messages.respond', props.conversation.id), { decision }, { preserveScroll: true });
const remove = (m: Msg) => ask('Delete this message?').then((ok) => ok && router.delete(route('messages.destroy', m.id), { preserveScroll: true }));

const scrollDown = async () => {
    await nextTick();
    thread.value?.scrollTo({ top: thread.value.scrollHeight });
};
onMounted(scrollDown);
watch(() => props.messages.length, scrollDown);
</script>

<template>
    <AppLayout :title="conversation.other?.name ?? 'Conversation'">
        <AutoBreadcrumbs :title="conversation.other?.name ?? 'Conversation'" class="mb-4" />
        <div class="card mx-auto flex h-[calc(100vh-10rem)] max-w-3xl flex-col">
            <header class="flex items-center gap-3 border-b border-line-soft px-5 py-3">
                <Link :href="route('messages.index')" class="text-sm text-muted hover:text-ink" aria-label="Back to messages">←</Link>
                <AvatarImage v-if="conversation.other" :name="conversation.other.name" :src="conversation.other.photo_url" size="sm" />
                <Link v-if="conversation.other?.profile_id" :href="route('alumni.show', conversation.other.profile_id)" class="font-semibold text-ink hover:underline">{{ conversation.other.name }}</Link>
                <span v-else class="font-semibold text-ink">{{ conversation.other?.name ?? 'Former member' }}</span>
            </header>

            <div ref="thread" class="flex-1 space-y-3 overflow-y-auto px-5 py-4" aria-live="polite">
                <div v-for="m in messages" :key="m.id" :class="['group flex', m.mine ? 'justify-end' : 'justify-start']">
                    <div :class="['max-w-[80%] rounded-2xl px-4 py-2 text-sm', m.mine ? 'bg-brand-600 text-white shadow-sm' : 'bg-surface-sunken text-ink']">
                        <p v-if="m.removed" class="italic opacity-70">This message was removed by a moderator.</p>
                        <p v-else-if="m.body" :class="m.mine ? '[&_a]:text-white [&_a]:underline' : ''"><LinkifiedText :text="m.body" /></p>
                        <a v-if="m.attachment?.is_image" :href="m.attachment.url" target="_blank" rel="noopener" class="mt-2 block">
                            <img :src="m.attachment.thumb ?? m.attachment.url" :alt="m.attachment.name" class="max-h-48 rounded-lg" loading="lazy" />
                        </a>
                        <a v-else-if="m.attachment" :href="m.attachment.url" :class="['mt-2 inline-flex items-center gap-1.5 underline', m.mine ? 'text-white' : 'text-brand-700']"><Paperclip :size="14" aria-hidden="true" />{{ m.attachment.name }}</a>
                        <p :class="['mt-1 text-[11px]', m.mine ? 'text-brand-200' : 'text-muted']">
                            {{ m.at }}
                            <button v-if="m.mine && !m.removed" type="button" class="ml-2 opacity-0 group-hover:opacity-100 focus:opacity-100" @click="remove(m)">Delete</button>
                            <span v-if="!m.mine && !m.removed" class="ml-2 opacity-0 group-hover:opacity-100 focus-within:opacity-100"><ReportButton type="message" :id="m.id" :reasons="reportReasons" /></span>
                        </p>
                    </div>
                </div>
            </div>

            <footer class="border-t border-line-soft p-4">
                <AlertBox v-if="incomingRequest" tone="info" class="mb-3">
                    {{ conversation.other?.name }} isn’t one of your connections. Reply or accept to continue the conversation.
                    <div class="mt-2 flex gap-2">
                        <AppButton size="sm" @click="respond('accept')">Accept</AppButton>
                        <AppButton size="sm" variant="secondary" @click="respond('decline')">Decline</AppButton>
                    </div>
                </AlertBox>
                <p v-if="waitingForAccept" class="text-sm text-muted">Request sent. You can send more once {{ conversation.other?.name }} accepts.</p>
                <p v-else-if="conversation.status === 'declined'" class="text-sm text-muted">This conversation is closed.</p>
                <form v-if="canSend" class="flex items-end gap-2" @submit.prevent="send">
                    <label class="cursor-pointer rounded-lg p-2 text-muted hover:bg-surface-sunken" title="Attach an image or PDF">
                        <Paperclip :size="18" aria-hidden="true" /><span class="sr-only">Attach a file</span>
                        <input ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" class="sr-only" @change="form.attachment = ($event.target as HTMLInputElement).files?.[0] ?? null" />
                    </label>
                    <div class="flex-1">
                        <label for="message-body" class="sr-only">Message</label>
                        <textarea
                            id="message-body"
                            v-model="form.body"
                            rows="1"
                            maxlength="4000"
                            placeholder="Write a message… (Enter to send)"
                            class="block max-h-40 w-full resize-y rounded-lg border-0 text-sm ring-1 ring-line-strong ring-inset focus:ring-2 focus:ring-brand-600"
                            @keydown="onKey"
                        />
                        <p v-if="form.attachment" class="mt-1 text-xs text-muted">Attached: {{ form.attachment.name }}</p>
                        <p v-if="form.errors.body || form.errors.attachment" class="mt-1 text-xs text-red-600">{{ form.errors.body ?? form.errors.attachment }}</p>
                    </div>
                    <AppButton type="submit" :loading="form.processing" :disabled="!form.body.trim() && !form.attachment">Send</AppButton>
                </form>
            </footer>
        </div>
    </AppLayout>
</template>
