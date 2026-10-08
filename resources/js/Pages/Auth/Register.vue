<script setup lang="ts">
import AppButton from '@/Components/AppButton.vue';
import CheckboxInput from '@/Components/CheckboxInput.vue';
import FormField from '@/Components/FormField.vue';
import SelectInput from '@/Components/SelectInput.vue';
import StepIndicator from '@/Components/StepIndicator.vue';
import TextInput from '@/Components/TextInput.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{ programmes: { id: number; name: string; code: string }[] }>();

const programmeOptions = computed(() => props.programmes.map((p) => ({ value: p.id, label: p.name })));

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    roll_number: '',
    programme_id: '' as number | '',
    admission_year: '' as number | '',
    graduation_year: '' as number | '',
    accept_terms: false,
    accept_communications: false,
});

// One form, one submission, shown in three steps. "Next" only checks that
// required fields are filled; the server validates everything.
const steps = ['About you', 'At IIITM', 'Secure your account'];
const step = ref(0);
const fieldsByStep: string[][] = [
    ['name', 'email'],
    ['roll_number', 'programme_id', 'admission_year', 'graduation_year'],
    ['password', 'password_confirmation', 'accept_terms', 'accept_communications'],
];
const required: Record<number, (() => boolean)[]> = {
    0: [() => !!form.name.trim(), () => /.+@.+\..+/.test(form.email)],
    1: [() => !!form.roll_number.trim(), () => form.programme_id !== '', () => !!form.graduation_year],
};
const stepError = ref('');
function next() {
    stepError.value = '';
    if ((required[step.value] ?? []).some((ok) => !ok())) {
        stepError.value = 'Please fill in the required fields to continue.';
        return;
    }
    step.value++;
}

const submit = () =>
    form.post(route('register.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
        // Send the user back to the first step with a problem.
        onError: (errors) => {
            const first = fieldsByStep.findIndex((fields) => fields.some((f) => f in errors));
            if (first !== -1) step.value = first;
        },
    });
</script>

<template>
    <GuestLayout wide title="Join Alumni Connect" description="Register with your institute details. We match them against the academic records to verify you.">
        <StepIndicator :steps="steps" :current="step" class="mb-6" @go="(i) => (step = i)" />
        <form class="space-y-5" novalidate @submit.prevent="step < steps.length - 1 ? next() : submit()">
            <div v-show="step === 0" class="space-y-5">
            <FormField label="Full name (as on your degree)" :error="form.errors.name" required>
                <TextInput v-model="form.name" autocomplete="name" required autofocus />
            </FormField>

            <FormField label="Email address" :error="form.errors.email" hint="Use an address you will keep; we send a verification link." required>
                <TextInput v-model="form.email" type="email" autocomplete="email" required />
            </FormField>

            </div>

            <fieldset v-show="step === 1" class="space-y-5">
                <legend class="sr-only">Academic details</legend>
                <p class="text-sm text-muted">We match these against the institute's records to verify you automatically.</p>
                <FormField label="Roll number" :error="form.errors.roll_number" required>
                    <TextInput v-model="form.roll_number" required placeholder="e.g. 2015IPG-045" />
                </FormField>
                <FormField label="Programme" :error="form.errors.programme_id" required>
                    <SelectInput v-model="form.programme_id" :options="programmeOptions" placeholder="Select your programme" required />
                </FormField>
                <div class="grid grid-cols-2 gap-4">
                    <FormField label="Admission year" :error="form.errors.admission_year">
                        <TextInput v-model.number="form.admission_year" type="number" inputmode="numeric" min="1997" />
                    </FormField>
                    <FormField label="Graduation year" :error="form.errors.graduation_year" required>
                        <TextInput v-model.number="form.graduation_year" type="number" inputmode="numeric" min="1998" required />
                    </FormField>
                </div>
            </fieldset>

            <div v-show="step === 2" class="space-y-5">
            <FormField label="Password" :error="form.errors.password" hint="At least 12 characters. A memorable passphrase works well." required>
                <TextInput v-model="form.password" type="password" autocomplete="new-password" required />
            </FormField>

            <FormField label="Confirm password" :error="form.errors.password_confirmation" required>
                <TextInput v-model="form.password_confirmation" type="password" autocomplete="new-password" required />
            </FormField>

            <div class="space-y-3">
                <CheckboxInput v-model="form.accept_terms">I accept the terms of use and the privacy policy, including how my alumni data is processed.</CheckboxInput>
                <p v-if="form.errors.accept_terms" class="text-sm text-red-600" role="alert">{{ form.errors.accept_terms }}</p>
                <CheckboxInput v-model="form.accept_communications">Send me alumni news, events and opportunities by email. (Optional; change any time.)</CheckboxInput>
            </div>

            </div>

            <p v-if="stepError" class="text-sm text-red-600" role="alert">{{ stepError }}</p>
            <div class="flex gap-2">
                <AppButton v-if="step > 0" variant="secondary" :icon="ArrowLeft" @click="step--">Back</AppButton>
                <AppButton v-if="step < steps.length - 1" type="submit" class="flex-1">Next<ArrowRight :size="16" aria-hidden="true" /></AppButton>
                <AppButton v-else type="submit" class="flex-1" :loading="form.processing">Create account</AppButton>
            </div>
        </form>

        <template #footer>
            Already registered? <Link :href="route('login')" class="font-medium text-brand-700 hover:text-brand-900">Sign in</Link>
        </template>
    </GuestLayout>
</template>
