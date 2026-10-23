<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import StoreLayout from '@/Layouts/StoreLayout.vue';

const store = usePage().props.store;
</script>

<template>
    <Head title="Home" />

    <StoreLayout>
        <section class="bg-brand-900 text-brand-50">
            <div class="mx-auto flex max-w-6xl flex-col items-center px-4 pb-20 pt-14 text-center sm:pb-28 sm:pt-20">
                <div class="flex w-full items-center justify-center gap-4 sm:gap-6">
                    <span class="h-px w-10 bg-brand-500 sm:w-16" aria-hidden="true" />
                    <h1 class="font-display text-5xl font-semibold uppercase tracking-[0.12em] sm:text-7xl">
                        {{ store.store_name }}
                    </h1>
                    <span class="h-px w-10 bg-brand-500 sm:w-16" aria-hidden="true" />
                </div>
                <p class="mt-3 text-sm font-medium uppercase tracking-[0.3em] text-brand-200">Apparel Co.</p>
                <p class="mt-6 flex flex-wrap justify-center gap-x-4 gap-y-2 text-xs uppercase tracking-[0.3em] text-brand-300">
                    <span>Streetwear</span>
                    <span class="hidden sm:inline" aria-hidden="true">/</span>
                    <span>Premium quality</span>
                    <span class="hidden sm:inline" aria-hidden="true">/</span>
                    <span>Aizawl, Mizoram</span>
                </p>
            </div>
        </section>

        <section class="mx-auto max-w-2xl px-4 py-16 text-center sm:py-20">
            <p class="text-xs font-medium uppercase tracking-[0.3em] text-brand-600">The first drop</p>
            <h2 class="mt-3 font-display text-3xl font-semibold sm:text-4xl">Our collection is coming soon.</h2>
            <p class="mt-4 text-base leading-relaxed text-brand-700">
                Apparel and everyday goods, delivered across Mizoram.
            </p>
        </section>
    </StoreLayout>
</template>
