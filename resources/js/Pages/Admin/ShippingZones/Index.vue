<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminTable from '@/Components/Admin/AdminTable.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import StatusBadge from '@/Components/Admin/StatusBadge.vue';
import IconPlus from '@/Components/Icons/IconPlus.vue';
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
            <PageHeader title="Shipping zones">
                <template #actions>
                    <Link :href="route('admin.shipping-zones.create')" class="admin-btn">
                        <IconPlus class="h-4 w-4" />
                        New zone
                    </Link>
                </template>
            </PageHeader>
        </template>

        <AdminTable :rows="zones" :columns="columns" :filters="filters">
            <template #cell-name="{ row }">
                <Link :href="route('admin.shipping-zones.edit', row.id)" class="focus-ring rounded font-medium text-ink hover:underline">{{ row.name }}</Link>
            </template>
            <template #cell-rate="{ row }">
                <span class="tabular-nums">{{ formatPaise(row.rate) }}</span>
            </template>
            <template #cell-free_above="{ row }">
                <span class="tabular-nums">{{ row.free_above === null ? '—' : formatPaise(row.free_above) }}</span>
            </template>
            <template #cell-cod_allowed="{ row }">{{ row.cod_allowed ? 'Yes' : 'No' }}</template>
            <template #cell-is_active="{ row }">
                <StatusBadge :value="row.is_active ? 'active' : 'inactive'" :label="row.is_active ? 'Active' : 'Off'" />
            </template>
            <template #cell-actions="{ row }">
                <button type="button" class="admin-btn-danger min-h-8 px-3" @click="deleteZone(row)">Delete</button>
            </template>
            <template #empty>No zones yet. Without a zone nobody can check out.</template>
        </AdminTable>
    </AdminLayout>
</template>
