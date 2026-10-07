<script setup lang="ts">
import AppButton from '@/Components/AppButton.vue';
import CheckboxInput from '@/Components/CheckboxInput.vue';
import FormField from '@/Components/FormField.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextInput from '@/Components/TextInput.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

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

const submit = () => form.post(route('register.store'), { onFinish: () => form.reset('password', 'password_confirmation') });
</script>

<template>
    <GuestLayout wide title="Join Alumni Connect" description="Register with your institute details. We match them against the academic records to verify you.">
        <form class="space-y-5" novalidate @submit.prevent="submit">
            <FormField label="Full name (as on your degree)" :error="form.errors.name" required>
                <TextInput v-model="form.name" autocomplete="name" required autofocus />
            </FormField>

            <FormField label="Email address" :error="form.errors.email" hint="Use an address you will keep; we send a verification link." required>
                <TextInput v-model="form.email" type="email" autocomplete="email" required />
            </FormField>

            <fieldset class="space-y-5 rounded-lg bg-surface-muted p-4">
                <legend class="sr-only">Academic details</legend>
                <p class="text-sm font-medium text-ink-soft">Academic details</p>
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

            <AppButton type="submit" class="w-full" :loading="form.processing">Create account</AppButton>
        </form>

        <template #footer>
            Already registered? <Link :href="route('login')" class="font-medium text-brand-700 hover:text-brand-900">Sign in</Link>
        </template>
    </GuestLayout>
</template>
