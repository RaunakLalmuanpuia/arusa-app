<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ProductForm from './Partials/ProductForm.vue';
import { toPaise } from '@/money';

const props = defineProps({
    categories: { type: Array, required: true },
    genders: { type: Array, required: true },
});

const form = useForm({
    name: '',
    slug: '',
    category_id: props.categories[0]?.id ?? null,
    gender: null,
    description: '',
    base_price: '',
    is_active: true,
});

const submit = () => {
    form.transform((data) => ({ ...data, base_price: toPaise(data.base_price) })).post(route('admin.products.store'));
};
</script>

<template>
    <Head title="New product" />

    <AdminLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">New product</h1>
        </template>

        <p v-if="categories.length === 0" class="text-sm text-gray-600">Create a category first.</p>
        <ProductForm v-else :form="form" :categories="categories" :genders="genders" @submit="submit" />
    </AdminLayout>
</template>
