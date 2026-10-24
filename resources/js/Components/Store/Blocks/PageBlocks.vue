<script setup>
import Reveal from '@/Components/Store/Reveal.vue';
import CallToActionBlock from './CallToActionBlock.vue';
import ContactCardsBlock from './ContactCardsBlock.vue';
import FaqBlock from './FaqBlock.vue';
import FeaturesBlock from './FeaturesBlock.vue';
import HeroBlock from './HeroBlock.vue';
import ImageTextBlock from './ImageTextBlock.vue';
import TableBlock from './TableBlock.vue';
import TextBlock from './TextBlock.vue';

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
    <div>
        <HeroBlock v-if="blocks[0]?.type !== 'hero'" :data="{ eyebrow: 'Arusa Apparel Co.', heading: title }" />

        <div class="pb-16 sm:pb-24">
            <template v-for="(block, index) in blocks" :key="index">
                <HeroBlock v-if="index === 0 && block.type === 'hero'" :data="block.data" />
                <Reveal v-else class="mt-14 sm:mt-20">
                    <component :is="components[block.type]" :data="block.data" v-bind="block.type === 'hero' ? { headingLevel: 'h2' } : {}" />
                </Reveal>
            </template>
        </div>
    </div>
</template>
