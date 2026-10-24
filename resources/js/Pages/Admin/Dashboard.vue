<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import BarChart from '@/Components/Admin/BarChart.vue';
import StatusBadge from '@/Components/Admin/StatusBadge.vue';
import IconChevronDown from '@/Components/Icons/IconChevronDown.vue';
import { formatPaise } from '@/money';

const props = defineProps({
    today: { type: Object, required: true },
    needs_action: { type: Array, required: true },
    low_stock: { type: Array, required: true },
    revenue: { type: Object, required: true },
});

const now = new Date();
const greeting = now.getHours() < 12 ? 'Good morning' : now.getHours() < 17 ? 'Good afternoon' : 'Good evening';
const todayLabel = now.toLocaleDateString('en-IN', { weekday: 'long', day: 'numeric', month: 'long' });

const isRefundOwed = (item) => item.filters.status === 'refund_owed' && item.count > 0;

const kpis = computed(() => [
    { label: 'Orders today', value: props.today.count },
    { label: 'Received, last 7 days', value: formatPaise(props.revenue.last_7_days) },
    { label: 'Received, last 30 days', value: formatPaise(props.revenue.last_30_days) },
]);
</script>

<template>
    <Head title="Admin" />

    <AdminLayout>
        <template #header>
            <p class="text-sm text-brand-500">{{ todayLabel }}</p>
            <h1 class="mt-1 font-display text-3xl font-semibold tracking-tight text-ink">{{ greeting }}</h1>
        </template>

        <div class="space-y-8">
            <section aria-labelledby="needs-action">
                <h2 id="needs-action" class="mb-3 text-sm font-medium text-brand-600">Needs action</h2>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                    <Link
                        v-for="item in needs_action"
                        :key="item.label"
                        :href="route('admin.orders.index', item.filters)"
                        class="admin-card focus-ring group relative flex flex-col gap-1 overflow-hidden p-4 transition-colors duration-150 hover:border-brand-400 motion-reduce:transition-none"
                    >
                        <span v-if="isRefundOwed(item)" class="absolute inset-y-0 left-0 w-1 bg-red-600" aria-hidden="true" />
                        <span class="text-2xl font-semibold tabular-nums" :class="item.count === 0 ? 'text-brand-300' : isRefundOwed(item) ? 'text-red-700' : 'text-ink'">{{ item.count }}</span>
                        <span class="flex items-center justify-between gap-2 text-sm text-brand-600">
                            {{ item.label }}
                            <IconChevronDown class="h-4 w-4 shrink-0 -rotate-90 text-brand-400 transition-transform duration-150 group-hover:translate-x-0.5 motion-reduce:transition-none" />
                        </span>
                    </Link>
                </div>
            </section>

            <section class="grid gap-3 sm:grid-cols-3">
                <div v-for="kpi in kpis" :key="kpi.label" class="admin-card p-5">
                    <p class="text-sm text-brand-600">{{ kpi.label }}</p>
                    <p class="mt-2 text-3xl font-semibold tracking-tight tabular-nums">{{ kpi.value }}</p>
                </div>
            </section>

            <section class="admin-card p-5">
                <div class="mb-5 flex items-baseline justify-between gap-3">
                    <h2 class="font-medium">Received, last 30 days</h2>
                    <span class="text-sm tabular-nums text-brand-500">{{ formatPaise(revenue.last_30_days) }}</span>
                </div>
                <BarChart :points="revenue.daily" :format="formatPaise" />
            </section>

            <div class="grid gap-6 lg:grid-cols-2">
                <section class="admin-card min-w-0">
                    <h2 class="border-b border-brand-200 px-5 py-4 font-medium">Today's orders</h2>
                    <ul v-if="today.orders.length" class="divide-y divide-brand-100">
                        <li v-for="order in today.orders" :key="order.id">
                            <Link :href="route('admin.orders.show', order.id)" class="focus-ring flex items-center gap-3 px-5 py-3 text-sm transition-colors duration-150 hover:bg-brand-50/60 motion-reduce:transition-none">
                                <span class="shrink-0 font-medium">{{ order.number }}</span>
                                <span class="min-w-0 flex-1 truncate text-brand-700">{{ order.customer }}</span>
                                <span class="tabular-nums">{{ formatPaise(order.total) }}</span>
                                <StatusBadge :value="order.status_value" :label="order.status" />
                            </Link>
                        </li>
                    </ul>
                    <p v-else class="px-5 py-10 text-center text-sm text-brand-500">No orders yet today.</p>
                </section>

                <section class="admin-card min-w-0">
                    <h2 class="border-b border-brand-200 px-5 py-4 font-medium">Low stock (3 or fewer)</h2>
                    <ul v-if="low_stock.length" class="divide-y divide-brand-100">
                        <li v-for="variant in low_stock" :key="variant.id">
                            <Link :href="route('admin.products.edit', variant.product_id)" class="focus-ring flex items-center gap-3 px-5 py-3 text-sm transition-colors duration-150 hover:bg-brand-50/60 motion-reduce:transition-none">
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-medium">{{ variant.name }}</span>
                                    <span v-if="variant.variant" class="block text-xs text-brand-500">{{ variant.variant }}</span>
                                </span>
                                <span class="hidden font-mono text-xs text-brand-500 sm:inline">{{ variant.sku }}</span>
                                <span
                                    class="whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium tabular-nums"
                                    :class="variant.stock === 0 ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-800'"
                                >
                                    {{ variant.stock === 0 ? 'Sold out' : `${variant.stock} left` }}
                                </span>
                            </Link>
                        </li>
                    </ul>
                    <p v-else class="px-5 py-10 text-center text-sm text-brand-500">Everything is well stocked.</p>
                </section>
            </div>
        </div>
    </AdminLayout>
</template>
