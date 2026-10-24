<script setup>
import Field from './Field.vue';
import ImageField from './ImageField.vue';

const props = defineProps({
    data: { type: Object, required: true },
    errors: { type: Object, required: true },
    prefix: { type: String, required: true },
});

const error = (field) => props.errors[`${props.prefix}.${field}`];
</script>

<template>
    <div class="space-y-4">
        <Field :id="`${prefix}.eyebrow`" v-model="data.eyebrow" label="Small line above the heading (optional)" :error="error('eyebrow')" />
        <Field :id="`${prefix}.heading`" v-model="data.heading" label="Heading" :error="error('heading')" />
        <Field :id="`${prefix}.intro`" v-model="data.intro" label="Intro (optional)" :rows="2" :error="error('intro')" />
        <ImageField v-model="data.image" label="Background photo (optional; the store photo is used when empty)" clearable :error="error('image')" />
    </div>
</template>
