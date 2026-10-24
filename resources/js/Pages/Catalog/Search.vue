<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import StoreLayout from '@/Layouts/StoreLayout.vue';
import ProductGrid from '@/Components/Store/ProductGrid.vue';
import GenderSwitch from '@/Components/Store/GenderSwitch.vue';

const props = defineProps({
    query: { type: String, required: true },
    products: { type: Array, required: true },
    gender: { type: String, default: null },
    genders: { type: Array, required: true },
});

const term = ref(props.query);

const search = (gender) => router.get(route('search'), { q: term.value, gender: gender ?? '' }, { preserveState: true, replace: true });

const submit = () => search(props.gender);
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
                <GenderSwitch :genders="genders" :model-value="gender" class="mt-6" @update:model-value="search" />
                <p class="mt-6 text-sm text-brand-700" role="status">
                    {{ products.length }} {{ products.length === 1 ? 'result' : 'results' }} for “{{ query }}”
                </p>
                <ProductGrid v-if="products.length" :products="products" class="mt-6" />
                <p v-else class="mt-12 text-center text-brand-700">No products match. Try a shorter word, like “tee”.</p>
            </template>
        </div>
    </StoreLayout>
</template>
