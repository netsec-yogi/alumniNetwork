<script setup lang="ts">
import { computed, ref } from 'vue';

/**
 * Monthly single-series bar chart (inline SVG). One hue, bars anchored to
 * the baseline with rounded tops, a 2px gap, a recessive axis, and a
 * hover/focus tooltip. "Show table" gives the same data as text.
 */
const props = withDefaults(defineProps<{ data: { label: string; value: number }[]; label: string; height?: number }>(), { height: 120 });

const W = 600;
const pad = { top: 8, bottom: 22, left: 4, right: 4 };
const max = computed(() => Math.max(1, ...props.data.map((d) => d.value)));
const band = computed(() => (W - pad.left - pad.right) / Math.max(1, props.data.length));
const bars = computed(() =>
    props.data.map((d, i) => {
        const h = (d.value / max.value) * (props.height - pad.top - pad.bottom);
        return { ...d, x: pad.left + i * band.value + 1, w: Math.max(1, band.value - 2), y: props.height - pad.bottom - h, h };
    }),
);
const active = ref<number | null>(null);
const showTable = ref(false);
const tip = computed(() => (active.value === null ? null : bars.value[active.value]));
</script>

<template>
    <figure>
        <div class="relative">
            <svg :viewBox="`0 0 ${W} ${height}`" class="w-full" role="img" :aria-label="`${label}: ${data.map((d) => `${d.label} ${d.value}`).join(', ')}`" @mouseleave="active = null">
                <line :x1="pad.left" :x2="W - pad.right" :y1="height - pad.bottom" :y2="height - pad.bottom" class="stroke-line-strong" stroke-width="1" />
                <g v-for="(b, i) in bars" :key="b.label">
                    <!-- Hit target taller than the bar. -->
                    <rect :x="b.x - 1" :y="pad.top" :width="b.w + 2" :height="height - pad.top - pad.bottom" fill="transparent" tabindex="0" :aria-label="`${b.label}: ${b.value}`" @mouseenter="active = i" @focus="active = i" @blur="active = null" />
                    <path
                        v-if="b.h > 0"
                        :d="`M${b.x},${height - pad.bottom} V${b.y + Math.min(4, b.h)} Q${b.x},${b.y} ${b.x + Math.min(4, b.w / 2)},${b.y} H${b.x + b.w - Math.min(4, b.w / 2)} Q${b.x + b.w},${b.y} ${b.x + b.w},${b.y + Math.min(4, b.h)} V${height - pad.bottom} Z`"
                        :class="active === i ? 'fill-brand-800' : 'fill-brand-600'"
                        class="pointer-events-none"
                    />
                    <text v-if="i % 2 === 0 || data.length <= 6" :x="b.x + b.w / 2" :y="height - 6" text-anchor="middle" class="fill-muted text-[10px]">{{ b.label }}</text>
                </g>
            </svg>
            <div v-if="tip" class="pointer-events-none absolute -translate-x-1/2 -translate-y-full rounded-md bg-slate-900 px-2 py-1 text-xs whitespace-nowrap text-white shadow" :style="{ left: `${((tip.x + tip.w / 2) / W) * 100}%`, top: `${(tip.y / height) * 100}%` }">
                {{ tip.label }}: <strong class="tabular-nums">{{ tip.value.toLocaleString('en-IN') }}</strong>
            </div>
        </div>
        <button type="button" class="mt-1 text-xs text-muted hover:text-ink" :aria-expanded="showTable" @click="showTable = !showTable">{{ showTable ? 'Hide table' : 'Show table' }}</button>
        <table v-if="showTable" class="mt-2 w-full text-xs">
            <tr v-for="d in data" :key="d.label" class="border-t border-line-soft"><td class="py-1 text-muted">{{ d.label }}</td><td class="py-1 text-right tabular-nums">{{ d.value }}</td></tr>
        </table>
    </figure>
</template>
