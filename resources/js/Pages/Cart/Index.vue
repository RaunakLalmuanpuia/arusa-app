<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import PageHeading from '@/Components/Store/PageHeading.vue';
import Reveal from '@/Components/Store/Reveal.vue';
import StoreLayout from '@/Layouts/StoreLayout.vue';
import { formatPaise } from '@/money';

const page = usePage();

const setQuantity = (line, quantity) => {
    router.patch(route('cart.update', line.variant_id), { quantity }, { preserveScroll: true });
};

const remove = (line) => {
    router.delete(route('cart.destroy', line.variant_id), { preserveScroll: true });
};

const variantLabel = (line) => [line.colour, line.size].filter(Boolean).join(' / ');

const stepper = 'inline-flex h-11 w-11 items-center justify-center border border-brand-300 disabled:opacity-40';
</script>

<template>
    <Head title="Your bag" />

    <StoreLayout>
        <div class="mx-auto max-w-3xl px-4 py-8 sm:py-12">
            <PageHeading title="Your bag" />

            <template v-if="page.props.cart.lines.length">
                <p v-if="page.props.cart.has_unavailable" class="mt-6 border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                    Some items are no longer available. Remove them to check out.
                </p>

                <ul class="mt-6 divide-y divide-brand-200 border-y border-brand-200">
                    <Reveal
                        v-for="(line, index) in page.props.cart.lines"
                        :key="line.variant_id"
                        as="li"
                        :delay="Math.min(index, 4) * 75"
                        class="flex gap-4 py-5"
                    >
                        <Link :href="route('products.show', line.slug)" class="h-28 w-24 shrink-0 overflow-hidden bg-brand-100 focus-ring">
                            <img v-if="line.image" :src="line.image.src" :srcset="line.image.srcset" sizes="96px" :alt="line.image.alt" class="h-full w-full object-cover" />
                        </Link>
                        <div class="flex flex-1 flex-col gap-2 text-sm sm:flex-row sm:justify-between">
                            <div>
                                <Link :href="route('products.show', line.slug)" class="font-medium hover:underline focus-ring">{{ line.name }}</Link>
                                <p v-if="variantLabel(line)" class="text-brand-600">{{ variantLabel(line) }}</p>
                                <p class="mt-1 tabular-nums">{{ formatPaise(line.unit_price) }}</p>
                                <p v-if="!line.available" class="mt-1 text-red-700">No longer available</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <div v-if="line.available" class="flex items-center">
                                    <button type="button" :class="[stepper, 'focus-ring']" :aria-label="`One fewer ${line.name}`" @click="setQuantity(line, line.quantity - 1)">−</button>
                                    <span class="w-10 text-center tabular-nums" aria-live="polite">{{ line.quantity }}</span>
                                    <button type="button" :class="[stepper, 'focus-ring']" :aria-label="`One more ${line.name}`" :disabled="line.quantity >= line.max_quantity" @click="setQuantity(line, line.quantity + 1)">+</button>
                                </div>
                                <button type="button" class="min-h-11 text-sm underline underline-offset-4 focus-ring" @click="remove(line)">Remove</button>
                            </div>
                        </div>
                    </Reveal>
                </ul>

                <div class="mt-6 flex flex-col items-stretch gap-4 sm:items-end">
                    <p class="flex justify-between gap-12 text-base sm:justify-end">
                        <span>Subtotal</span>
                        <span class="tabular-nums">{{ formatPaise(page.props.cart.subtotal) }}</span>
                    </p>
                    <p class="text-xs text-brand-600">Shipping is worked out at checkout from your pincode.</p>
                    <div class="flex flex-col-reverse items-stretch gap-4 sm:flex-row sm:items-center">
                        <Link :href="route('home')" class="store-link inline-flex min-h-11 items-center justify-center">Continue shopping</Link>
                        <Link
                            v-if="!page.props.cart.has_unavailable && page.props.cart.count"
                            :href="route('checkout.create')"
                            class="flex min-h-12 items-center justify-center bg-ink px-12 text-xs font-medium uppercase tracking-[0.2em] text-brand-50 focus-ring"
                        >
                            Checkout
                        </Link>
                    </div>
                </div>
            </template>

            <div v-else class="mt-12 flex flex-col items-center text-center">
                <ApplicationLogo class="h-16 w-16" />
                <p class="mt-6 text-brand-700">Your bag is empty.</p>
                <Link :href="route('home') + '#new-in'" class="store-link mt-4 inline-flex min-h-11 items-center">Shop new in</Link>
            </div>
        </div>
    </StoreLayout>
</template>
