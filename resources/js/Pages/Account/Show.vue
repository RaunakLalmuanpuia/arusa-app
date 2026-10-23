<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import StoreLayout from '@/Layouts/StoreLayout.vue';
import { formatPaise } from '@/money';

defineProps({
    orders: { type: Array, required: true },
    addresses: { type: Array, required: true },
});

const removeAddress = (address) => {
    router.delete(route('account.addresses.destroy', address.id), {
        preserveScroll: true,
        onBefore: () => window.confirm('Remove this address?'),
    });
};

const focusRing = 'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-700';
</script>

<template>
    <Head title="Your account" />

    <StoreLayout>
        <div class="mx-auto max-w-3xl px-4 py-8 sm:py-12">
            <div class="flex flex-wrap items-baseline justify-between gap-4">
                <h1 class="font-display text-3xl font-semibold sm:text-4xl">Your account</h1>
                <div class="flex gap-6 text-sm">
                    <Link :href="route('profile.edit')" class="underline underline-offset-4" :class="focusRing">Profile & password</Link>
                    <Link :href="route('logout')" method="post" as="button" class="underline underline-offset-4" :class="focusRing">Log out</Link>
                </div>
            </div>

            <section class="mt-10" aria-labelledby="orders-title">
                <h2 id="orders-title" class="text-xs font-medium uppercase tracking-[0.2em]">Orders</h2>
                <ul v-if="orders.length" class="mt-4 divide-y divide-brand-200 border-y border-brand-200">
                    <li v-for="order in orders" :key="order.number">
                        <a :href="order.url" class="flex min-h-14 items-center justify-between gap-4 py-3 text-sm hover:bg-brand-100" :class="focusRing">
                            <span>
                                <span class="font-medium tabular-nums">{{ order.number }}</span>
                                <span class="block text-brand-600">{{ order.placed_at }}</span>
                            </span>
                            <span class="text-right">
                                <span class="block tabular-nums">{{ formatPaise(order.total) }}</span>
                                <span class="block text-brand-600">{{ order.status }}</span>
                            </span>
                        </a>
                    </li>
                </ul>
                <p v-else class="mt-4 text-sm text-brand-700">No orders yet.</p>
            </section>

            <section class="mt-10" aria-labelledby="addresses-title">
                <h2 id="addresses-title" class="text-xs font-medium uppercase tracking-[0.2em]">Saved addresses</h2>
                <ul v-if="addresses.length" class="mt-4 grid gap-4 sm:grid-cols-2">
                    <li v-for="address in addresses" :key="address.id" class="border border-brand-200 p-4 text-sm">
                        <address class="not-italic">
                            {{ address.name }} · {{ address.phone }}<br />
                            {{ address.line1 }}<br />
                            {{ address.locality }}, {{ address.district }} {{ address.pincode }}
                        </address>
                        <button type="button" class="mt-2 min-h-11 underline underline-offset-4" :class="focusRing" @click="removeAddress(address)">Remove</button>
                    </li>
                </ul>
                <p v-else class="mt-4 text-sm text-brand-700">Addresses you check out with are saved here.</p>
            </section>
        </div>
    </StoreLayout>
</template>
