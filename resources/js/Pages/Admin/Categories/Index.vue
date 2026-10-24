<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminTable from '@/Components/Admin/AdminTable.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import StatusBadge from '@/Components/Admin/StatusBadge.vue';
import IconPlus from '@/Components/Icons/IconPlus.vue';

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
            <PageHeader title="Categories">
                <template #actions>
                    <Link :href="route('admin.categories.create')" class="admin-btn">
                        <IconPlus class="h-4 w-4" />
                        New category
                    </Link>
                </template>
            </PageHeader>
        </template>

        <AdminTable :rows="categories" :columns="columns" :filters="filters">
            <template #cell-name="{ row }">
                <Link :href="route('admin.categories.edit', row.id)" class="focus-ring rounded font-medium text-ink hover:underline">{{ row.name }}</Link>
            </template>
            <template #cell-parent="{ row }">{{ row.parent ?? '—' }}</template>
            <template #cell-is_active="{ row }">
                <StatusBadge :value="row.is_active ? 'active' : 'inactive'" :label="row.is_active ? 'Visible' : 'Hidden'" />
            </template>
            <template #cell-actions="{ row }">
                <button type="button" class="admin-btn-danger min-h-8 px-3" @click="deleteCategory(row)">Delete</button>
            </template>
            <template #empty>No categories yet.</template>
        </AdminTable>
    </AdminLayout>
</template>
