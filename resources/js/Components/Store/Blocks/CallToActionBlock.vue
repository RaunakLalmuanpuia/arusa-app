<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    data: { type: Object, required: true },
});
</script>

<template>
    <section class="mx-auto max-w-6xl px-4">
        <div class="bg-brand-900 px-6 py-14 text-center text-brand-50 sm:px-12 sm:py-20">
            <h2 class="font-display text-2xl font-semibold sm:text-4xl">{{ data.heading }}</h2>
            <p v-if="data.text" class="mx-auto mt-3 max-w-xl text-brand-200">{{ data.text }}</p>
            <component
                :is="data.button_url.startsWith('/') ? Link : 'a'"
                :href="data.button_url"
                class="mt-8 inline-flex min-h-12 items-center border border-brand-50 px-8 text-xs font-medium uppercase tracking-[0.2em] transition-colors duration-200 hover:bg-brand-50 hover:text-ink motion-reduce:transition-none focus-ring-inverse"
            >
                {{ data.button_label }}
            </component>
        </div>
    </section>
</template>
