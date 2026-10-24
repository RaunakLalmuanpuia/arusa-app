<script setup>
import IconChevronDown from '@/Components/Icons/IconChevronDown.vue';

defineProps({
    data: { type: Object, required: true },
});
</script>

<template>
    <section class="mx-auto max-w-3xl px-4">
        <h2 v-if="data.heading" class="mb-4 font-display text-2xl font-semibold sm:text-3xl">{{ data.heading }}</h2>
        <div class="divide-y divide-brand-200 border-y border-brand-200">
            <details v-for="(item, index) in data.items" :key="index" class="group">
                <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-4 py-4 font-medium focus-ring [&::-webkit-details-marker]:hidden">
                    {{ item.question }}
                    <IconChevronDown class="h-5 w-5 shrink-0 text-brand-700 transition-transform duration-200 group-open:rotate-180 motion-reduce:transition-none" />
                </summary>
                <!-- html is server-rendered Markdown with raw HTML stripped -->
                <div class="content-prose pb-4 text-brand-800" v-html="item.html" />
            </details>
        </div>
    </section>
</template>
