<script setup>
defineProps({
    data: { type: Object, required: true },
});
</script>

<template>
    <section class="relative isolate overflow-hidden bg-brand-900 text-brand-50">
        <img
            :src="data.image?.src ?? '/images/hero-1600.webp'"
            :srcset="data.image?.srcset ?? '/images/hero-800.webp 800w, /images/hero-1600.webp 1600w'"
            sizes="100vw"
            alt=""
            class="absolute inset-0 -z-10 h-full w-full object-cover opacity-60"
        />
        <div class="absolute inset-0 -z-10 bg-gradient-to-t from-brand-900 via-brand-900/60 to-brand-900/20" aria-hidden="true" />

        <div class="mx-auto flex min-h-[16rem] max-w-3xl flex-col justify-end px-4 pb-10 pt-24 sm:min-h-[22rem] sm:pb-14">
            <p v-if="data.eyebrow" class="animate-rise-in text-xs uppercase tracking-[0.3em] text-brand-200">{{ data.eyebrow }}</p>
            <h1 class="mt-2 animate-rise-in font-display text-3xl font-semibold text-brand-50 [animation-delay:75ms] sm:text-5xl">{{ data.heading }}</h1>
            <p v-if="data.intro" class="mt-4 max-w-xl animate-rise-in text-base leading-relaxed text-brand-100 [animation-delay:150ms] sm:text-lg">{{ data.intro }}</p>
        </div>
    </section>
</template>
