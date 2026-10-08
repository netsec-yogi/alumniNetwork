<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * A number that counts up from zero the first time it is scrolled into
 * view. Screen readers always get the final value (aria-label), and with
 * reduced motion the final value shows immediately.
 */
const props = defineProps<{ value: number; duration?: number }>();
const shown = ref(0);
const el = ref<HTMLElement>();
let io: IntersectionObserver | undefined;
let raf = 0;

const format = (n: number) => n.toLocaleString('en-IN');

function run() {
    const total = props.duration ?? 1400;
    const start = performance.now();
    const step = (now: number) => {
        const t = Math.min(1, (now - start) / total);
        shown.value = Math.round(props.value * (1 - Math.pow(1 - t, 3)));
        if (t < 1) raf = requestAnimationFrame(step);
    };
    raf = requestAnimationFrame(step);
}

onMounted(() => {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) {
        shown.value = props.value;
        return;
    }
    io = new IntersectionObserver((entries) => {
        if (entries.some((e) => e.isIntersecting)) {
            run();
            io?.disconnect();
        }
    });
    if (el.value) io.observe(el.value);
});
onBeforeUnmount(() => {
    io?.disconnect();
    cancelAnimationFrame(raf);
});
</script>

<template>
    <span ref="el" :aria-label="format(value)" class="tabular-nums"><span aria-hidden="true">{{ format(shown) }}</span></span>
</template>
