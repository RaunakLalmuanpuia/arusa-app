<script setup>
defineProps({
    data: { type: Object, required: true },
});
</script>

<template>
    <section class="mx-auto grid max-w-6xl items-center gap-8 px-4 md:grid-cols-2 md:gap-14">
        <img
            :src="data.image.src"
            :srcset="data.image.srcset"
            sizes="(min-width: 768px) 50vw, 100vw"
            :alt="data.image_alt ?? ''"
            loading="lazy"
            class="aspect-[4/5] w-full object-cover"
            :class="{ 'md:order-last': data.image_side === 'right' }"
        />
        <div>
            <h2 v-if="data.heading" class="mb-4 font-display text-2xl font-semibold sm:text-3xl">{{ data.heading }}</h2>
            <!-- html is server-rendered Markdown with raw HTML stripped -->
            <div class="content-prose" v-html="data.html" />
        </div>
    </section>
</template>
