<script setup>
import { Link } from '@inertiajs/vue3';
import { formatPaise } from '@/money';

defineProps({
    product: { type: Object, required: true },
    eager: { type: Boolean, default: false },
});
</script>

<template>
    <Link
        :href="route('products.show', product.slug)"
        prefetch
        class="group block rounded focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand-700"
    >
        <div class="relative aspect-[4/5] overflow-hidden bg-brand-100">
            <img
                v-if="product.image"
                :src="product.image.src"
                :srcset="product.image.srcset"
                sizes="(min-width: 1024px) 25vw, 50vw"
                :alt="product.image.alt"
                :loading="eager ? 'eager' : 'lazy'"
                class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-[1.03] motion-reduce:transition-none motion-reduce:group-hover:scale-100"
            />
            <img
                v-if="product.hover_image"
                :src="product.hover_image.src"
                :srcset="product.hover_image.srcset"
                sizes="(min-width: 1024px) 25vw, 50vw"
                alt=""
                loading="lazy"
                class="absolute inset-0 h-full w-full object-cover opacity-0 transition-opacity duration-300 motion-reduce:transition-none [@media(hover:hover)]:group-hover:opacity-100"
            />
            <span
                v-if="!product.in_stock"
                class="absolute left-3 top-3 border border-brand-50/40 bg-ink/85 px-3 py-1.5 text-[10px] font-medium uppercase tracking-[0.3em] text-brand-50"
            >
                Sold out
            </span>
        </div>
        <h3 class="mt-3 text-sm font-medium">{{ product.name }}</h3>
        <p class="mt-1 text-sm text-brand-700">
            <template v-if="product.has_price_range">From </template>{{ formatPaise(product.price_from) }}
        </p>
    </Link>
</template>
