<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import CategoryForm from './Partials/CategoryForm.vue';

const props = defineProps({
    category: { type: Object, required: true },
    parents: { type: Array, required: true },
    imageUrl: { type: String, default: null },
});

const form = useForm({ ...props.category, image: null });

// PHP only parses multipart bodies on POST, so the file upload spoofs PUT.
const submit = () => form.transform((data) => ({ ...data, _method: 'put' })).post(route('admin.categories.update', props.category.id));
</script>

<template>
    <Head :title="`Edit ${category.name}`" />

    <AdminLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">Edit category</h1>
        </template>

        <CategoryForm :form="form" :parents="parents" :image-url="imageUrl" @submit="submit" />
    </AdminLayout>
</template>
