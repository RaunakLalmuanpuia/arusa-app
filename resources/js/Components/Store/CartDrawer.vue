<script setup>
import { nextTick, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import IconClose from '@/Components/Icons/IconClose.vue';
import { isCartOpen } from '@/cartDrawer';
import { formatPaise } from '@/money';

const page = usePage();
const closeButton = ref(null);

watch(isCartOpen, async (open) => {
    document.body.classList.toggle('overflow-hidden', open);
    if (open) {
        await nextTick();
        closeButton.value?.focus();
    }
});

const close = () => {
    isCartOpen.value = false;
};

const variantLabel = (line) => [line.colour, line.size].filter(Boolean).join(' / ');

const focusRing = 'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-700';
</script>

<template>
    <div>
        <Transition
            enter-active-class="transition-opacity duration-200 motion-reduce:transition-none"
            enter-from-class="opacity-0"
            leave-active-class="transition-opacity duration-150 motion-reduce:transition-none"
            leave-to-class="opacity-0"
        >
            <div v-if="isCartOpen" class="fixed inset-0 z-40 bg-ink/40" aria-hidden="true" @click="close" />
        </Transition>
        <Transition
            enter-active-class="transition-transform duration-300 ease-out motion-reduce:transition-none"
            enter-from-class="translate-x-full"
            leave-active-class="transition-transform duration-200 ease-in motion-reduce:transition-none"
            leave-to-class="translate-x-full"
        >
            <aside
                v-if="isCartOpen"
                role="dialog"
                aria-modal="true"
                aria-labelledby="cart-drawer-title"
                class="fixed inset-y-0 right-0 z-50 flex w-full max-w-sm flex-col bg-brand-50 text-ink shadow-xl"
                @keydown.esc="close"
            >
                <div class="flex items-center justify-between border-b border-brand-200 px-4 py-3">
                    <h2 id="cart-drawer-title" class="text-xs font-medium uppercase tracking-[0.2em]">Your bag ({{ page.props.cart.count }})</h2>
                    <button ref="closeButton" type="button" class="inline-flex h-11 w-11 items-center justify-center" :class="focusRing" aria-label="Close bag" @click="close">
                        <IconClose class="h-6 w-6" />
                    </button>
                </div>

                <ul v-if="page.props.cart.lines.length" class="flex-1 divide-y divide-brand-200 overflow-y-auto px-4">
                    <li v-for="line in page.props.cart.lines" :key="line.variant_id" class="flex gap-3 py-4">
                        <div class="h-20 w-16 shrink-0 overflow-hidden bg-brand-100">
                            <img v-if="line.image" :src="line.image.src" :srcset="line.image.srcset" sizes="64px" :alt="line.image.alt" class="h-full w-full object-cover" />
                        </div>
                        <div class="flex-1 text-sm">
                            <p class="font-medium">{{ line.name }}</p>
                            <p v-if="variantLabel(line)" class="text-brand-600">{{ variantLabel(line) }}</p>
                            <p v-if="line.available" class="mt-1 tabular-nums">{{ line.quantity }} × {{ formatPaise(line.unit_price) }}</p>
                            <p v-else class="mt-1 text-red-700">No longer available</p>
                        </div>
                    </li>
                </ul>
                <p v-else class="flex-1 px-4 py-12 text-center text-brand-700">Your bag is empty.</p>

                <div v-if="page.props.cart.count" class="border-t border-brand-200 p-4">
                    <p class="flex justify-between text-sm">
                        <span>Subtotal</span>
                        <span class="tabular-nums">{{ formatPaise(page.props.cart.subtotal) }}</span>
                    </p>
                    <p class="mt-1 text-xs text-brand-600">Shipping is worked out at checkout.</p>
                    <div class="mt-4 grid gap-2">
                        <Link :href="route('checkout.create')" class="flex min-h-11 items-center justify-center bg-ink text-xs font-medium uppercase tracking-[0.2em] text-brand-50" :class="focusRing" @click="close">Checkout</Link>
                        <Link :href="route('cart.index')" class="flex min-h-11 items-center justify-center border border-ink text-xs font-medium uppercase tracking-[0.2em]" :class="focusRing" @click="close">View bag</Link>
                    </div>
                </div>
            </aside>
        </Transition>
    </div>
</template>
