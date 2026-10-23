<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import StoreLayout from '@/Layouts/StoreLayout.vue';
import { formatPaise } from '@/money';

defineProps({
    order: { type: Object, required: true },
    just_placed: { type: Boolean, required: true },
});

const page = usePage();
</script>

<template>
    <Head :title="`Order ${order.number}`" />

    <StoreLayout>
        <div class="mx-auto max-w-2xl px-4 py-8 sm:py-12">
            <div v-if="just_placed" class="mb-8 border border-brand-300 bg-brand-100 px-4 py-4" role="status">
                <p class="font-display text-2xl font-semibold">Thank you, {{ order.customer_name }}.</p>
                <p class="mt-1 text-sm text-brand-700">
                    Your order is confirmed. We'll call {{ order.customer_phone }} before delivery. Bookmark this page to check on your order.
                </p>
            </div>

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
