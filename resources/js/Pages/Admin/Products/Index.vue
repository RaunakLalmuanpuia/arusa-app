<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminTable from '@/Components/Admin/AdminTable.vue';
import { formatPaise } from '@/money';

defineProps({
    products: { type: Object, required: true },
    filters: { type: Object, required: true },
    categories: { type: Array, required: true },
});

const columns = [
    { key: 'name', label: 'Name' },
    { key: 'category', label: 'Category' },
    { key: 'base_price', label: 'Price' },
    { key: 'variants_count', label: 'Variants' },
    { key: 'stock', label: 'Stock' },
    { key: 'is_active', label: 'Visible' },
    { key: 'actions', label: '' },
];

const deleteProduct = (product) => {
    router.delete(route('admin.products.destroy', product.id), {
        onBefore: () => window.confirm(`Delete "${product.name}" and its images?`),
    });
};
</script>

<template>
    <Head title="Products" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold text-gray-900">Products</h1>
                <Link :href="route('admin.products.create')" class="rounded bg-gray-800 px-3 py-2 text-sm font-medium text-white">New product</Link>
            </div>
        </template>

        <p v-if="$page.props.errors.product" class="mb-4 rounded bg-red-50 px-4 py-2 text-sm text-red-800" role="alert">
            {{ $page.props.errors.product }}
        </p>
        <AdminTable :rows="products" :columns="columns" :filters="filters">
            <template #filters="{ query }">
                <select
                    v-model="query.category_id"
                    aria-label="Category"
                    class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option :value="null">All categories</option>
                    <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.label }}</option>
                </select>
            </template>
            <template #cell-name="{ row }">
                <Link :href="route('admin.products.edit', row.id)" class="font-medium text-gray-900 hover:underline">{{ row.name }}</Link>
            </template>
            <template #cell-base_price="{ row }">{{ formatPaise(row.base_price) }}</template>
            <template #cell-stock="{ row }">
                <span :class="row.stock <= 3 ? 'font-medium text-red-700' : ''">{{ row.stock }}</span>
            </template>
            <template #cell-is_active="{ row }">{{ row.is_active ? 'Yes' : 'No' }}</template>
            <template #cell-actions="{ row }">
                <button type="button" class="text-red-600 hover:underline" @click="deleteProduct(row)">Delete</button>
            </template>
            <template #empty>No products yet.</template>
        </AdminTable>
    </AdminLayout>
</template>
