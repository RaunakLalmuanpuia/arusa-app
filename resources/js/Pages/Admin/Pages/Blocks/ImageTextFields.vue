<script setup>
import Field from './Field.vue';
import ImageField from './ImageField.vue';

const props = defineProps({
    data: { type: Object, required: true },
    errors: { type: Object, required: true },
    prefix: { type: String, required: true },
});

const error = (field) => props.errors[`${props.prefix}.${field}`];

const sides = [
    { value: 'left', label: 'Photo on the left' },
    { value: 'right', label: 'Photo on the right' },
];
</script>

<template>
    <div class="space-y-4">
        <ImageField v-model="data.image" label="Photo" :error="error('image')" />
        <Field :id="`${prefix}.image_alt`" v-model="data.image_alt" label="Photo description for screen readers (optional)" :error="error('image_alt')" />
        <Field :id="`${prefix}.image_side`" v-model="data.image_side" label="Layout" :options="sides" :error="error('image_side')" />
        <Field :id="`${prefix}.heading`" v-model="data.heading" label="Heading (optional)" :error="error('heading')" />
        <Field :id="`${prefix}.markdown`" v-model="data.markdown" label="Text (Markdown)" :rows="8" class="font-mono" :error="error('markdown')" />
    </div>
</template>
