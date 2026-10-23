<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { toPaise } from '@/money';

const props = defineProps({
    product: { type: Object, required: true },
    variants: { type: Array, required: true },
});

const sizes = ref('');
const colours = ref('');

const toRows = (variants) =>
    variants.map((variant) => ({
        ...variant,
        price_override: variant.price_override === null ? '' : variant.price_override / 100,
    }));

const form = useForm({ variants: toRows(props.variants) });

const split = (text) => {
    const values = text.split(',').map((value) => value.trim()).filter(Boolean);
    return values.length ? values : [null];
};

const comboKey = (size, colour) => `${size ?? ''}|${colour ?? ''}`.toLowerCase();

const skuFor = (size, colour) =>
    [props.product.slug, colour, size].filter(Boolean).join('-').toUpperCase().replace(/[^A-Z0-9-]/g, '');

const generate = () => {
    const existing = new Set(form.variants.map((variant) => comboKey(variant.size, variant.colour)));

    for (const colour of split(colours.value)) {
        for (const size of split(sizes.value)) {
            if (!existing.has(comboKey(size, colour))) {
                form.variants.push({ size, colour, sku: skuFor(size, colour), price_override: '', stock: 0, is_active: true });
            }
        }
    }
};

const save = () => {
    form.transform((data) => ({
        variants: data.variants.map((variant) => ({ ...variant, price_override: toPaise(variant.price_override) })),
    })).put(route('admin.products.variants.update', props.product.id), {
        preserveScroll: true,
        onSuccess: () => {
            form.variants = toRows(props.variants);
            form.defaults();
        },
    });
};

const cell = 'w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500';
</script>

<template>
    <section class="rounded bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-gray-900">Variants</h2>
        <p class="mt-1 text-sm text-gray-600">
            Type sizes and colours separated by commas, then Generate. Leave both empty for a single-variant product.
            Variants can't be deleted; untick Active to hide one.
        </p>

        <div class="mt-4 flex flex-wrap items-end gap-3">
            <label class="text-sm text-gray-700">
                Sizes
                <input v-model="sizes" placeholder="S, M, L, XL" :class="cell" class="mt-1 block sm:w-56" />
            </label>
            <label class="text-sm text-gray-700">
                Colours
                <input v-model="colours" placeholder="Black, Cream" :class="cell" class="mt-1 block sm:w-56" />
            </label>
            <button type="button" class="rounded border border-gray-300 px-3 py-2 text-sm" @click="generate">Generate</button>
        </div>

        <InputError class="mt-4" :message="form.errors.variants" />

        <form class="mt-4" @submit.prevent="save">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-left text-sm">
                    <thead class="border-b text-gray-500">
                        <tr>
                            <th class="px-2 py-2 font-medium">Colour</th>
                            <th class="px-2 py-2 font-medium">Size</th>
                            <th class="px-2 py-2 font-medium">SKU</th>
                            <th class="px-2 py-2 font-medium">Price override (₹)</th>
                            <th class="px-2 py-2 font-medium">Stock</th>
                            <th class="px-2 py-2 font-medium">Active</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(variant, index) in form.variants" :key="variant.id ?? `new-${index}`" class="border-b align-top last:border-0">
                            <td class="px-2 py-2">{{ variant.colour ?? '—' }}</td>
                            <td class="px-2 py-2">{{ variant.size ?? '—' }}</td>
                            <td class="px-2 py-2">
                                <input v-model="variant.sku" :class="cell" :aria-label="`SKU row ${index + 1}`" required />
                                <InputError class="mt-1" :message="form.errors[`variants.${index}.sku`]" />
                            </td>
                            <td class="px-2 py-2">
                                <input v-model="variant.price_override" type="number" min="1" step="0.01" placeholder="Base price" :class="cell" :aria-label="`Price override row ${index + 1}`" />
                                <InputError class="mt-1" :message="form.errors[`variants.${index}.price_override`]" />
                            </td>
                            <td class="px-2 py-2">
                                <input v-model="variant.stock" type="number" min="0" :class="cell" :aria-label="`Stock row ${index + 1}`" required />
                                <InputError class="mt-1" :message="form.errors[`variants.${index}.stock`]" />
                            </td>
                            <td class="px-2 py-2">
                                <input v-model="variant.is_active" type="checkbox" class="rounded border-gray-300 text-indigo-600" :aria-label="`Active row ${index + 1}`" />
                            </td>
                        </tr>
                        <tr v-if="form.variants.length === 0">
                            <td colspan="6" class="px-2 py-6 text-center text-gray-500">No variants yet. Generate some above.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <PrimaryButton class="mt-4" :disabled="form.processing || form.variants.length === 0">Save variants</PrimaryButton>
        </form>
    </section>
</template>
