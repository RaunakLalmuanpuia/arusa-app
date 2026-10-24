<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import CategoryForm from './Partials/CategoryForm.vue';

defineProps({
    parents: { type: Array, required: true },
});

const form = useForm({ name: '', slug: '', parent_id: null, sort_order: 0, is_active: true, image: null });
</script>

<template>
    <Head title="New category" />

    <AdminLayout>
        <template #header>
            <PageHeader title="New category" :back="{ label: 'Categories', href: route('admin.categories.index') }" />
        </template>

        <CategoryForm :form="form" :parents="parents" @submit="form.post(route('admin.categories.store'))" />
    </AdminLayout>
</template>
