<script setup>
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import StoreLayout from '@/Layouts/StoreLayout.vue';
import ProductGrid from '@/Components/Store/ProductGrid.vue';
import GenderSwitch from '@/Components/Store/GenderSwitch.vue';

const props = defineProps({
    category: { type: Object, required: true },
    subcategories: { type: Array, required: true },
    products: { type: Array, required: true },
    sizes: { type: Array, required: true },
    colours: { type: Array, required: true },
    genders: { type: Array, required: true },
    filters: { type: Object, required: true },
});

const query = reactive({ gender: null, size: null, colour: null, min: null, max: null, ...props.filters });

const apply = () => {
    // An empty gender (All) is sent on purpose: it clears the remembered pick.
    const params = { ...Object.fromEntries(Object.entries(query).filter(([, value]) => value !== null && value !== '')), gender: query.gender ?? '' };
    router.get(route('categories.show', props.category.slug), params, { preserveState: true, preserveScroll: true, replace: true });
};

const toggle = (key, value) => {
    query[key] = query[key] === value ? null : value;
    apply();
};

const pickGender = (gender) => {
    query.gender = gender;
    apply();
};

const clear = () => {
    Object.assign(query, { size: null, colour: null, min: null, max: null });
    apply();
};

const chip = 'min-h-11 border px-3 text-sm transition-colors duration-150 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-700';
</script>

<template>
    <Head :title="category.name" />

    <StoreLayout>
        <div class="mx-auto max-w-6xl px-4 py-8 sm:py-12">
            <h1 class="font-display text-3xl font-semibold sm:text-4xl">{{ category.name }}</h1>

            <nav v-if="subcategories.length" aria-label="Subcategories" class="mt-4 flex flex-wrap gap-x-6 gap-y-2">
                <Link
                    v-for="sub in subcategories"
                    :key="sub.slug"
                    :href="route('categories.show', sub.slug)"
                    class="text-xs uppercase tracking-[0.2em] text-brand-600 underline-offset-4 hover:underline"
                >
                    {{ sub.name }}
                </Link>
            </nav>

            <GenderSwitch v-if="genders.length" :genders="genders" :model-value="query.gender" class="mt-6" @update:model-value="pickGender" />

            <details class="mt-6 border-y border-brand-200 py-3 md:open:pb-6" :open="Object.entries(filters).some(([key, value]) => key !== 'gender' && value !== null)">
                <summary class="flex min-h-11 cursor-pointer items-center text-xs font-medium uppercase tracking-[0.2em]">Filter</summary>
                <div class="mt-3 grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                    <fieldset v-if="sizes.length">
                        <legend class="text-xs uppercase tracking-[0.2em] text-brand-600">Size</legend>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button v-for="size in sizes" :key="size" type="button" :class="[chip, query.size === size ? 'border-ink bg-ink text-brand-50' : 'border-brand-300']" :aria-pressed="query.size === size" @click="toggle('size', size)">{{ size }}</button>
                        </div>
                    </fieldset>
                    <fieldset v-if="colours.length">
                        <legend class="text-xs uppercase tracking-[0.2em] text-brand-600">Colour</legend>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button v-for="colour in colours" :key="colour" type="button" :class="[chip, query.colour === colour ? 'border-ink bg-ink text-brand-50' : 'border-brand-300']" :aria-pressed="query.colour === colour" @click="toggle('colour', colour)">{{ colour }}</button>
                        </div>
                    </fieldset>
                    <form @submit.prevent="apply">
                        <fieldset>
                            <legend class="text-xs uppercase tracking-[0.2em] text-brand-600">Price (₹)</legend>
                            <div class="mt-2 flex items-center gap-2">
                                <input v-model="query.min" type="number" min="0" inputmode="numeric" placeholder="Min" aria-label="Minimum price" class="min-h-11 w-24 border-brand-300 bg-transparent text-sm" />
                                <span aria-hidden="true">–</span>
                                <input v-model="query.max" type="number" min="0" inputmode="numeric" placeholder="Max" aria-label="Maximum price" class="min-h-11 w-24 border-brand-300 bg-transparent text-sm" />
                                <button type="submit" :class="[chip, 'border-ink']">Go</button>
                            </div>
                        </fieldset>
                    </form>
                </div>
                <button type="button" class="mt-4 min-h-11 text-sm underline underline-offset-4" @click="clear">Clear filters</button>
            </details>

            <ProductGrid v-if="products.length" :products="products" class="mt-8" />
            <p v-else class="mt-12 text-center text-brand-700">Nothing here matches yet. Try clearing the filters.</p>
        </div>
    </StoreLayout>
</template>
