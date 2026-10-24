<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ProductForm from './Partials/ProductForm.vue';
import ImageUploader from '@/Components/Admin/ImageUploader.vue';
import VariantGrid from './Partials/VariantGrid.vue';
import { toPaise } from '@/money';

const props = defineProps({
    product: { type: Object, required: true },
    variants: { type: Array, required: true },
    categories: { type: Array, required: true },
    genders: { type: Array, required: true },
    images: { type: Array, required: true },
    colours: { type: Array, required: true },
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
            <ProductForm :form="form" :categories="categories" :genders="genders" @submit="submit" />
            <VariantGrid :product="product" :variants="variants" />
            <ImageUploader
                :images="images"
                :colours="colours"
                :upload-url="route('admin.products.images.store', product.id)"
                :save-url="route('admin.products.images.update', product.id)"
            />
        </div>
    </AdminLayout>
</template>
