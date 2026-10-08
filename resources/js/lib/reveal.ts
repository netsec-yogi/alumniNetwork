import type { Directive } from 'vue';

/**
 * v-reveal: fade-and-rise an element the first time it scrolls into view.
 * Without IntersectionObserver, or with reduced motion, it simply shows.
 * Optional value: delay in ms (for staggering cards).
 */
const reduced = () => typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

export const vReveal: Directive<HTMLElement, number | undefined> = {
    mounted(el, binding) {
        if (reduced() || !('IntersectionObserver' in window)) return;
        el.classList.add('reveal');
        if (binding.value) el.style.transitionDelay = `${binding.value}ms`;
        const io = new IntersectionObserver(
            (entries) => {
                for (const e of entries) {
                    if (e.isIntersecting) {
                        el.classList.add('is-visible');
                        io.disconnect();
                    }
                }
            },
            { rootMargin: '0px 0px -8% 0px', threshold: 0.08 },
        );
        io.observe(el);
    },
};
