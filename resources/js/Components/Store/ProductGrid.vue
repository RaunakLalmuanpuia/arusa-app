<script setup>
import ProductCard from '@/Components/Store/ProductCard.vue';
import Reveal from '@/Components/Store/Reveal.vue';

defineProps({
    products: { type: Array, required: true },
    eagerCount: { type: Number, default: 2 },
});
</script>

<template>
    <ul class="grid grid-cols-2 gap-x-4 gap-y-8 lg:grid-cols-4">
        <Reveal v-for="(product, index) in products" :key="product.slug" as="li" :delay="(index % 4) * 75">
            <ProductCard :product="product" :eager="index < eagerCount" />
        </Reveal>
    </ul>
</template>
