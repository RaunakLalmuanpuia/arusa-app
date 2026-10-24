<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminTable from '@/Components/Admin/AdminTable.vue';
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

const fieldClass = 'rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500';
</script>

<template>
    <Head title="Orders" />

    <AdminLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">Orders</h1>
        </template>

        <p v-for="(message, key) in $page.props.errors" :key="key" class="mb-4 rounded bg-red-50 px-4 py-2 text-sm text-red-800" role="alert">
            {{ message }}
        </p>
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
                <label class="text-sm text-gray-600">
                    From
                    <input v-model="query.from" type="date" :class="fieldClass" />
                </label>
                <label class="text-sm text-gray-600">
                    To
                    <input v-model="query.to" type="date" :class="fieldClass" />
                </label>
            </template>
            <template #cell-number="{ row }">
                <Link :href="route('admin.orders.show', row.id)" class="font-medium text-gray-900 hover:underline">{{ row.number }}</Link>
            </template>
            <template #cell-customer="{ row }">
                {{ row.customer }}
                <span class="block text-xs text-gray-500">{{ row.phone }}</span>
            </template>
            <template #cell-total="{ row }">{{ formatPaise(row.total) }}</template>
            <template #empty>No orders match.</template>
        </AdminTable>
    </AdminLayout>
</template>
