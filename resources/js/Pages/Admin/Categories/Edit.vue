<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import CategoryForm from './Partials/CategoryForm.vue';

const props = defineProps({
    category: { type: Object, required: true },
    parents: { type: Array, required: true },
});

const form = useForm({ ...props.category });
</script>

<template>
    <Head :title="`Edit ${category.name}`" />

    <AdminLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">Edit category</h1>
        </template>

        <CategoryForm :form="form" :parents="parents" @submit="form.put(route('admin.categories.update', category.id))" />
    </AdminLayout>
</template>
