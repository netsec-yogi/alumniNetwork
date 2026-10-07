<script setup lang="ts">
/** Raised vs goal, with any sponsor match shown as its own segment so it's never mistaken for donor money. */
defineProps<{ raised: number; matched: number; goal: number; donors: number; daysLeft?: number | null; large?: boolean }>();
const inr = (n: number) => '₹' + n.toLocaleString('en-IN');
</script>

<template>
    <div>
        <div
            :class="['flex overflow-hidden rounded-full bg-surface-sunken', large ? 'h-3' : 'h-2']"
            role="progressbar"
            :aria-valuenow="Math.min(goal, raised + matched)"
            aria-valuemin="0"
            :aria-valuemax="goal"
            :aria-label="`${inr(raised + matched)} of ${inr(goal)}`"
        >
            <div class="bg-brand-600" :style="{ width: `${Math.min(100, (raised / goal) * 100)}%` }" />
            <div v-if="matched" class="border-l-2 border-white bg-accent-500" :style="{ width: `${Math.min(100 - (raised / goal) * 100, (matched / goal) * 100)}%` }" />
        </div>
        <div :class="['mt-2 flex flex-wrap justify-between gap-2', large ? 'text-base' : 'text-sm']">
            <span><strong class="tabular-nums">{{ inr(raised + matched) }}</strong> <span class="text-muted">of {{ inr(goal) }}</span></span>
            <span class="text-muted">
                {{ donors }} {{ donors === 1 ? 'donor' : 'donors' }}<template v-if="daysLeft !== null && daysLeft !== undefined"> · {{ daysLeft }} {{ daysLeft === 1 ? 'day' : 'days' }} left</template>
            </span>
        </div>
        <p v-if="matched" class="mt-1 text-xs text-muted"><span class="mr-1 inline-block size-2 rounded-full bg-accent-500" aria-hidden="true" />includes {{ inr(matched) }} sponsor match</p>
    </div>
</template>
