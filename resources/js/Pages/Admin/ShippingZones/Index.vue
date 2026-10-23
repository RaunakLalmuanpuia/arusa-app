<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminTable from '@/Components/Admin/AdminTable.vue';
import { formatPaise } from '@/money';

defineProps({
    zones: { type: Object, required: true },
    filters: { type: Object, required: true },
});

const columns = [
    { key: 'name', label: 'Zone' },
    { key: 'rate', label: 'Rate' },
    { key: 'free_above', label: 'Free from' },
    { key: 'cod_allowed', label: 'COD' },
    { key: 'pincodes_count', label: 'Pincodes' },
    { key: 'is_active', label: 'Active' },
    { key: 'actions', label: '' },
];

const deleteZone = (zone) => {
    router.delete(route('admin.shipping-zones.destroy', zone.id), {
        onBefore: () => window.confirm(`Delete "${zone.name}"?`),
    });
};
</script>

<template>
    <Head title="Shipping zones" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold text-gray-900">Shipping zones</h1>
                <Link :href="route('admin.shipping-zones.create')" class="rounded bg-gray-800 px-3 py-2 text-sm font-medium text-white">New zone</Link>
            </div>
        </template>

        <p v-if="$page.props.errors.zone" class="mb-4 rounded bg-red-50 px-4 py-2 text-sm text-red-800" role="alert">
            {{ $page.props.errors.zone }}
        </p>

        <AdminTable :rows="zones" :columns="columns" :filters="filters">
            <template #cell-name="{ row }">
                <Link :href="route('admin.shipping-zones.edit', row.id)" class="font-medium text-gray-900 hover:underline">{{ row.name }}</Link>
            </template>
            <template #cell-rate="{ row }">{{ formatPaise(row.rate) }}</template>
            <template #cell-free_above="{ row }">{{ row.free_above === null ? '—' : formatPaise(row.free_above) }}</template>
            <template #cell-cod_allowed="{ row }">{{ row.cod_allowed ? 'Yes' : 'No' }}</template>
            <template #cell-is_active="{ row }">{{ row.is_active ? 'Yes' : 'No' }}</template>
            <template #cell-actions="{ row }">
                <button type="button" class="text-red-600 hover:underline" @click="deleteZone(row)">Delete</button>
            </template>
            <template #empty>No zones yet. Without a zone nobody can check out.</template>
        </AdminTable>
    </AdminLayout>
</template>
