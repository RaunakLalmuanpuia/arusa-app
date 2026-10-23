<script setup>
import logo256 from '../../images/logo-256.jpg';
import logo512 from '../../images/logo-512.jpg';

defineProps({
    onDark: { type: Boolean, default: false },
});
</script>

<template>
    <!-- JPG has a white background: multiply blends it into light surfaces; invert + screen does the same on dark ones -->
    <img
        :src="logo256"
        :srcset="`${logo256} 256w, ${logo512} 512w`"
        sizes="64px"
        width="256"
        height="256"
        alt="Arusa Apparel Co."
        :class="onDark ? 'invert mix-blend-screen' : 'mix-blend-multiply'"
    />
</template>
