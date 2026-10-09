<script setup lang="ts">
import AlertBox from '@/Components/AlertBox.vue';
import AppButton from '@/Components/AppButton.vue';
import OtpInput from '@/Components/OtpInput.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { MailCheck, Timer } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps<{ maskedEmail: string; expiresIn: number; resendIn: number; length: number; status?: string | null }>();

const form = useForm({ code: '' });
const input = ref<InstanceType<typeof OtpInput>>();
// Boxes are disabled while posting, so focus returns once the request has finished.
const refocus = () => nextTick(() => input.value?.focus());
const resendForm = useForm<{ code?: string }>({});

const tick = () => Math.floor(Date.now() / 1000);
const now = ref(tick());
const loadedAt = ref(tick());
watch(() => [props.expiresIn, props.resendIn], () => (loadedAt.value = now.value = tick()));
let timer: number | undefined;
onMounted(() => (timer = window.setInterval(() => (now.value = tick()), 1000)));
onBeforeUnmount(() => window.clearInterval(timer));

const elapsed = computed(() => now.value - loadedAt.value);
const expiresIn = computed(() => Math.max(0, props.expiresIn - elapsed.value));
const resendIn = computed(() => Math.max(0, props.resendIn - elapsed.value));
const expired = computed(() => expiresIn.value === 0);
const clock = (s: number) => `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;

const submit = () => {
    if (form.code.length !== props.length || form.processing) return;
    form.post(route('login.otp.verify'), { onError: () => form.reset('code'), onFinish: refocus });
};
const resend = () => resendForm.post(route('login.otp.resend'), { onSuccess: () => form.reset('code'), onFinish: refocus });
</script>

<template>
    <GuestLayout title="Enter your OTP" description="We’ve sent a one-time password to your registered email address.">
        <AlertBox v-if="status" tone="success" class="mb-5">{{ status }}</AlertBox>

        <div class="mb-6 flex items-center gap-3 rounded-2xl bg-surface-muted p-3 ring-1 ring-line">
            <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-600"><MailCheck :size="20" aria-hidden="true" /></span>
            <p class="text-sm text-ink-soft">
                Sent to <strong class="font-semibold text-ink">{{ maskedEmail }}</strong>
            </p>
        </div>

        <form class="space-y-5" novalidate @submit.prevent="submit">
            <OtpInput ref="input" v-model="form.code" :length="length" :invalid="!!(form.errors.code || resendForm.errors.code)" :disabled="form.processing || expired" @complete="submit" />
            <p v-if="form.errors.code || resendForm.errors.code" class="text-center text-sm text-red-600" role="alert">{{ form.errors.code || resendForm.errors.code }}</p>

            <p class="flex items-center justify-center gap-1.5 text-sm" :class="expired ? 'text-red-600' : 'text-muted'" aria-live="polite">
                <Timer :size="15" aria-hidden="true" />
                <template v-if="!expired">Code expires in <span class="font-semibold text-ink tabular-nums">{{ clock(expiresIn) }}</span></template>
                <template v-else>This OTP has expired. Please request a new one.</template>
            </p>

            <AppButton type="submit" class="w-full" :loading="form.processing" :disabled="form.code.length !== length || expired">Verify and sign in</AppButton>
        </form>

        <div class="mt-5 text-center text-sm text-muted">
            Didn’t get it? Check your spam folder, or
            <button v-if="resendIn === 0" type="button" class="font-medium text-brand-700 hover:text-brand-900 disabled:opacity-50" :disabled="resendForm.processing" @click="resend">resend OTP</button>
            <span v-else>resend in <span class="font-semibold text-ink tabular-nums">{{ resendIn }}s</span></span>
        </div>

        <template #footer>
            <Link :href="route('login')" class="font-medium text-brand-700 hover:text-brand-900">Use a different email or sign in with password</Link>
        </template>
    </GuestLayout>
</template>
