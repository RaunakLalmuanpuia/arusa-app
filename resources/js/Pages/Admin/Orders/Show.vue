<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import FormField from '@/Components/Admin/FormField.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import StatusBadge from '@/Components/Admin/StatusBadge.vue';
import IconAlert from '@/Components/Icons/IconAlert.vue';
import IconChat from '@/Components/Icons/IconChat.vue';
import IconCheck from '@/Components/Icons/IconCheck.vue';
import IconDocument from '@/Components/Icons/IconDocument.vue';
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

// Must match FulfilmentStatus::next() order.
const steps = [
    { value: 'pending', label: 'Pending' },
    { value: 'confirmed', label: 'Confirmed' },
    { value: 'packed', label: 'Packed' },
    { value: 'dispatched', label: 'Dispatched' },
    { value: 'delivered', label: 'Delivered' },
];

const currentStep = computed(() => steps.findIndex((step) => step.value === props.order.fulfilment_status_value));
const isStepDone = (index) => index < currentStep.value || (index === currentStep.value && steps[index].value === 'delivered');

const nextHints = {
    confirmed: 'Call the customer to confirm, then mark the order confirmed.',
    packed: 'Pack the items, then mark the order packed.',
    dispatched: 'Hand the parcel over, add a tracking note if there is one, then mark it dispatched.',
    delivered: 'Mark it delivered once the customer has the parcel.',
};

const nextHint = computed(() => (props.next_status ? nextHints[props.next_status.value] : 'Waiting for payment before this order can move on.'));
</script>

<template>
    <Head :title="order.number" />

    <AdminLayout>
        <template #header>
            <PageHeader :title="order.number" :back="{ label: 'Orders', href: route('admin.orders.index') }" :meta="`${order.placed_at} · ${order.payment_method}`">
                <template #badges>
                    <StatusBadge :value="order.fulfilment_status_value" :label="order.fulfilment_status" />
                    <StatusBadge :value="order.payment_status_value" :label="order.payment_status" />
                </template>
                <template #actions>
                    <a :href="route('admin.orders.slip', order.id)" target="_blank" rel="noopener" class="admin-btn-secondary">
                        <IconDocument class="h-4 w-4" />
                        Print slip
                    </a>
                    <a :href="whatsapp_url" target="_blank" rel="noopener" class="admin-btn-secondary">
                        <IconChat class="h-4 w-4" />
                        Message customer
                    </a>
                </template>
            </PageHeader>
        </template>

        <div class="space-y-6">
            <div v-if="order.fulfilment_status_value === 'cancelled'" class="flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <IconAlert class="h-5 w-5 shrink-0" />
                This order was cancelled.
            </div>
            <ol v-else class="admin-card grid grid-cols-5 px-2 py-4" aria-label="Order progress">
                <li
                    v-for="(step, index) in steps"
                    :key="step.value"
                    class="flex flex-col items-center gap-2 text-center text-xs"
                    :aria-current="index === currentStep ? 'step' : null"
                >
                    <span class="relative flex w-full items-center justify-center">
                        <span
                            v-if="index > 0"
                            class="absolute right-1/2 top-1/2 h-0.5 w-full -translate-y-1/2"
                            :class="index <= currentStep ? 'bg-ink' : 'bg-brand-200'"
                            aria-hidden="true"
                        />
                        <span
                            class="relative flex h-7 w-7 items-center justify-center rounded-full border-2"
                            :class="isStepDone(index) ? 'border-ink bg-ink text-brand-50' : index === currentStep ? 'border-ink bg-white text-ink ring-4 ring-brand-100' : 'border-brand-200 bg-white text-brand-300'"
                        >
                            <IconCheck v-if="isStepDone(index)" class="h-4 w-4" />
                            <span v-else class="text-[11px] font-semibold">{{ index + 1 }}</span>
                        </span>
                    </span>
                    <span :class="index <= currentStep ? 'font-medium text-ink' : 'text-brand-400'">{{ step.label }}</span>
                </li>
            </ol>

            <p v-if="next_status?.value === 'confirmed'" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status">
                Call <a :href="`tel:+91${order.customer_phone}`" class="font-medium underline">{{ order.customer_phone }}</a> to confirm this cash-on-delivery order before packing it.
                It is cancelled automatically 24 hours after it was placed if nobody confirms it.
            </p>

            <section v-if="next_status || can_cancel" class="admin-card flex flex-wrap items-end justify-between gap-4 p-5">
                <div class="min-w-0 max-w-md">
                    <h2 class="font-medium">Next step</h2>
                    <p class="mt-1 text-sm text-brand-600">{{ nextHint }}</p>
                </div>
                <div class="flex flex-wrap items-end gap-2">
                    <FormField v-if="next_status?.value === 'dispatched'" label="Tracking note (optional)" for="tracking_note">
                        <input id="tracking_note" v-model="trackingNote" type="text" maxlength="500" placeholder="Bus parcel, receipt no." class="admin-field sm:w-72" />
                    </FormField>
                    <button v-if="next_status" type="button" :disabled="isBusy" class="admin-btn" @click="advance">
                        {{ next_status.value === 'confirmed' ? 'Confirmed by phone' : `Mark ${next_status.label}` }}
                    </button>
                    <button v-if="can_cancel" type="button" :disabled="isBusy" class="admin-btn-danger" @click="cancel">Cancel order</button>
                </div>
            </section>

            <div class="grid gap-6 lg:grid-cols-3">
                <section class="admin-card lg:col-span-2">
                    <h2 class="border-b border-brand-200 px-5 py-4 font-medium">Items</h2>
                    <ul class="divide-y divide-brand-100">
                        <li v-for="item in order.items" :key="item.id" class="flex items-start gap-4 px-5 py-3 text-sm">
                            <div class="min-w-0 flex-1">
                                <p class="font-medium">{{ item.name }}</p>
                                <p v-if="item.variant" class="text-xs text-brand-500">{{ item.variant }}</p>
                            </div>
                            <p class="whitespace-nowrap tabular-nums text-brand-600">{{ item.quantity }} × {{ formatPaise(item.unit_price) }}</p>
                            <p class="w-20 text-right font-medium tabular-nums">{{ formatPaise(item.line_total) }}</p>
                        </li>
                    </ul>
                    <dl class="space-y-2 border-t border-brand-200 px-5 py-4 text-sm">
                        <div class="flex justify-between text-brand-600">
                            <dt>Subtotal</dt>
                            <dd class="tabular-nums">{{ formatPaise(order.subtotal) }}</dd>
                        </div>
                        <div class="flex justify-between text-brand-600">
                            <dt>Shipping ({{ order.zone }})</dt>
                            <dd class="tabular-nums">{{ order.shipping ? formatPaise(order.shipping) : 'Free' }}</dd>
                        </div>
                        <div class="flex justify-between border-t border-brand-100 pt-2 text-base font-semibold">
                            <dt>Total</dt>
                            <dd class="tabular-nums">{{ formatPaise(order.total) }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="admin-card space-y-5 p-5 text-sm">
                    <div>
                        <h2 class="text-xs font-medium uppercase tracking-wide text-brand-500">Customer</h2>
                        <p class="mt-2 font-medium">{{ order.customer_name }}</p>
                        <a :href="`tel:${order.customer_phone}`" class="focus-ring block rounded text-brand-700 hover:underline">{{ order.customer_phone }}</a>
                        <p v-if="order.customer_email" class="break-all text-brand-700">{{ order.customer_email }}</p>
                    </div>
                    <div>
                        <h2 class="text-xs font-medium uppercase tracking-wide text-brand-500">Ship to</h2>
                        <p v-for="line in order.address" :key="line" class="mt-1 text-brand-700 first-of-type:mt-2">{{ line }}</p>
                    </div>
                    <div v-if="order.tracking_note">
                        <h2 class="text-xs font-medium uppercase tracking-wide text-brand-500">Tracking</h2>
                        <p class="mt-2 text-brand-700">{{ order.tracking_note }}</p>
                    </div>
                </section>
            </div>

            <section v-if="payments.length" class="admin-card overflow-x-auto">
                <h2 class="border-b border-brand-200 px-5 py-4 font-medium">Payment attempts</h2>
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-brand-200 bg-brand-50/60 text-xs uppercase tracking-wide text-brand-500">
                        <tr>
                            <th scope="col" class="px-5 py-2 font-medium">When</th>
                            <th scope="col" class="px-5 py-2 font-medium">Razorpay ids</th>
                            <th scope="col" class="px-5 py-2 font-medium">Amount</th>
                            <th scope="col" class="px-5 py-2 font-medium">Status</th>
                            <th scope="col" class="px-5 py-2"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-100">
                        <tr v-for="payment in payments" :key="payment.id">
                            <td class="whitespace-nowrap px-5 py-3">{{ payment.created_at }}</td>
                            <td class="px-5 py-3 font-mono text-xs">
                                {{ payment.gateway_payment_id ?? '—' }}
                                <span class="block text-brand-500">{{ payment.gateway_order_id }}</span>
                            </td>
                            <td class="px-5 py-3 tabular-nums">{{ formatPaise(payment.amount) }}</td>
                            <td class="px-5 py-3"><StatusBadge :value="payment.status_value" :label="payment.status" /></td>
                            <td class="px-5 py-3 text-right">
                                <button v-if="payment.refundable" type="button" :disabled="isBusy" class="admin-btn-danger min-h-8 px-3" @click="refund(payment)">Refund</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="admin-card p-5">
                <h2 class="font-medium">Timeline</h2>
                <ol class="mt-4">
                    <li v-for="(event, index) in events" :key="event.id" class="relative flex gap-4 pb-6 last:pb-0">
                        <span v-if="index < events.length - 1" class="absolute left-[4.5px] top-4 h-full w-px bg-brand-200" aria-hidden="true" />
                        <span class="relative mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full" :class="index === events.length - 1 ? 'bg-ink ring-4 ring-brand-100' : 'bg-brand-300'" aria-hidden="true" />
                        <div class="min-w-0 text-sm">
                            <div class="flex flex-wrap gap-2">
                                <StatusBadge :value="event.fulfilment_status_value" :label="event.fulfilment_status" />
                                <StatusBadge :value="event.payment_status_value" :label="event.payment_status" />
                            </div>
                            <p class="mt-1 text-xs text-brand-500">
                                {{ event.at }}<template v-if="event.by"> · by {{ event.by }}</template>
                            </p>
                        </div>
                    </li>
                </ol>
            </section>
        </div>
    </AdminLayout>
</template>
