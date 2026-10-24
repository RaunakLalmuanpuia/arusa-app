<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import ProductForm from './Partials/ProductForm.vue';
import ProductPreview from './Partials/ProductPreview.vue';
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
const isPreviewOpen = ref(false);

const submit = () => {
    form.transform((data) => ({ ...data, base_price: toPaise(data.base_price) })).post(route('admin.products.store'));
};
</script>

<template>
    <Head title="New product" />

    <AdminLayout>
        <template #header>
            <PageHeader title="New product" :back="{ label: 'Products', href: route('admin.products.index') }" />
        </template>

        <div v-if="categories.length === 0" class="admin-card px-4 py-12 text-center text-sm text-brand-600">
            Create a category first.
            <Link :href="route('admin.categories.create')" class="focus-ring ml-1 rounded font-medium text-ink underline">New category</Link>
        </div>
        <div v-else class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
            <div class="min-w-0 space-y-6">
                <button type="button" class="admin-btn-secondary lg:hidden" aria-controls="product-preview-inline" :aria-expanded="isPreviewOpen" @click="isPreviewOpen = !isPreviewOpen">
                    {{ isPreviewOpen ? 'Hide preview' : 'Preview' }}
                </button>
                <ProductPreview v-if="isPreviewOpen" id="product-preview-inline" class="lg:hidden" :form="form" />
                <ProductForm :form="form" :categories="categories" :genders="genders" @submit="submit" />
            </div>
            <aside class="hidden lg:block">
                <div class="sticky top-8">
                    <ProductPreview :form="form" />
                </div>
            </aside>
        </div>
    </AdminLayout>
</template>
