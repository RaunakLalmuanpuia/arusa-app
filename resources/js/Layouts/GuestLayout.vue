<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import PageHeading from '@/Components/Store/PageHeading.vue';
import StoreLayout from '@/Layouts/StoreLayout.vue';

defineProps({
    title: { type: String, required: true },
});
</script>

<template>
    <StoreLayout>
        <div class="lg:grid lg:min-h-[calc(100dvh-4rem)] lg:grid-cols-2">
            <div class="relative isolate flex h-40 items-center justify-center overflow-hidden bg-brand-900 text-brand-50 lg:h-auto">
                <img
                    src="/images/hero-1600.webp"
                    srcset="/images/hero-800.webp 800w, /images/hero-1600.webp 1600w"
                    sizes="(min-width: 1024px) 50vw, 100vw"
                    alt=""
                    class="absolute inset-0 -z-10 h-full w-full object-cover opacity-60"
                />
                <div class="absolute inset-0 -z-10 bg-gradient-to-t from-brand-900 via-brand-900/60 to-brand-900/20" aria-hidden="true" />

                <div class="flex flex-col items-center text-center">
                    <ApplicationLogo on-dark sizes="112px" alt="" class="h-20 w-20 animate-rise-in lg:h-28 lg:w-28" />
                    <p class="mt-6 hidden animate-rise-in font-display text-5xl font-semibold uppercase tracking-[0.12em] [animation-delay:150ms] lg:block">
                        {{ $page.props.store.store_name }}
                    </p>
                    <p class="mt-3 hidden animate-rise-in text-sm font-medium uppercase tracking-[0.3em] text-brand-200 [animation-delay:300ms] lg:block">
                        Apparel Co.
                    </p>
                    <span class="mt-6 hidden h-px w-16 animate-rule-draw bg-brand-400 [animation-delay:300ms] lg:block" aria-hidden="true" />
                </div>
            </div>

            <div class="flex items-center">
                <div class="mx-auto w-full max-w-md px-4 py-10 sm:py-16">
                    <PageHeading :title="title" class="mb-8" />
                    <slot />
                </div>
            </div>
        </div>
    </StoreLayout>
</template>
