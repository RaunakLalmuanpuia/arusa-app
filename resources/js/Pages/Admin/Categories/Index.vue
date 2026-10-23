<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminTable from '@/Components/Admin/AdminTable.vue';

defineProps({
    categories: { type: Object, required: true },
    filters: { type: Object, required: true },
});

const columns = [
    { key: 'name', label: 'Name' },
    { key: 'parent', label: 'Parent' },
    { key: 'products_count', label: 'Products' },
    { key: 'sort_order', label: 'Order' },
    { key: 'is_active', label: 'Visible' },
    { key: 'actions', label: '' },
];

const deleteCategory = (category) => {
    router.delete(route('admin.categories.destroy', category.id), {
        onBefore: () => window.confirm(`Delete "${category.name}"?`),
    });
};
</script>

<template>
    <Head title="Categories" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold text-gray-900">Categories</h1>
                <Link :href="route('admin.categories.create')" class="rounded bg-gray-800 px-3 py-2 text-sm font-medium text-white">New category</Link>
            </div>
        </template>

        <p v-if="$page.props.errors.category" class="mb-4 rounded bg-red-50 px-4 py-2 text-sm text-red-800" role="alert">
            {{ $page.props.errors.category }}
        </p>

        <AdminTable :rows="categories" :columns="columns" :filters="filters">
            <template #cell-name="{ row }">
                <Link :href="route('admin.categories.edit', row.id)" class="font-medium text-gray-900 hover:underline">{{ row.name }}</Link>
            </template>
            <template #cell-parent="{ row }">{{ row.parent ?? '—' }}</template>
            <template #cell-is_active="{ row }">{{ row.is_active ? 'Yes' : 'No' }}</template>
            <template #cell-actions="{ row }">
                <button type="button" class="text-red-600 hover:underline" @click="deleteCategory(row)">Delete</button>
            </template>
            <template #empty>No categories yet.</template>
        </AdminTable>
    </AdminLayout>
</template>
