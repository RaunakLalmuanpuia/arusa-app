<script setup>
import { Head } from '@inertiajs/vue3';
import StoreLayout from '@/Layouts/StoreLayout.vue';
import PageBlocks from '@/Components/Store/Blocks/PageBlocks.vue';

defineProps({
    title: { type: String, required: true },
    blocks: { type: Array, required: true },
});
</script>

<template>
    <Head :title="title" />

    <StoreLayout>
        <PageBlocks :title="title" :blocks="blocks" />
    </StoreLayout>
</template>
