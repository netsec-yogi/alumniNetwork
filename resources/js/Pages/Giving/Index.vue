<script setup lang="ts">
import AutoBreadcrumbs from '@/Components/AutoBreadcrumbs.vue';
import AlertBox from '@/Components/AlertBox.vue';
import AppButton from '@/Components/AppButton.vue';
import CardPanel from '@/Components/CardPanel.vue';
import CheckboxInput from '@/Components/CheckboxInput.vue';
import FormField from '@/Components/FormField.vue';
import TextArea from '@/Components/TextArea.vue';
import TextInput from '@/Components/TextInput.vue';
import SiteLayout from '@/Layouts/SiteLayout.vue';
import type { Option } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    categories: Option[];
    limits: { min: number; max: number; pan_from: number };
    prefill: { donor_name: string; donor_email: string; donor_phone: string | null } | null;
    stats: { donors: number; raised: number };
    recent: { name: string; category: string; when: string }[];
    testGateway: boolean;
    campaign: { slug: string; title: string; category: string; matching: string | null } | null;
}>();

const presets = [1000, 2500, 5000, 10000, 25000];
const form = useForm({
    category: props.campaign?.category ?? 'scholarship',
    campaign: props.campaign?.slug ?? null,
    amount: 2500 as number | '',
    donor_name: props.prefill?.donor_name ?? '',
    donor_email: props.prefill?.donor_email ?? '',
    donor_phone: props.prefill?.donor_phone ?? '',
    wants_80g: false,
    pan: '',
    address: '',
    is_anonymous: false,
    indian_resident: false,
});
const panNeeded = computed(() => form.wants_80g || Number(form.amount) >= props.limits.pan_from);
const inr = (n: number) => '₹' + n.toLocaleString('en-IN');
const submit = () => form.transform((d) => ({ ...d, pan: d.pan || null, address: d.address || null, donor_phone: d.donor_phone || null })).post(route('giving.store'));
</script>

<template>
    <SiteLayout title="Give">
        <AutoBreadcrumbs title="Give" class="mb-4" />
        <div class="grid gap-8 lg:grid-cols-[1fr_24rem]">
            <section>
                <h1 class="text-3xl font-semibold tracking-tight text-ink">Give back to IIITM</h1>
                <p class="mt-3 max-w-2xl text-muted">Your gift funds scholarships, research, student welfare and the next generation of founders. Every rupee is receipted, and gifts with a PAN are eligible for 80G deduction.</p>
                <dl class="mt-6 flex gap-10">
                    <div><dt class="text-sm text-muted">Raised</dt><dd class="text-2xl font-semibold text-brand-800 tabular-nums">{{ inr(stats.raised) }}</dd></div>
                    <div><dt class="text-sm text-muted">Donors</dt><dd class="text-2xl font-semibold text-brand-800 tabular-nums">{{ stats.donors.toLocaleString('en-IN') }}</dd></div>
                </dl>
                <CardPanel v-if="recent.length" title="Recent supporters" class="mt-8">
                    <ul class="space-y-2 text-sm">
                        <li v-for="(r, i) in recent" :key="i" class="flex justify-between"><span><span class="font-medium">{{ r.name }}</span> gave to {{ r.category }}</span><span class="text-subtle">{{ r.when }}</span></li>
                    </ul>
                </CardPanel>
            </section>

            <CardPanel title="Make a donation">
                <AlertBox v-if="testGateway" tone="warning" class="mb-4">Test mode: no real money is charged.</AlertBox>
                <AlertBox v-if="campaign" tone="info" class="mb-4" :title="`Giving to: ${campaign.title}`">{{ campaign.matching ?? 'Your gift goes to this campaign.' }}</AlertBox>
                <form class="space-y-4" novalidate @submit.prevent="submit">
                    <fieldset v-if="!campaign">
                        <legend class="text-sm font-medium text-ink-soft">Support</legend>
                        <div class="mt-2 grid grid-cols-2 gap-2">
                            <label
                                v-for="c in categories"
                                :key="c.value"
                                :class="['cursor-pointer rounded-lg px-3 py-2 text-sm ring-1', form.category === c.value ? 'bg-brand-50 font-medium text-brand-900 ring-brand-500' : 'ring-line hover:bg-surface-muted']"
                            >
                                <input v-model="form.category" type="radio" :value="c.value" class="sr-only" />{{ c.label }}
                            </label>
                        </div>
                    </fieldset>
                    <fieldset>
                        <legend class="text-sm font-medium text-ink-soft">Amount (INR)</legend>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button
                                v-for="p in presets"
                                :key="p"
                                type="button"
                                :aria-pressed="form.amount === p"
                                :class="['rounded-lg px-3 py-1.5 text-sm ring-1', form.amount === p ? 'bg-brand-600 text-white ring-brand-600' : 'ring-line-strong hover:bg-surface-muted']"
                                @click="form.amount = p"
                            >
                                {{ inr(p) }}
                            </button>
                        </div>
                        <FormField label="Other amount" :error="form.errors.amount" class="mt-3"><TextInput v-model.number="form.amount" type="number" :min="limits.min" :max="limits.max" inputmode="numeric" /></FormField>
                    </fieldset>
                    <FormField label="Full name" :error="form.errors.donor_name" required><TextInput v-model="form.donor_name" autocomplete="name" /></FormField>
                    <FormField label="Email (for your receipt)" :error="form.errors.donor_email" required><TextInput v-model="form.donor_email" type="email" autocomplete="email" /></FormField>
                    <FormField label="Phone" :error="form.errors.donor_phone"><TextInput v-model="form.donor_phone" type="tel" autocomplete="tel" /></FormField>
                    <CheckboxInput v-model="form.wants_80g" label="I want an 80G tax receipt" />
                    <FormField v-if="panNeeded" label="PAN" :error="form.errors.pan" :hint="form.wants_80g ? 'Needed for 80G.' : `Required by law for gifts of ${inr(limits.pan_from)} or more.`" required>
                        <TextInput v-model="form.pan" maxlength="10" autocomplete="off" class="uppercase" placeholder="ABCDE1234F" />
                    </FormField>
                    <FormField v-if="form.wants_80g" label="Postal address" :error="form.errors.address" required><TextArea v-model="form.address" rows="2" /></FormField>
                    <CheckboxInput v-model="form.is_anonymous" label="Don’t show my name among supporters" />
                    <div>
                        <CheckboxInput v-model="form.indian_resident">I am an Indian citizen or resident, and this contribution is from Indian sources.</CheckboxInput>
                        <p v-if="form.errors.indian_resident" class="mt-1 text-sm text-red-600">{{ form.errors.indian_resident }}</p>
                    </div>
                    <AppButton type="submit" class="w-full" :loading="form.processing">Continue to secure payment</AppButton>
                    <p class="text-center text-xs text-muted">You’ll pay on the payment provider’s secure page. We never see your card or UPI details.</p>
                </form>
            </CardPanel>
        </div>
    </SiteLayout>
</template>
