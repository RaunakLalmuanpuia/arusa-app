<script setup>
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: null },
});
</script>

<template>
    <section class="grid gap-5 p-6 md:grid-cols-[13rem_minmax(0,1fr)] md:gap-8">
        <header>
            <h2 class="font-medium text-ink">{{ title }}</h2>
            <p v-if="description" class="mt-1 text-sm text-brand-600">{{ description }}</p>
        </header>
        <div class="min-w-0 space-y-5">
            <slot />
        </div>
    </section>
</template>
