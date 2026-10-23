<script setup>
import { Head } from '@inertiajs/vue3';
import StoreLayout from '@/Layouts/StoreLayout.vue';

defineProps({
    title: { type: String, required: true },
    html: { type: String, required: true },
});
</script>

<template>
    <Head :title="title" />

    <StoreLayout>
        <article class="content-prose mx-auto max-w-3xl px-4 py-10 sm:py-16">
            <h1>{{ title }}</h1>
            <!-- html is server-rendered Markdown with raw HTML stripped -->
            <div v-html="html" />
        </article>
    </StoreLayout>
</template>
