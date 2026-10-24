<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import StoreLayout from '@/Layouts/StoreLayout.vue';
import ProductGrid from '@/Components/Store/ProductGrid.vue';
import Reveal from '@/Components/Store/Reveal.vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import IconCotton from '@/Components/Icons/IconCotton.vue';
import IconPuffPrint from '@/Components/Icons/IconPuffPrint.vue';
import IconShield from '@/Components/Icons/IconShield.vue';
import IconFeather from '@/Components/Icons/IconFeather.vue';

defineProps({ products: { type: Array, required: true } });

const page = usePage();
const store = page.props.store;

const marqueeItems = ['Streetwear', 'Premium quality', 'Aizawl, Mizoram', 'Est. 2026'];

const features = [
    { label: 'Premium cotton', icon: IconCotton },
    { label: 'Puff print detail', icon: IconPuffPrint },
    { label: 'Durable & long lasting', icon: IconShield },
    { label: 'Comfort fit', icon: IconFeather },
];

const scrollToNewIn = () => {
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    document.getElementById('new-in').scrollIntoView({ behavior: prefersReducedMotion ? 'auto' : 'smooth' });
};
</script>

<template>
    <Head title="Home" />

    <StoreLayout>
        <section class="relative isolate overflow-hidden bg-brand-900 text-brand-50">
            <img
                src="/images/hero-1600.webp"
                srcset="/images/hero-800.webp 800w, /images/hero-1600.webp 1600w"
                sizes="100vw"
                alt=""
                fetchpriority="high"
                class="absolute inset-0 -z-10 h-full w-full object-cover opacity-60"
            />
            <div class="absolute inset-0 -z-10 bg-gradient-to-t from-brand-900 via-brand-900/60 to-brand-900/20" aria-hidden="true" />

            <div class="mx-auto flex min-h-[80svh] max-w-6xl flex-col items-center justify-center px-4 py-20 text-center">
                <ApplicationLogo on-dark sizes="112px" class="h-24 w-24 animate-rise-in sm:h-28 sm:w-28" />
                <div class="mt-8 flex w-full items-center justify-center gap-4 sm:gap-6">
                    <span class="h-px w-10 origin-right animate-rule-draw bg-brand-400 [animation-delay:150ms] sm:w-16" aria-hidden="true" />
                    <h1 class="animate-rise-in font-display text-5xl font-semibold uppercase tracking-[0.12em] [animation-delay:150ms] sm:text-7xl">
                        {{ store.store_name }}
                    </h1>
                    <span class="h-px w-10 origin-left animate-rule-draw bg-brand-400 [animation-delay:150ms] sm:w-16" aria-hidden="true" />
                </div>
                <p class="mt-3 animate-rise-in text-sm font-medium uppercase tracking-[0.3em] text-brand-200 [animation-delay:300ms]">
                    Apparel Co.
                </p>
                <a
                    v-if="products.length"
                    href="#new-in"
                    class="mt-10 inline-flex min-h-12 animate-rise-in items-center border border-brand-50 px-8 text-xs font-medium uppercase tracking-[0.2em] transition-colors duration-200 [animation-delay:450ms] hover:bg-brand-50 hover:text-ink motion-reduce:transition-none focus-ring-inverse"
                    @click.prevent="scrollToNewIn"
                >
                    Shop new in
                </a>
            </div>
        </section>

        <div class="overflow-hidden border-y border-brand-800 bg-brand-900 py-4 text-brand-300">
            <p class="sr-only">{{ marqueeItems.join(' · ') }}</p>
            <div class="flex w-max animate-marquee" aria-hidden="true">
                <ul v-for="copy in 6" :key="copy" class="flex shrink-0">
                    <li v-for="item in marqueeItems" :key="item" class="flex items-center whitespace-nowrap text-xs uppercase tracking-[0.3em]">
                        <span class="px-6">{{ item }}</span>
                        <span class="text-brand-600">·</span>
                    </li>
                </ul>
            </div>
        </div>

        <section aria-label="Why Arusa" class="mx-auto max-w-6xl px-4 py-14 sm:py-20">
            <ul class="grid grid-cols-2 gap-x-4 gap-y-10 sm:grid-cols-4">
                <Reveal v-for="(feature, index) in features" :key="feature.label" as="li" :delay="index * 75" class="flex flex-col items-center text-center">
                    <span class="flex h-16 w-16 items-center justify-center rounded-full border border-ink">
                        <component :is="feature.icon" class="h-7 w-7" />
                    </span>
                    <span class="mt-4 text-xs font-medium uppercase tracking-[0.2em]">{{ feature.label }}</span>
                </Reveal>
            </ul>
        </section>

        <section v-if="page.props.categories.length" aria-label="Shop by category" class="mx-auto max-w-6xl px-4">
            <ul class="grid gap-4 sm:grid-cols-2">
                <Reveal v-for="(category, index) in page.props.categories" :key="category.slug" as="li" :delay="(index % 2) * 75">
                    <Link
                        :href="route('categories.show', category.slug)"
                        class="group flex aspect-[2/1] flex-col justify-end bg-brand-900 p-6 text-brand-50 focus-ring sm:p-8"
                    >
                        <span class="text-[10px] uppercase tracking-[0.3em] text-brand-300">Shop</span>
                        <span class="mt-2 font-display text-3xl font-semibold uppercase tracking-[0.12em] sm:text-4xl">{{ category.name }}</span>
                        <span
                            class="mt-4 h-px w-10 origin-left bg-brand-400 transition-transform duration-300 group-hover:scale-x-[2.5] motion-reduce:transition-none"
                            aria-hidden="true"
                        />
                    </Link>
                </Reveal>
            </ul>
        </section>

        <section v-if="products.length" id="new-in" class="mx-auto max-w-6xl scroll-mt-16 px-4 py-16 sm:py-20">
            <div class="flex items-center justify-center gap-4">
                <span class="h-px w-10 bg-brand-400" aria-hidden="true" />
                <h2 class="text-xs font-medium uppercase tracking-[0.3em] text-brand-600">New in</h2>
                <span class="h-px w-10 bg-brand-400" aria-hidden="true" />
            </div>
            <ProductGrid :products="products" class="mt-10" />
        </section>
        <section v-else class="mx-auto max-w-2xl px-4 py-16 text-center sm:py-20">
            <p class="text-xs font-medium uppercase tracking-[0.3em] text-brand-600">The first drop</p>
            <h2 class="mt-3 font-display text-3xl font-semibold sm:text-4xl">Our collection is coming soon.</h2>
            <p class="mt-4 text-base leading-relaxed text-brand-700">
                Apparel and everyday goods, delivered across Mizoram.
            </p>
        </section>
    </StoreLayout>
</template>
