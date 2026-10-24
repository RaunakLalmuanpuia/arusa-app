<script setup>
import { Head } from '@inertiajs/vue3';
import StoreLayout from '@/Layouts/StoreLayout.vue';

defineProps({
    title: { type: String, required: true },
    html: { type: String, required: true },
});
</script>

<template>
    <Head :title="title" />

    <StoreLayout>
        <section class="relative isolate overflow-hidden bg-brand-900 text-brand-50">
            <img
                src="/images/hero-1600.webp"
                srcset="/images/hero-800.webp 800w, /images/hero-1600.webp 1600w"
                sizes="100vw"
                alt=""
                class="absolute inset-0 -z-10 h-full w-full object-cover opacity-60"
            />
            <div class="absolute inset-0 -z-10 bg-gradient-to-t from-brand-900 via-brand-900/60 to-brand-900/20" aria-hidden="true" />

            <div class="mx-auto flex h-48 max-w-3xl flex-col justify-end px-4 pb-8 sm:h-64">
                <p class="animate-rise-in text-xs uppercase tracking-[0.3em] text-brand-200">Arusa Apparel Co.</p>
                <h1 class="mt-2 animate-rise-in font-display text-3xl font-semibold text-brand-50 [animation-delay:75ms] sm:text-4xl">{{ title }}</h1>
            </div>
        </section>

        <article class="content-prose mx-auto max-w-3xl px-4 py-10 sm:py-16">
            <!-- html is server-rendered Markdown with raw HTML stripped -->
            <div v-html="html" />
        </article>
    </StoreLayout>
</template>
