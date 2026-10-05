<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import StatusBadge from './StatusBadge.vue';

export interface JobSummary {
    id: number;
    type: string;
    title: string;
    organization: string;
    location: string | null;
    work_mode: string;
    employment_type: string;
    experience: string | null;
    skills: string[];
    compensation: string | null;
    deadline: string | null;
    referral_available: boolean;
    status: string;
    is_live: boolean;
    posted_by: string | null;
    posted_at: string;
}

defineProps<{ job: JobSummary; showStatus?: boolean }>();
</script>

<template>
    <Link :href="route('jobs.show', job.id)" class="block rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200 transition hover:ring-brand-300">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="font-semibold text-slate-900">{{ job.title }}</p>
                <p class="text-sm text-slate-600">{{ job.organization }}<template v-if="job.location"> · {{ job.location }}</template></p>
            </div>
            <div class="flex gap-1.5">
                <StatusBadge v-if="showStatus" :status="job.status === 'approved' ? 'active' : job.status" :label="job.status" />
                <span v-if="job.type === 'internship'" class="rounded-full bg-violet-50 px-2 py-0.5 text-xs font-medium text-violet-700 ring-1 ring-violet-600/20">Internship</span>
                <span v-if="job.referral_available" class="rounded-full bg-accent-400/15 px-2 py-0.5 text-xs font-medium text-amber-800">Referral available</span>
            </div>
        </div>
        <p class="mt-2 text-sm text-slate-500">
            {{ [job.work_mode, job.employment_type, job.experience, job.compensation].filter(Boolean).join(' · ') }}
        </p>
        <div v-if="job.skills.length" class="mt-3 flex flex-wrap gap-1">
            <span v-for="s in job.skills.slice(0, 6)" :key="s" class="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-700">{{ s }}</span>
        </div>
        <p class="mt-3 text-xs text-slate-400">Posted {{ job.posted_at }}<template v-if="job.posted_by"> by {{ job.posted_by }}</template><template v-if="job.deadline"> · Apply by {{ job.deadline }}</template></p>
    </Link>
</template>
