<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import StoreLayout from '@/Layouts/StoreLayout.vue';
import ProductGrid from '@/Components/Store/ProductGrid.vue';

const props = defineProps({
    query: { type: String, required: true },
    products: { type: Array, required: true },
    gender: { type: String, default: null },
    genders: { type: Array, required: true },
});

const term = ref(props.query);

const search = (gender) => router.get(route('search'), { q: term.value, ...(gender && { gender }) }, { preserveState: true, replace: true });

const submit = () => search(props.gender);

const toggleGender = (gender) => search(props.gender === gender ? null : gender);

const chip = 'min-h-11 border px-3 text-sm transition-colors duration-150 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-700';
</script>

<template>
    <Head :title="query ? `Search: ${query}` : 'Search'" />

    <StoreLayout>
        <div class="mx-auto max-w-6xl px-4 py-8 sm:py-12">
            <form role="search" class="flex gap-2" @submit.prevent="submit">
                <input
                    v-model="term"
                    type="search"
                    name="q"
                    placeholder="Search tees, hoodies, goods…"
                    aria-label="Search products"
                    autofocus
                    class="min-h-11 flex-1 border-brand-300 bg-transparent"
                />
                <button type="submit" class="min-h-11 bg-ink px-5 text-xs font-medium uppercase tracking-[0.2em] text-brand-50">Search</button>
            </form>

            <template v-if="query">
                <div role="group" aria-label="Gender" class="mt-6 flex flex-wrap gap-2">
                    <button v-for="option in genders" :key="option.value" type="button" :class="[chip, gender === option.value ? 'border-ink bg-ink text-brand-50' : 'border-brand-300']" :aria-pressed="gender === option.value" @click="toggleGender(option.value)">{{ option.label }}</button>
                </div>
                <p class="mt-6 text-sm text-brand-700" role="status">
                    {{ products.length }} {{ products.length === 1 ? 'result' : 'results' }} for “{{ query }}”
                </p>
                <ProductGrid v-if="products.length" :products="products" class="mt-6" />
                <p v-else class="mt-12 text-center text-brand-700">No products match. Try a shorter word, like “tee”.</p>
            </template>
        </div>
    </StoreLayout>
</template>
