<script setup>
import { computed } from 'vue';
import ProductCard from '@/Components/Store/ProductCard.vue';
import StatusBadge from '@/Components/Admin/StatusBadge.vue';
import IconExternal from '@/Components/Icons/IconExternal.vue';
import { toPaise } from '@/money';

const props = defineProps({
    form: { type: Object, required: true },
    variants: { type: Array, default: () => [] },
    images: { type: Array, default: () => [] },
    viewUrl: { type: String, default: null },
});

// Mirrors Product::cardData(): active variants, effective price = price_override ?? base_price.
const card = computed(() => {
    const basePrice = toPaise(props.form.base_price) ?? 0;
    const activeVariants = props.variants.filter((variant) => variant.is_active);
    const prices = activeVariants.map((variant) => variant.price_override ?? basePrice);
    const name = props.form.name || 'Product name';
    const image = (index) => (props.images[index] ? { src: props.images[index].url, srcset: '', alt: props.images[index].alt || name } : null);

    return {
        name,
        slug: props.form.slug || 'preview',
        price_from: prices.length ? Math.min(...prices) : basePrice,
        has_price_range: new Set(prices).size > 1,
        in_stock: activeVariants.some((variant) => variant.stock > 0),
        image: image(0),
        hover_image: image(1),
    };
});
</script>

<template>
    <div class="admin-card p-5">
        <div class="flex items-center justify-between gap-2">
            <h2 class="text-sm font-medium">Preview</h2>
            <StatusBadge v-if="!form.is_active" value="inactive" label="Hidden from store" />
        </div>
        <div class="mt-4 rounded-lg bg-brand-50 p-4 text-ink">
            <!-- inert: the card is a real storefront link; the preview must not navigate or prefetch -->
            <div inert class="mx-auto max-w-[15rem]">
                <ProductCard :product="card" />
            </div>
        </div>
        <p class="mt-3 truncate font-mono text-xs text-brand-500">/products/{{ form.slug || '…' }}</p>
        <p class="mt-2 text-xs text-brand-500">Variant and photo changes show here once they are saved.</p>
        <a v-if="viewUrl" :href="viewUrl" target="_blank" rel="noopener" class="admin-btn-secondary mt-4 w-full">
            <IconExternal class="h-4 w-4" />
            View on store
        </a>
    </div>
</template>
