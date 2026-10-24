<script setup>
defineProps({
    title: { type: String, required: true },
    eyebrow: { type: String, default: null },
});
</script>

<template>
    <div class="animate-rise-in">
        <p v-if="eyebrow" class="text-xs uppercase tracking-[0.3em] text-brand-600">{{ eyebrow }}</p>
        <h1 class="font-display text-3xl font-semibold sm:text-4xl" :class="{ 'mt-1': eyebrow }">{{ title }}</h1>
        <span class="mt-4 block h-px w-10 bg-brand-400" aria-hidden="true" />
    </div>
</template>
