<script setup>
import { Head } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';

defineProps({
    title: { type: String, required: true },
    html: { type: String, required: true },
});
</script>

<template>
    <Head :title="title" />

    <GuestLayout>
        <article class="content-prose">
            <h1>{{ title }}</h1>
            <!-- html is server-rendered Markdown with raw HTML stripped -->
            <div v-html="html" />
        </article>
    </GuestLayout>
</template>
