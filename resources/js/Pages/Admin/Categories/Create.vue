<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import CategoryForm from './Partials/CategoryForm.vue';

defineProps({
    parents: { type: Array, required: true },
});

const form = useForm({ name: '', slug: '', parent_id: null, sort_order: 0, is_active: true });
</script>

<template>
    <Head title="New category" />

    <AdminLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">New category</h1>
        </template>

        <CategoryForm :form="form" :parents="parents" @submit="form.post(route('admin.categories.store'))" />
    </AdminLayout>
</template>
