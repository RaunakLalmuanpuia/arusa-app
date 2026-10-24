<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
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
        stock_was: variant.stock,
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

const cell = 'admin-field';
</script>

<template>
    <section class="admin-card p-6">
        <h2 class="font-medium">Variants</h2>
        <p class="mt-1 text-sm text-brand-600">
            Type sizes and colours separated by commas, then Generate. Leave both empty for a single-variant product.
            Variants can't be deleted; untick Active to hide one.
        </p>

        <div class="mt-4 flex flex-wrap items-end gap-3">
            <label class="text-sm font-medium text-ink">
                Sizes
                <input v-model="sizes" placeholder="S, M, L, XL" :class="cell" class="mt-1.5 sm:w-56" />
            </label>
            <label class="text-sm font-medium text-ink">
                Colours
                <input v-model="colours" placeholder="Black, Cream" :class="cell" class="mt-1.5 sm:w-56" />
            </label>
            <button type="button" class="admin-btn-secondary" @click="generate">Generate</button>
        </div>

        <p v-if="form.errors.variants" class="mt-4 text-sm text-red-700">{{ form.errors.variants }}</p>

        <form class="mt-4" @submit.prevent="save">
            <div class="overflow-x-auto rounded-lg border border-brand-200">
                <table class="w-full min-w-[640px] text-left text-sm">
                    <thead class="border-b border-brand-200 bg-brand-50/60 text-xs uppercase tracking-wide text-brand-500">
                        <tr>
                            <th scope="col" class="px-3 py-2 font-medium">Colour</th>
                            <th scope="col" class="px-3 py-2 font-medium">Size</th>
                            <th scope="col" class="px-3 py-2 font-medium">SKU</th>
                            <th scope="col" class="px-3 py-2 font-medium">Price override (₹)</th>
                            <th scope="col" class="px-3 py-2 font-medium">Stock</th>
                            <th scope="col" class="px-3 py-2 font-medium">Active</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-100">
                        <tr v-for="(variant, index) in form.variants" :key="variant.id ?? `new-${index}`" class="align-top">
                            <td class="px-3 py-2 pt-4">{{ variant.colour ?? '—' }}</td>
                            <td class="px-3 py-2 pt-4">{{ variant.size ?? '—' }}</td>
                            <td class="px-3 py-2">
                                <input v-model="variant.sku" :class="cell" class="font-mono text-xs" :aria-label="`SKU row ${index + 1}`" required />
                                <p v-if="form.errors[`variants.${index}.sku`]" class="mt-1 text-xs text-red-700">{{ form.errors[`variants.${index}.sku`] }}</p>
                            </td>
                            <td class="px-3 py-2">
                                <input v-model="variant.price_override" type="number" min="1" step="0.01" placeholder="Base price" :class="cell" :aria-label="`Price override row ${index + 1}`" />
                                <p v-if="form.errors[`variants.${index}.price_override`]" class="mt-1 text-xs text-red-700">{{ form.errors[`variants.${index}.price_override`] }}</p>
                            </td>
                            <td class="px-3 py-2">
                                <input v-model="variant.stock" type="number" min="0" :class="cell" :aria-label="`Stock row ${index + 1}`" required />
                                <p v-if="form.errors[`variants.${index}.stock`]" class="mt-1 text-xs text-red-700">{{ form.errors[`variants.${index}.stock`] }}</p>
                            </td>
                            <td class="px-3 py-2 pt-4">
                                <input v-model="variant.is_active" type="checkbox" class="admin-checkbox" :aria-label="`Active row ${index + 1}`" />
                            </td>
                        </tr>
                        <tr v-if="form.variants.length === 0">
                            <td colspan="6" class="px-3 py-8 text-center text-brand-500">No variants yet. Generate some above.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <button type="submit" class="admin-btn mt-4" :disabled="form.processing || form.variants.length === 0">Save variants</button>
        </form>
    </section>
</template>
