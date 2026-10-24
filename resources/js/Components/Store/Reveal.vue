<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    as: { type: String, default: 'div' },
    delay: { type: Number, default: 0 },
});

const element = ref(null);
let observer = null;

onMounted(() => {
    // Without the observer, or with reduced motion, content simply stays visible.
    if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    const target = element.value;
    target.classList.add('reveal');
    target.style.transitionDelay = `${props.delay}ms`;

    observer = new IntersectionObserver(([entry]) => {
        if (entry.isIntersecting) {
            target.classList.add('is-revealed');
            observer.disconnect();
        }
    }, { rootMargin: '0px 0px -10% 0px' });
    observer.observe(target);
});

onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <component :is="as" ref="element">
        <slot />
    </component>
</template>
