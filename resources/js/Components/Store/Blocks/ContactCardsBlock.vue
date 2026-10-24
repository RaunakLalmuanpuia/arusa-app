<script setup>
import IconChat from '@/Components/Icons/IconChat.vue';
import IconClock from '@/Components/Icons/IconClock.vue';
import IconMail from '@/Components/Icons/IconMail.vue';
import IconMapPin from '@/Components/Icons/IconMapPin.vue';
import IconPhone from '@/Components/Icons/IconPhone.vue';

defineProps({
    data: { type: Object, required: true },
});

const icons = { phone: IconPhone, whatsapp: IconChat, email: IconMail, address: IconMapPin, hours: IconClock };
</script>

<template>
    <section class="mx-auto max-w-6xl px-4">
        <h2 v-if="data.heading" class="mb-6 font-display text-2xl font-semibold sm:text-3xl">{{ data.heading }}</h2>
        <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <li v-for="(item, index) in data.items" :key="index">
                <component
                    :is="item.href ? 'a' : 'div'"
                    :href="item.href"
                    :target="item.kind === 'whatsapp' ? '_blank' : undefined"
                    :rel="item.kind === 'whatsapp' ? 'noopener' : undefined"
                    class="flex h-full items-start gap-4 border border-brand-200 bg-white p-6"
                    :class="{ 'transition-colors duration-200 hover:border-ink motion-reduce:transition-none focus-ring': item.href }"
                >
                    <component :is="icons[item.kind]" class="h-6 w-6 shrink-0 text-brand-700" />
                    <span class="min-w-0">
                        <span class="block text-xs uppercase tracking-[0.2em] text-brand-700">{{ item.label }}</span>
                        <span class="mt-1 block whitespace-pre-line break-words font-medium">{{ item.value }}</span>
                    </span>
                </component>
            </li>
        </ul>
    </section>
</template>
