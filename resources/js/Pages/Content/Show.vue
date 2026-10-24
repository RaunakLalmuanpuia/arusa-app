<script setup>
import { Head } from '@inertiajs/vue3';
import StoreLayout from '@/Layouts/StoreLayout.vue';
import Reveal from '@/Components/Store/Reveal.vue';
import CallToActionBlock from '@/Components/Store/Blocks/CallToActionBlock.vue';
import ContactCardsBlock from '@/Components/Store/Blocks/ContactCardsBlock.vue';
import FaqBlock from '@/Components/Store/Blocks/FaqBlock.vue';
import FeaturesBlock from '@/Components/Store/Blocks/FeaturesBlock.vue';
import HeroBlock from '@/Components/Store/Blocks/HeroBlock.vue';
import ImageTextBlock from '@/Components/Store/Blocks/ImageTextBlock.vue';
import TableBlock from '@/Components/Store/Blocks/TableBlock.vue';
import TextBlock from '@/Components/Store/Blocks/TextBlock.vue';

defineProps({
    title: { type: String, required: true },
    blocks: { type: Array, required: true },
});

// Keys must match PageBlockType values.
const components = {
    hero: HeroBlock,
    text: TextBlock,
    image_text: ImageTextBlock,
    features: FeaturesBlock,
    table: TableBlock,
    faq: FaqBlock,
    contact_cards: ContactCardsBlock,
    call_to_action: CallToActionBlock,
};
</script>

<template>
    <Head :title="title" />

    <StoreLayout>
        <HeroBlock v-if="blocks[0]?.type !== 'hero'" :data="{ eyebrow: 'Arusa Apparel Co.', heading: title }" />

        <div class="pb-16 sm:pb-24">
            <template v-for="(block, index) in blocks" :key="index">
                <HeroBlock v-if="index === 0 && block.type === 'hero'" :data="block.data" />
                <Reveal v-else class="mt-14 sm:mt-20">
                    <component :is="components[block.type]" :data="block.data" />
                </Reveal>
            </template>
        </div>
    </StoreLayout>
</template>
