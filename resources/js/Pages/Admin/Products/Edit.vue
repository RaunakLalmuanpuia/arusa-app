<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ProductForm from './Partials/ProductForm.vue';
import { toPaise } from '@/money';

const props = defineProps({
    product: { type: Object, required: true },
    categories: { type: Array, required: true },
});

const form = useForm({ ...props.product, base_price: props.product.base_price / 100 });

const submit = () => {
    form.transform((data) => ({ ...data, base_price: toPaise(data.base_price) }))
        .put(route('admin.products.update', props.product.id), { preserveScroll: true });
};
</script>

<template>
    <Head :title="`Edit ${product.name}`" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold text-gray-900">{{ product.name }}</h1>
                <Link :href="route('admin.products.index')" class="text-sm text-gray-600 underline">All products</Link>
            </div>
        </template>

        <div class="space-y-8">
            <ProductForm :form="form" :categories="categories" @submit="submit" />
        </div>
    </AdminLayout>
</template>
