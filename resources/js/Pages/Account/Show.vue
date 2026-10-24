<script setup>
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import PageHeading from '@/Components/Store/PageHeading.vue';
import Reveal from '@/Components/Store/Reveal.vue';
import StoreLayout from '@/Layouts/StoreLayout.vue';
import { formatPaise } from '@/money';

defineProps({
    orders: { type: Array, required: true },
    addresses: { type: Array, required: true },
});

const page = usePage();
const firstName = computed(() => page.props.auth.user.name.trim().split(/\s+/)[0]);

const removeAddress = (address) => {
    router.delete(route('account.addresses.destroy', address.id), {
        preserveScroll: true,
        onBefore: () => window.confirm('Remove this address?'),
    });
};
</script>

<template>
    <Head title="Your account" />

    <StoreLayout>
        <div class="mx-auto max-w-3xl px-4 py-8 sm:py-12">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <PageHeading eyebrow="Your account" :title="`Hello, ${firstName}`" />
                <div class="flex gap-6 text-sm">
                    <Link :href="route('profile.edit')" class="underline underline-offset-4 focus-ring">Profile & password</Link>
                    <Link :href="route('logout')" method="post" as="button" class="underline underline-offset-4 focus-ring">Log out</Link>
                </div>
            </div>

            <p v-if="$page.props.flash?.status" class="mt-6 text-sm text-brand-700" role="status">{{ $page.props.flash.status }}</p>

            <section class="mt-10" aria-labelledby="orders-title">
                <h2 id="orders-title" class="text-xs font-medium uppercase tracking-[0.2em]">Orders</h2>
                <ul v-if="orders.length" class="mt-4 divide-y divide-brand-200 border-y border-brand-200">
                    <Reveal v-for="(order, index) in orders" :key="order.number" as="li" :delay="Math.min(index, 4) * 75">
                        <a :href="order.url" class="flex min-h-14 items-center justify-between gap-4 py-3 text-sm hover:bg-brand-100 focus-ring">
                            <span>
                                <span class="font-medium tabular-nums">{{ order.number }}</span>
                                <span class="block text-brand-600">{{ order.placed_at }}</span>
                            </span>
                            <span class="flex flex-col items-end gap-1">
                                <span class="tabular-nums">{{ formatPaise(order.total) }}</span>
                                <span class="border border-brand-300 px-2 py-1 text-[10px] uppercase tracking-[0.2em] text-brand-700">{{ order.status }}</span>
                            </span>
                        </a>
                    </Reveal>
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
                        <button type="button" class="mt-2 min-h-11 underline underline-offset-4 focus-ring" @click="removeAddress(address)">Remove</button>
                    </li>
                </ul>
                <p v-else class="mt-4 text-sm text-brand-700">Addresses you check out with are saved here.</p>
            </section>
        </div>
    </StoreLayout>
</template>
