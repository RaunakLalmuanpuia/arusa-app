<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminTable from '@/Components/Admin/AdminTable.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import StatusBadge from '@/Components/Admin/StatusBadge.vue';
import IconPlus from '@/Components/Icons/IconPlus.vue';
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
            <PageHeader title="Products">
                <template #actions>
                    <Link :href="route('admin.products.create')" class="admin-btn">
                        <IconPlus class="h-4 w-4" />
                        New product
                    </Link>
                </template>
            </PageHeader>
        </template>

        <AdminTable :rows="products" :columns="columns" :filters="filters">
            <template #filters="{ query }">
                <select v-model="query.category_id" aria-label="Category" class="admin-field md:w-auto">
                    <option :value="null">All categories</option>
                    <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.label }}</option>
                </select>
            </template>
            <template #cell-name="{ row }">
                <Link :href="route('admin.products.edit', row.id)" class="focus-ring rounded font-medium text-ink hover:underline">{{ row.name }}</Link>
            </template>
            <template #cell-base_price="{ row }">
                <span class="tabular-nums">{{ formatPaise(row.base_price) }}</span>
            </template>
            <template #cell-stock="{ row }">
                <span class="tabular-nums" :class="row.stock <= 3 ? 'font-medium text-red-700' : ''">{{ row.stock }}</span>
            </template>
            <template #cell-is_active="{ row }">
                <StatusBadge :value="row.is_active ? 'active' : 'inactive'" :label="row.is_active ? 'Visible' : 'Hidden'" />
            </template>
            <template #cell-actions="{ row }">
                <button type="button" class="admin-btn-danger min-h-8 px-3" @click="deleteProduct(row)">Delete</button>
            </template>
            <template #empty>No products yet.</template>
        </AdminTable>
    </AdminLayout>
</template>
