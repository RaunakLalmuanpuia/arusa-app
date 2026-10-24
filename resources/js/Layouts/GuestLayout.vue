<script setup>
import StoreLayout from '@/Layouts/StoreLayout.vue';

defineProps({
    title: { type: String, required: true },
});
</script>

<template>
    <StoreLayout>
        <div class="mx-auto max-w-md px-4 py-10 sm:py-16">
            <h1 class="mb-8 font-display text-3xl font-semibold sm:text-4xl">{{ title }}</h1>
            <slot />
        </div>
    </StoreLayout>
</template>
