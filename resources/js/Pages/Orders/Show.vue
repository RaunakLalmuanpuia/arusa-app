<script setup>
import { onMounted, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import StoreLayout from '@/Layouts/StoreLayout.vue';
import { formatPaise } from '@/money';

const props = defineProps({
    order: { type: Object, required: true },
    just_placed: { type: Boolean, required: true },
    pays_on_delivery: { type: Boolean, required: true },
    payment: { type: Object, default: null },
    payment_confirmed: { type: Boolean, required: true },
    payment_error: { type: String, default: null },
});

const page = usePage();
const paymentMessage = ref(props.payment_error);
const opening = ref(false);


/** Razorpay Checkout is loaded only on this page, only when someone pays. */
const loadCheckout = () =>
    new Promise((resolve, reject) => {
        if (window.Razorpay) {
            resolve();
            return;
        }

        const script = document.createElement('script');
        script.src = 'https://checkout.razorpay.com/v1/checkout.js';
        script.onload = resolve;
        script.onerror = () => {
            script.remove();
            reject(new Error('Razorpay Checkout could not load.'));
        };
        document.head.appendChild(script);
    });

const pay = async () => {
    opening.value = true;
    paymentMessage.value = null;

    try {
        await loadCheckout();
    } catch {
        paymentMessage.value = "The payment window couldn't load. Check your connection and try again.";
        opening.value = false;
        return;
    }

    const checkout = new window.Razorpay({
        key: props.payment.key,
        amount: props.payment.amount,
        currency: 'INR',
        order_id: props.payment.gateway_order_id,
        name: page.props.store.store_name,
        description: `Order ${props.order.number}`,
        prefill: props.payment.prefill,
        theme: { color: '#121110' },
        handler: (response) =>
            router.post(props.payment.verify_url, response, {
                preserveScroll: true,
                onFinish: () => {
                    opening.value = false;
                    paymentMessage.value = props.payment_error;
                },
            }),
        modal: {
            ondismiss: () => {
                opening.value = false;
                paymentMessage.value ??= 'Payment not completed. Your items stay reserved for 30 minutes.';
            },
        },
    });

    checkout.on('payment.failed', (response) => {
        paymentMessage.value = response.error?.description ?? 'The payment failed. Please try again.';
    });

    checkout.open();
};

onMounted(() => {
    if (props.payment && props.just_placed) {
        pay();
    }
});
</script>

<template>
    <Head :title="`Order ${order.number}`" />

    <StoreLayout>
        <div class="mx-auto max-w-2xl px-4 py-8 sm:py-12">
            <div v-if="payment_confirmed" class="mb-8 border border-brand-300 bg-brand-100 px-4 py-4" role="status">
                <p class="font-display text-2xl font-semibold">Payment received. Thank you, {{ order.customer_name }}.</p>
                <p class="mt-1 text-sm text-brand-700">
                    Your order is confirmed. We'll call {{ order.customer_phone }} before delivery. Bookmark this page to check on your order.
                </p>
            </div>
            <div v-else-if="just_placed && pays_on_delivery" class="mb-8 border border-brand-300 bg-brand-100 px-4 py-4" role="status">
                <p class="font-display text-2xl font-semibold">Thank you, {{ order.customer_name }}.</p>
                <p class="mt-1 text-sm text-brand-700">
                    We'll call {{ order.customer_phone }} within 24 hours to confirm your order. Please keep your phone on, as unconfirmed orders are cancelled after a day. Bookmark this page to check on your order.
                </p>
            </div>

            <section v-if="payment" class="mb-8 border border-ink px-4 py-5" aria-labelledby="pay-title">
                <h2 id="pay-title" class="font-display text-2xl font-semibold">Complete your payment</h2>
                <p class="mt-1 text-sm text-brand-700">Your items are reserved for 30 minutes. Pay now to confirm the order.</p>
                <p v-if="paymentMessage" class="mt-3 text-sm text-red-700" role="alert">{{ paymentMessage }}</p>
                <button
                    type="button"
                    class="mt-4 flex min-h-12 w-full items-center justify-center bg-ink text-xs font-medium uppercase tracking-[0.2em] text-brand-50 disabled:opacity-40 focus-ring"
                    :disabled="opening"
                    @click="pay"
                >
                    {{ opening ? 'Opening payment…' : `Pay ${formatPaise(payment.amount)}` }}
                </button>
            </section>
            <p v-else-if="paymentMessage" class="mb-8 border border-brand-300 bg-brand-100 px-4 py-3 text-sm" role="status">
                {{ paymentMessage }}
            </p>
            <p v-else-if="order.fulfilment_status === 'Cancelled' && order.payment_status === 'Failed'" class="mb-8 border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="status">
                This order was cancelled because the payment wasn't completed.
            </p>
            <p v-else-if="order.fulfilment_status === 'Cancelled' && order.payment_status === 'Refunded'" class="mb-8 border border-brand-300 bg-brand-100 px-4 py-3 text-sm" role="status">
                This order was cancelled and your payment has been refunded. Refunds reach your account in 5–7 working days.
            </p>

            <p class="text-xs uppercase tracking-[0.3em] text-brand-600">Order</p>
            <h1 class="mt-1 font-display text-3xl font-semibold tabular-nums">{{ order.number }}</h1>
            <p class="mt-1 text-sm text-brand-700">Placed {{ order.placed_at }}</p>

            <dl class="mt-6 grid grid-cols-2 gap-4 border-y border-brand-200 py-4 text-sm">
                <div>
                    <dt class="text-xs uppercase tracking-[0.2em] text-brand-600">Status</dt>
                    <dd class="mt-1 font-medium">{{ order.fulfilment_status }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-[0.2em] text-brand-600">Payment</dt>
                    <dd class="mt-1">{{ order.payment_method }} · {{ order.payment_status }}</dd>
                </div>
                <div v-if="order.tracking_note" class="col-span-2">
                    <dt class="text-xs uppercase tracking-[0.2em] text-brand-600">Delivery note</dt>
                    <dd class="mt-1 whitespace-pre-line">{{ order.tracking_note }}</dd>
                </div>
            </dl>

            <ul class="mt-6 space-y-3 text-sm">
                <li v-for="(item, index) in order.items" :key="index" class="flex justify-between gap-4">
                    <span>{{ item.name }}<span v-if="item.variant" class="text-brand-600"> · {{ item.variant }}</span> × {{ item.quantity }}</span>
                    <span class="tabular-nums">{{ formatPaise(item.line_total) }}</span>
                </li>
            </ul>
            <dl class="mt-4 space-y-1 border-t border-brand-200 pt-4 text-sm">
                <div class="flex justify-between"><dt>Subtotal</dt><dd class="tabular-nums">{{ formatPaise(order.subtotal) }}</dd></div>
                <div class="flex justify-between"><dt>Shipping</dt><dd class="tabular-nums">{{ order.shipping ? formatPaise(order.shipping) : 'Free' }}</dd></div>
                <div class="flex justify-between text-base font-medium"><dt>Total</dt><dd class="tabular-nums">{{ formatPaise(order.total) }}</dd></div>
            </dl>

            <div class="mt-8 text-sm">
                <h2 class="text-xs uppercase tracking-[0.2em] text-brand-600">Delivering to</h2>
                <address class="mt-1 not-italic">
                    {{ order.customer_name }}<br />
                    <template v-for="line in order.address" :key="line">{{ line }}<br /></template>
                </address>
            </div>

            <p v-if="page.props.store.whatsapp_number" class="mt-8 text-sm">
                Questions?
                <a :href="`https://wa.me/91${page.props.store.whatsapp_number}?text=${encodeURIComponent(`Hi, about order ${order.number}`)}`" target="_blank" rel="noopener" class="underline underline-offset-4">Message us on WhatsApp</a>
            </p>
        </div>
    </StoreLayout>
</template>
