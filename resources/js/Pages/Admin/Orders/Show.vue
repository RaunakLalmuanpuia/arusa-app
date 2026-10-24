<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatPaise } from '@/money';

const props = defineProps({
    order: { type: Object, required: true },
    payments: { type: Array, required: true },
    events: { type: Array, required: true },
    next_status: { type: Object, default: null },
    can_cancel: { type: Boolean, required: true },
    whatsapp_url: { type: String, required: true },
});

const trackingNote = ref(props.order.tracking_note ?? '');
const isBusy = ref(false);
const options = { preserveScroll: true, onStart: () => (isBusy.value = true), onFinish: () => (isBusy.value = false) };

const advance = () => {
    router.put(
        route('admin.orders.status.update', props.order.id),
        { status: props.next_status.value, tracking_note: props.next_status.value === 'dispatched' ? trackingNote.value || null : null },
        options,
    );
};

const cancel = () => {
    if (window.confirm(`Cancel ${props.order.number}? Stock goes back, and a paid online order is refunded.`)) {
        router.post(route('admin.orders.cancel', props.order.id), {}, options);
    }
};

const refund = (payment) => {
    if (window.confirm(`Refund ${formatPaise(payment.amount)} for ${payment.gateway_payment_id}?`)) {
        router.post(route('admin.orders.payments.refund', [props.order.id, payment.id]), {}, options);
    }
};
</script>

<template>
    <Head :title="order.number" />

    <AdminLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <Link :href="route('admin.orders.index')" class="text-sm text-gray-500 hover:underline">← Orders</Link>
                    <h1 class="text-xl font-semibold text-gray-900">{{ order.number }}</h1>
                    <p class="text-sm text-gray-600">
                        {{ order.placed_at }} · {{ order.fulfilment_status }} · {{ order.payment_method }} ({{ order.payment_status }})
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a :href="route('admin.orders.slip', order.id)" target="_blank" rel="noopener" class="rounded border bg-white px-3 py-2 text-sm font-medium text-gray-700">Print slip</a>
                    <a :href="whatsapp_url" target="_blank" rel="noopener" class="rounded border bg-white px-3 py-2 text-sm font-medium text-gray-700">Message customer</a>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <p
                v-for="key in ['status', 'order', 'payment']"
                v-show="$page.props.errors[key]"
                :key="key"
                class="rounded bg-red-50 px-4 py-2 text-sm text-red-800"
                role="alert"
            >
                {{ $page.props.errors[key] }}
            </p>

            <p v-if="next_status?.value === 'confirmed'" class="rounded bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status">
                Call <a :href="`tel:+91${order.customer_phone}`" class="font-medium underline">{{ order.customer_phone }}</a> to confirm this cash-on-delivery order before packing it.
                It is cancelled automatically 24 hours after it was placed if nobody confirms it.
            </p>

            <section v-if="next_status || can_cancel" class="flex flex-wrap items-end gap-3 rounded bg-white p-4 shadow-sm">
                <label v-if="next_status?.value === 'dispatched'" class="block text-sm text-gray-700">
                    Tracking note (optional)
                    <input
                        v-model="trackingNote"
                        type="text"
                        maxlength="500"
                        placeholder="Bus parcel, receipt no."
                        class="mt-1 block w-72 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                </label>
                <button
                    v-if="next_status"
                    type="button"
                    :disabled="isBusy"
                    class="rounded bg-gray-800 px-3 py-2 text-sm font-medium text-white disabled:opacity-50"
                    @click="advance"
                >
                    {{ next_status.value === 'confirmed' ? 'Confirmed by phone' : `Mark ${next_status.label}` }}
                </button>
                <button v-if="can_cancel" type="button" :disabled="isBusy" class="rounded px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50 disabled:opacity-50" @click="cancel">
                    Cancel order
                </button>
            </section>

            <div class="grid gap-6 lg:grid-cols-3">
                <section class="rounded bg-white shadow-sm lg:col-span-2">
                    <h2 class="border-b px-4 py-3 font-medium text-gray-900">Items</h2>
                    <table class="w-full text-left text-sm">
                        <tbody>
                            <tr v-for="item in order.items" :key="item.id" class="border-b">
                                <td class="px-4 py-3">
                                    {{ item.name }}
                                    <span v-if="item.variant" class="block text-xs text-gray-500">{{ item.variant }}</span>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ item.quantity }} × {{ formatPaise(item.unit_price) }}</td>
                                <td class="px-4 py-3 text-right">{{ formatPaise(item.line_total) }}</td>
                            </tr>
                            <tr class="border-b text-gray-600">
                                <td class="px-4 py-2" colspan="2">Subtotal</td>
                                <td class="px-4 py-2 text-right">{{ formatPaise(order.subtotal) }}</td>
                            </tr>
                            <tr class="border-b text-gray-600">
                                <td class="px-4 py-2" colspan="2">Shipping ({{ order.zone }})</td>
                                <td class="px-4 py-2 text-right">{{ order.shipping ? formatPaise(order.shipping) : 'Free' }}</td>
                            </tr>
                            <tr class="font-medium">
                                <td class="px-4 py-3" colspan="2">Total</td>
                                <td class="px-4 py-3 text-right">{{ formatPaise(order.total) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <section class="space-y-1 rounded bg-white p-4 text-sm shadow-sm">
                    <h2 class="mb-2 font-medium text-gray-900">Customer</h2>
                    <p>{{ order.customer_name }}</p>
                    <p><a :href="`tel:${order.customer_phone}`" class="hover:underline">{{ order.customer_phone }}</a></p>
                    <p v-if="order.customer_email">{{ order.customer_email }}</p>
                    <p v-for="line in order.address" :key="line" class="text-gray-600">{{ line }}</p>
                    <p v-if="order.tracking_note" class="pt-2 text-gray-600"><span class="font-medium text-gray-900">Tracking:</span> {{ order.tracking_note }}</p>
                </section>
            </div>

            <section v-if="payments.length" class="overflow-x-auto rounded bg-white shadow-sm">
                <h2 class="border-b px-4 py-3 font-medium text-gray-900">Payment attempts</h2>
                <table class="w-full text-left text-sm">
                    <thead class="border-b text-gray-500">
                        <tr>
                            <th class="px-4 py-2 font-medium">When</th>
                            <th class="px-4 py-2 font-medium">Razorpay ids</th>
                            <th class="px-4 py-2 font-medium">Amount</th>
                            <th class="px-4 py-2 font-medium">Status</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="payment in payments" :key="payment.id" class="border-b last:border-0">
                            <td class="px-4 py-2">{{ payment.created_at }}</td>
                            <td class="px-4 py-2 font-mono text-xs">
                                {{ payment.gateway_payment_id ?? '—' }}
                                <span class="block text-gray-500">{{ payment.gateway_order_id }}</span>
                            </td>
                            <td class="px-4 py-2">{{ formatPaise(payment.amount) }}</td>
                            <td class="px-4 py-2">{{ payment.status }}</td>
                            <td class="px-4 py-2 text-right">
                                <button v-if="payment.refundable" type="button" :disabled="isBusy" class="text-red-600 hover:underline disabled:opacity-50" @click="refund(payment)">
                                    Refund
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="rounded bg-white p-4 shadow-sm">
                <h2 class="mb-3 font-medium text-gray-900">Timeline</h2>
                <ol class="space-y-2 text-sm">
                    <li v-for="event in events" :key="event.id" class="flex flex-wrap gap-x-3">
                        <span class="text-gray-500">{{ event.at }}</span>
                        <span>{{ event.fulfilment_status }} · {{ event.payment_status }}</span>
                        <span v-if="event.by" class="text-gray-500">by {{ event.by }}</span>
                    </li>
                </ol>
            </section>
        </div>
    </AdminLayout>
</template>
