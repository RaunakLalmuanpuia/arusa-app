<script setup>
import IconBag from '@/Components/Icons/IconBag.vue';
import IconChat from '@/Components/Icons/IconChat.vue';
import IconCotton from '@/Components/Icons/IconCotton.vue';
import IconFeather from '@/Components/Icons/IconFeather.vue';
import IconPuffPrint from '@/Components/Icons/IconPuffPrint.vue';
import IconShield from '@/Components/Icons/IconShield.vue';

defineProps({
    data: { type: Object, required: true },
});

// Keys must match PageBlockType::ICONS.
const icons = { cotton: IconCotton, feather: IconFeather, 'puff-print': IconPuffPrint, shield: IconShield, chat: IconChat, bag: IconBag };
</script>

<template>
    <section class="mx-auto max-w-6xl px-4">
        <h2 v-if="data.heading" class="mb-10 text-center font-display text-2xl font-semibold sm:text-3xl">{{ data.heading }}</h2>
        <ul class="grid gap-x-6 gap-y-10 sm:grid-cols-2" :class="{ 'lg:grid-cols-3': data.items.length === 3, 'lg:grid-cols-4': data.items.length === 4 }">
            <li v-for="(item, index) in data.items" :key="index" class="flex flex-col items-center text-center">
                <span class="flex h-16 w-16 items-center justify-center rounded-full border border-ink">
                    <component :is="icons[item.icon]" class="h-7 w-7" />
                </span>
                <h3 class="mt-4 text-xs font-medium uppercase tracking-[0.2em]">{{ item.title }}</h3>
                <p class="mt-2 max-w-xs text-sm leading-relaxed text-brand-700">{{ item.text }}</p>
            </li>
        </ul>
    </section>
</template>
