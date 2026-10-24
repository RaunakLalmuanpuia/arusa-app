<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import PageForm from './Partials/PageForm.vue';
import { newBlock, withoutUids } from './Blocks/blockTypes.js';

defineProps({
    imageBaseUrl: { type: String, required: true },
});

const form = useForm({ title: '', slug: '', blocks: [newBlock('hero')] });
</script>

<template>
    <Head title="New page" />

    <AdminLayout>
        <template #header>
            <PageHeader title="New page" :back="{ label: 'Pages', href: route('admin.pages.index') }" />
        </template>

        <PageForm :form="form" :image-base-url="imageBaseUrl" @submit="form.transform(withoutUids).post(route('admin.pages.store'))" />
    </AdminLayout>
</template>
