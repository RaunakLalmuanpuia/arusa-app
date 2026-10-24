<script setup>
import { computed, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import StoreLayout from '@/Layouts/StoreLayout.vue';
import InputError from '@/Components/InputError.vue';
import PageHeading from '@/Components/Store/PageHeading.vue';
import { formatPaise } from '@/money';

const props = defineProps({
    prefill: { type: Object, required: true },
    districts: { type: Array, required: true },
    quote: { type: Object, default: null },
    online_payment: { type: Boolean, required: true },
});

const page = usePage();

const form = useForm({ ...props.prefill, payment_method: 'cod' });

watch(
    () => form.pincode,
    (pincode) => {
        if (/^\d{6}$/.test(pincode)) {
            router.reload({ only: ['quote'], data: { pincode } });
        }
    },
    { immediate: true },
);

const liveQuote = computed(() => (props.quote?.pincode === form.pincode ? props.quote : null));

watch(liveQuote, (quote) => {
    if (quote?.deliverable && !quote.cod_available && props.online_payment) {
        form.payment_method = 'razorpay';
    }
});

const methodAvailable = computed(() => (form.payment_method === 'cod' ? liveQuote.value?.cod_available : props.online_payment));

const canPlaceOrder = computed(() => liveQuote.value?.deliverable && methodAvailable.value && !form.processing);

const buttonLabel = computed(() => {
    if (form.processing) {
        return 'Placing order…';
    }

    if (!liveQuote.value?.deliverable) {
        return 'Enter your pincode';
    }

    return `${form.payment_method === 'razorpay' ? 'Continue to payment' : 'Place order'} · ${formatPaise(liveQuote.value.total)}`;
});

const submit = () => form.post(route('checkout.store'));

const variantLabel = (line) => [line.colour, line.size].filter(Boolean).join(' / ');
</script>

<template>
    <Head title="Checkout" />

    <StoreLayout>
        <div class="mx-auto grid max-w-5xl gap-10 px-4 py-8 sm:py-12 md:grid-cols-[1fr_20rem]">
            <form class="space-y-6" novalidate @submit.prevent="submit">
                <PageHeading title="Checkout" />

                <p v-if="form.errors.cart" class="border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                    {{ form.errors.cart }} <Link :href="route('cart.index')" class="underline">Review your bag</Link>
                </p>

                <fieldset class="space-y-4">
                    <legend class="font-display text-xl font-semibold"><span class="mr-3 text-brand-500 tabular-nums" aria-hidden="true">01</span>Contact</legend>
                    <div>
                        <label class="store-label" for="name">Full name</label>
                        <input class="store-field" id="name" v-model="form.name" autocomplete="name" required />
                        <InputError class="mt-1" :message="form.errors.name" />
                    </div>
                    <div>
                        <label class="store-label" for="phone">Mobile number</label>
                        <input class="store-field" id="phone" v-model="form.phone" type="tel" inputmode="numeric" autocomplete="tel-national" maxlength="10" placeholder="10 digits" required />
                        <InputError class="mt-1" :message="form.errors.phone" />
                    </div>
                    <div>
                        <label class="store-label" for="email">Email (optional, for your receipt)</label>
                        <input class="store-field" id="email" v-model="form.email" type="email" autocomplete="email" />
                        <InputError class="mt-1" :message="form.errors.email" />
                    </div>
                </fieldset>

                <fieldset class="space-y-4">
                    <legend class="font-display text-xl font-semibold"><span class="mr-3 text-brand-500 tabular-nums" aria-hidden="true">02</span>Delivery address</legend>
                    <div>
                        <label class="store-label" for="line1">House, building, street</label>
                        <input class="store-field" id="line1" v-model="form.line1" autocomplete="address-line1" required />
                        <InputError class="mt-1" :message="form.errors.line1" />
                    </div>
                    <div>
                        <label class="store-label" for="locality">Locality / veng</label>
                        <input class="store-field" id="locality" v-model="form.locality" autocomplete="address-level3" required />
                        <InputError class="mt-1" :message="form.errors.locality" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="store-label" for="district">District</label>
                            <select class="store-field" id="district" v-model="form.district" required>
                                <option v-for="district in districts" :key="district" :value="district">{{ district }}</option>
                            </select>
                            <InputError class="mt-1" :message="form.errors.district" />
                        </div>
                        <div>
                            <label class="store-label" for="pincode">Pincode</label>
                            <input class="store-field" id="pincode" v-model="form.pincode" inputmode="numeric" autocomplete="postal-code" maxlength="6" required />
                            <InputError class="mt-1" :message="form.errors.pincode" />
                            <p v-if="liveQuote && !liveQuote.deliverable" class="mt-1 text-sm text-red-700" role="status">We don't deliver here yet.</p>
                            <p v-else-if="liveQuote?.deliverable" class="mt-1 text-sm text-brand-700" role="status">Delivering to {{ liveQuote.zone }}.</p>
                        </div>
                    </div>
                </fieldset>

                <fieldset class="space-y-3">
                    <legend class="font-display text-xl font-semibold"><span class="mr-3 text-brand-500 tabular-nums" aria-hidden="true">03</span>Payment</legend>
                    <label class="flex min-h-11 items-center gap-3 border border-brand-300 px-4" :class="{ 'opacity-50': liveQuote?.deliverable && !liveQuote.cod_available }">
                        <input v-model="form.payment_method" type="radio" value="cod" class="text-ink focus:ring-ink" :disabled="liveQuote?.deliverable && !liveQuote.cod_available" />
                        <span>Cash on delivery</span>
                    </label>
                    <label v-if="online_payment" class="flex min-h-11 items-center gap-3 border border-brand-300 px-4">
                        <input v-model="form.payment_method" type="radio" value="razorpay" class="text-ink focus:ring-ink" />
                        <span>Pay online <span class="text-brand-600">· UPI, cards, netbanking</span></span>
                    </label>
                    <p v-if="liveQuote?.deliverable && !liveQuote.cod_available" class="text-sm text-red-700" role="status">
                        Cash on delivery isn't available for this order.<template v-if="online_payment"> Pay online instead.</template>
                    </p>
                    <InputError :message="form.errors.payment_method" />
                </fieldset>

                <button
                    type="submit"
                    class="flex min-h-12 w-full items-center justify-center bg-ink text-xs font-medium uppercase tracking-[0.2em] text-brand-50 disabled:opacity-40 focus-ring"
                    :disabled="!canPlaceOrder"
                >
                    {{ buttonLabel }}
                </button>
                <p class="text-center text-xs text-brand-600">
                    <template v-if="!(liveQuote?.deliverable && !liveQuote.cod_available)">Cash on delivery · </template><template v-if="online_payment">Secure online payment · </template>Delivered across Mizoram
                </p>
            </form>

            <aside class="h-fit border border-brand-200 p-4 md:sticky md:top-24" aria-labelledby="summary-title">
                <h2 id="summary-title" class="text-xs font-medium uppercase tracking-[0.2em]">Order summary</h2>
                <ul class="mt-4 space-y-3 text-sm">
                    <li v-for="line in page.props.cart.lines" :key="line.variant_id" class="flex gap-3">
                        <div class="h-[60px] w-12 shrink-0 overflow-hidden bg-brand-100">
                            <img v-if="line.image" :src="line.image.src" :srcset="line.image.srcset" sizes="48px" alt="" loading="lazy" class="h-full w-full object-cover" />
                        </div>
                        <div class="flex flex-1 justify-between gap-4">
                            <span>{{ line.name }}<span v-if="variantLabel(line)" class="text-brand-600"> · {{ variantLabel(line) }}</span> × {{ line.quantity }}</span>
                            <span class="tabular-nums">{{ formatPaise(line.line_total) }}</span>
                        </div>
                    </li>
                </ul>
                <dl class="mt-4 space-y-1 border-t border-brand-200 pt-4 text-sm">
                    <div class="flex justify-between"><dt>Subtotal</dt><dd class="tabular-nums">{{ formatPaise(page.props.cart.subtotal) }}</dd></div>
                    <div class="flex justify-between">
                        <dt>Shipping</dt>
                        <dd class="tabular-nums">{{ liveQuote?.deliverable ? (liveQuote.shipping ? formatPaise(liveQuote.shipping) : 'Free') : '—' }}</dd>
                    </div>
                    <div class="flex justify-between text-base font-medium">
                        <dt>Total</dt>
                        <dd class="tabular-nums">{{ formatPaise(liveQuote?.deliverable ? liveQuote.total : page.props.cart.subtotal) }}</dd>
                    </div>
                </dl>
            </aside>
        </div>
    </StoreLayout>
</template>
