<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminTable from '@/Components/Admin/AdminTable.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import StatusBadge from '@/Components/Admin/StatusBadge.vue';
import { formatPaise } from '@/money';

defineProps({
    orders: { type: Object, required: true },
    filters: { type: Object, required: true },
    statuses: { type: Array, required: true },
    payment_methods: { type: Array, required: true },
    zones: { type: Array, required: true },
});

const columns = [
    { key: 'number', label: 'Order' },
    { key: 'placed_at', label: 'Placed' },
    { key: 'customer', label: 'Customer' },
    { key: 'zone', label: 'Zone' },
    { key: 'total', label: 'Total' },
    { key: 'payment', label: 'Payment' },
    { key: 'status', label: 'Status' },
];

const fieldClass = 'admin-field md:w-auto';
</script>

<template>
    <Head title="Orders" />

    <AdminLayout>
        <template #header>
            <PageHeader title="Orders" />
        </template>

        <AdminTable :rows="orders" :columns="columns" :filters="filters">
            <template #filters="{ query }">
                <select v-model="query.status" aria-label="Status" :class="fieldClass">
                    <option :value="null">All statuses</option>
                    <option v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                </select>
                <select v-model="query.payment_method" aria-label="Payment method" :class="fieldClass">
                    <option :value="null">All payments</option>
                    <option v-for="method in payment_methods" :key="method.value" :value="method.value">{{ method.label }}</option>
                </select>
                <select v-model="query.shipping_zone_id" aria-label="Zone" :class="fieldClass">
                    <option :value="null">All zones</option>
                    <option v-for="zone in zones" :key="zone.id" :value="zone.id">{{ zone.name }}</option>
                </select>
                <label class="flex items-center gap-2 text-sm text-brand-600">
                    From
                    <input v-model="query.from" type="date" class="admin-field w-auto" />
                </label>
                <label class="flex items-center gap-2 text-sm text-brand-600">
                    To
                    <input v-model="query.to" type="date" class="admin-field w-auto" />
                </label>
            </template>
            <template #cell-number="{ row }">
                <Link :href="route('admin.orders.show', row.id)" class="focus-ring rounded font-medium text-ink hover:underline">{{ row.number }}</Link>
            </template>
            <template #cell-placed_at="{ row }">
                <span class="whitespace-nowrap text-brand-700">{{ row.placed_at }}</span>
            </template>
            <template #cell-customer="{ row }">
                {{ row.customer }}
                <span class="block text-xs text-brand-500">{{ row.phone }}</span>
            </template>
            <template #cell-total="{ row }">
                <span class="tabular-nums">{{ formatPaise(row.total) }}</span>
            </template>
            <template #cell-payment="{ row }">
                <span class="flex flex-wrap items-center gap-2">
                    <span class="text-brand-700">{{ row.payment_method_label }}</span>
                    <StatusBadge :value="row.payment_status_value" :label="row.payment_status_label" />
                </span>
            </template>
            <template #cell-status="{ row }">
                <StatusBadge :value="row.status_value" :label="row.status" />
            </template>
            <template #empty>No orders match.</template>
        </AdminTable>
    </AdminLayout>
</template>
