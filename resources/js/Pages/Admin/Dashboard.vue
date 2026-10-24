<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatPaise } from '@/money';

defineProps({
    today: { type: Object, required: true },
    needs_action: { type: Array, required: true },
    low_stock: { type: Array, required: true },
    revenue: { type: Object, required: true },
});
</script>

<template>
    <Head title="Admin" />

    <AdminLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">Dashboard</h1>
        </template>

        <div class="space-y-6">
            <section class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <Link
                    v-for="item in needs_action"
                    :key="item.status"
                    :href="route('admin.orders.index', { status: item.status })"
                    class="rounded bg-white p-4 shadow-sm hover:bg-gray-50"
                >
                    <span class="block text-sm text-gray-500">{{ item.label }}</span>
                    <span class="text-2xl font-semibold" :class="item.status === 'refund_owed' && item.count ? 'text-red-700' : 'text-gray-900'">{{ item.count }}</span>
                </Link>
            </section>

            <section class="grid grid-cols-2 gap-4 lg:grid-cols-3">
                <div class="rounded bg-white p-4 shadow-sm">
                    <span class="block text-sm text-gray-500">Orders today</span>
                    <span class="text-2xl font-semibold text-gray-900">{{ today.count }}</span>
                </div>
                <div class="rounded bg-white p-4 shadow-sm">
                    <span class="block text-sm text-gray-500">Received, last 7 days</span>
                    <span class="text-2xl font-semibold text-gray-900">{{ formatPaise(revenue.last_7_days) }}</span>
                </div>
                <div class="rounded bg-white p-4 shadow-sm">
                    <span class="block text-sm text-gray-500">Received, last 30 days</span>
                    <span class="text-2xl font-semibold text-gray-900">{{ formatPaise(revenue.last_30_days) }}</span>
                </div>
            </section>

            <div class="grid gap-6 lg:grid-cols-2">
                <section class="overflow-x-auto rounded bg-white shadow-sm">
                    <h2 class="border-b px-4 py-3 font-medium text-gray-900">Today's orders</h2>
                    <table class="w-full text-left text-sm">
                        <tbody>
                            <tr v-for="order in today.orders" :key="order.id" class="border-b last:border-0">
                                <td class="px-4 py-2">
                                    <Link :href="route('admin.orders.show', order.id)" class="font-medium hover:underline">{{ order.number }}</Link>
                                </td>
                                <td class="px-4 py-2">{{ order.customer }}</td>
                                <td class="px-4 py-2">{{ formatPaise(order.total) }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ order.status }}</td>
                            </tr>
                            <tr v-if="today.orders.length === 0">
                                <td class="px-4 py-6 text-center text-gray-500">No orders yet today.</td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <section class="overflow-x-auto rounded bg-white shadow-sm">
                    <h2 class="border-b px-4 py-3 font-medium text-gray-900">Low stock (3 or fewer)</h2>
                    <table class="w-full text-left text-sm">
                        <tbody>
                            <tr v-for="variant in low_stock" :key="variant.id" class="border-b last:border-0">
                                <td class="px-4 py-2">
                                    <Link :href="route('admin.products.edit', variant.product_id)" class="font-medium hover:underline">{{ variant.name }}</Link>
                                    <span v-if="variant.variant" class="block text-xs text-gray-500">{{ variant.variant }}</span>
                                </td>
                                <td class="px-4 py-2 font-mono text-xs">{{ variant.sku }}</td>
                                <td class="px-4 py-2 font-medium" :class="variant.stock === 0 ? 'text-red-700' : 'text-gray-900'">{{ variant.stock }}</td>
                            </tr>
                            <tr v-if="low_stock.length === 0">
                                <td class="px-4 py-6 text-center text-gray-500">Everything is well stocked.</td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            </div>
        </div>
    </AdminLayout>
</template>
