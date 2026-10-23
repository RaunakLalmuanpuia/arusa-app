<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import StoreLayout from '@/Layouts/StoreLayout.vue';
import { formatPaise } from '@/money';

const props = defineProps({
    product: { type: Object, required: true },
    variants: { type: Array, required: true },
    images: { type: Array, required: true },
});

const unique = (values) => [...new Set(values.filter((value) => value !== null))];
const colours = unique(props.variants.map((variant) => variant.colour));
const sizes = unique(props.variants.map((variant) => variant.size));

const selectedColour = ref(colours.find((colour) => props.variants.some((v) => v.colour === colour && v.in_stock)) ?? colours[0] ?? null);
const selectedSize = ref(sizes.length === 0 ? null : undefined);
const activeImage = ref(0);

const selectedVariant = computed(() =>
    props.variants.find((variant) => variant.colour === selectedColour.value && variant.size === selectedSize.value),
);

const isSizeAvailable = (size) =>
    props.variants.some((variant) => variant.size === size && variant.colour === selectedColour.value && variant.in_stock);

const prices = computed(() => props.variants.filter((v) => v.colour === selectedColour.value).map((v) => v.price));
const priceLabel = computed(() => {
    if (selectedVariant.value) {
        return formatPaise(selectedVariant.value.price);
    }
    const low = Math.min(...prices.value);
    return low === Math.max(...prices.value) ? formatPaise(low) : `From ${formatPaise(low)}`;
});

const gallery = computed(() => {
    const matching = props.images.filter((image) => image.colour === null || image.colour === selectedColour.value);
    return matching.length ? matching : props.images;
});

const selectColour = (colour) => {
    selectedColour.value = colour;
    activeImage.value = 0;
    if (selectedSize.value && !isSizeAvailable(selectedSize.value)) {
        selectedSize.value = undefined;
    }
};

const focusRing = 'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-700';
</script>

<template>
    <Head :title="product.name">
        <meta name="description" :content="product.description.slice(0, 155)" />
    </Head>

    <StoreLayout>
        <div class="mx-auto grid max-w-6xl gap-8 px-4 py-6 md:grid-cols-2 md:gap-12 md:py-12">
            <div>
                <div class="relative aspect-[4/5] overflow-hidden bg-brand-100">
                    <Transition
                        mode="out-in"
                        enter-active-class="transition-opacity duration-200 motion-reduce:transition-none"
                        leave-active-class="transition-opacity duration-150 motion-reduce:transition-none"
                        enter-from-class="opacity-0"
                        leave-to-class="opacity-0"
                    >
                        <img
                            v-if="gallery[activeImage]"
                            :key="gallery[activeImage].src"
                            :src="gallery[activeImage].src"
                            :srcset="gallery[activeImage].srcset"
                            sizes="(min-width: 768px) 50vw, 100vw"
                            :alt="gallery[activeImage].alt"
                            fetchpriority="high"
                            class="h-full w-full object-cover"
                        />
                    </Transition>
                </div>
                <div v-if="gallery.length > 1" class="mt-3 flex gap-2 overflow-x-auto p-1">
                    <button
                        v-for="(image, index) in gallery"
                        :key="image.src"
                        type="button"
                        class="h-16 w-16 shrink-0 overflow-hidden border transition-colors duration-150 motion-reduce:transition-none"
                        :class="[index === activeImage ? 'border-ink' : 'border-transparent hover:border-brand-400', focusRing]"
                        :aria-label="`Show image ${index + 1}`"
                        :aria-current="index === activeImage"
                        @click="activeImage = index"
                    >
                        <img :src="image.src" :srcset="image.srcset" sizes="64px" :alt="''" loading="lazy" class="h-full w-full object-cover" />
                    </button>
                </div>
            </div>

            <div>
                <Link
                    :href="route('categories.show', product.category.slug)"
                    class="text-xs uppercase tracking-[0.3em] text-brand-600 hover:underline"
                    :class="focusRing"
                >
                    {{ product.category.name }}
                </Link>
                <h1 class="mt-2 font-display text-3xl font-semibold sm:text-4xl">{{ product.name }}</h1>
                <p class="mt-3 text-xl tabular-nums">{{ priceLabel }}</p>

                <fieldset v-if="colours.length" class="mt-8">
                    <legend class="text-xs font-medium uppercase tracking-[0.2em]">Colour: {{ selectedColour }}</legend>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button
                            v-for="colour in colours"
                            :key="colour"
                            type="button"
                            class="min-h-11 border px-4 text-sm transition-colors duration-150 motion-reduce:transition-none"
                            :class="[colour === selectedColour ? 'border-ink bg-ink text-brand-50' : 'border-brand-300 hover:border-ink', focusRing]"
                            :aria-pressed="colour === selectedColour"
                            @click="selectColour(colour)"
                        >
                            {{ colour }}
                        </button>
                    </div>
                </fieldset>

                <fieldset v-if="sizes.length" class="mt-6">
                    <legend class="text-xs font-medium uppercase tracking-[0.2em]">Size</legend>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button
                            v-for="size in sizes"
                            :key="size"
                            type="button"
                            class="min-h-11 min-w-11 border px-3 text-sm transition-colors duration-150 motion-reduce:transition-none"
                            :class="[
                                size === selectedSize ? 'border-ink bg-ink text-brand-50' : 'border-brand-300 hover:border-ink',
                                isSizeAvailable(size) ? '' : 'text-brand-500 line-through',
                                focusRing,
                            ]"
                            :aria-pressed="size === selectedSize"
                            :aria-disabled="!isSizeAvailable(size)"
                            @click="isSizeAvailable(size) && (selectedSize = size)"
                        >
                            {{ size }}
                        </button>
                    </div>
                    <Link
                        :href="route('pages.show', 'size-guide')"
                        class="mt-3 inline-block text-sm underline underline-offset-4"
                        :class="focusRing"
                    >
                        Size guide
                    </Link>
                </fieldset>

                <p class="mt-6 min-h-5 text-sm text-brand-700" role="status">
                    <template v-if="selectedVariant">{{ selectedVariant.in_stock ? 'In stock' : 'Sold out' }}</template>
                    <template v-else-if="sizes.length">Choose a size</template>
                </p>

                <p v-if="product.description" class="mt-8 whitespace-pre-line leading-relaxed text-brand-700">{{ product.description }}</p>
            </div>
        </div>
    </StoreLayout>
</template>
