<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import ProductForm from './Partials/ProductForm.vue';
import ProductPreview from './Partials/ProductPreview.vue';
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
const isPreviewOpen = ref(false);

const submit = () => {
    form.transform((data) => ({ ...data, base_price: toPaise(data.base_price) }))
        .put(route('admin.products.update', props.product.id), { preserveScroll: true });
};
</script>

<template>
    <Head :title="`Edit ${product.name}`" />

    <AdminLayout>
        <template #header>
            <PageHeader :title="product.name" :back="{ label: 'Products', href: route('admin.products.index') }" />
        </template>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
            <div class="min-w-0 space-y-6">
                <button type="button" class="admin-btn-secondary lg:hidden" aria-controls="product-preview-inline" :aria-expanded="isPreviewOpen" @click="isPreviewOpen = !isPreviewOpen">
                    {{ isPreviewOpen ? 'Hide preview' : 'Preview' }}
                </button>
                <ProductPreview
                    v-if="isPreviewOpen"
                    id="product-preview-inline"
                    class="lg:hidden"
                    :form="form"
                    :variants="variants"
                    :images="images"
                    :view-url="route('products.show', product.slug)"
                />
                <ProductForm :form="form" :categories="categories" :genders="genders" @submit="submit" />
                <VariantGrid :product="product" :variants="variants" />
                <ImageUploader
                    :images="images"
                    :colours="colours"
                    :upload-url="route('admin.products.images.store', product.id)"
                    :save-url="route('admin.products.images.update', product.id)"
                />
            </div>
            <aside class="hidden lg:block">
                <div class="sticky top-8">
                    <ProductPreview :form="form" :variants="variants" :images="images" :view-url="route('products.show', product.slug)" />
                </div>
            </aside>
        </div>
    </AdminLayout>
</template>
