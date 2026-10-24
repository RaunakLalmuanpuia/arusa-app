import CallToActionFields from './CallToActionFields.vue';
import ContactCardsFields from './ContactCardsFields.vue';
import FaqFields from './FaqFields.vue';
import FeaturesFields from './FeaturesFields.vue';
import HeroFields from './HeroFields.vue';
import ImageTextFields from './ImageTextFields.vue';
import TableFields from './TableFields.vue';
import TextFields from './TextFields.vue';

// Keys must match PageBlockType values.
export const blockTypes = {
    hero: { label: 'Hero', fields: HeroFields, data: () => ({ eyebrow: '', heading: '', intro: '', image: null }) },
    text: { label: 'Text', fields: TextFields, data: () => ({ markdown: '' }) },
    image_text: { label: 'Image + text', fields: ImageTextFields, data: () => ({ image: null, image_alt: '', image_side: 'left', heading: '', markdown: '' }) },
    features: {
        label: 'Features',
        fields: FeaturesFields,
        data: () => ({ heading: '', items: [{ icon: 'cotton', title: '', text: '' }, { icon: 'feather', title: '', text: '' }] }),
    },
    table: { label: 'Table', fields: TableFields, data: () => ({ heading: '', columns: ['', ''], rows: [['', '']], note: '' }) },
    faq: { label: 'FAQ', fields: FaqFields, data: () => ({ heading: '', items: [{ question: '', answer: '' }] }) },
    contact_cards: { label: 'Contact cards', fields: ContactCardsFields, data: () => ({ heading: '', items: [{ kind: 'phone', label: '', value: '' }] }) },
    call_to_action: { label: 'Call to action', fields: CallToActionFields, data: () => ({ heading: '', text: '', button_label: '', button_url: '/' }) },
};

let nextUid = 0;

// uid only keys the block list in the editor; withoutUids strips it before submit.
export const withUid = (block) => ({ ...block, uid: nextUid++ });

export const newBlock = (type) => withUid({ type, data: blockTypes[type].data() });

export const withoutUids = (data) => ({ ...data, blocks: data.blocks.map(({ uid, ...block }) => block) });
