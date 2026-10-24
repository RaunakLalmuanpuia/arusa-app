<script setup>
import Field from './Field.vue';
import { featureIcons, removeButton, smallButton } from './fieldOptions.js';

const props = defineProps({
    data: { type: Object, required: true },
    errors: { type: Object, required: true },
    prefix: { type: String, required: true },
});

const error = (field) => props.errors[`${props.prefix}.${field}`];
</script>

<template>
    <div class="space-y-4">
        <Field :id="`${prefix}.heading`" v-model="data.heading" label="Heading (optional)" :error="error('heading')" />
        <fieldset class="space-y-3">
            <legend class="text-sm font-medium text-ink">Items (2 to 4)</legend>
            <div v-for="(item, index) in data.items" :key="index" class="grid gap-3 rounded-lg border border-brand-200 bg-brand-50/40 p-3 sm:grid-cols-[10rem_1fr]">
                <Field :id="`${prefix}.items.${index}.icon`" v-model="item.icon" label="Icon" :options="featureIcons" :error="error(`items.${index}.icon`)" />
                <Field :id="`${prefix}.items.${index}.title`" v-model="item.title" label="Title" :error="error(`items.${index}.title`)" />
                <Field :id="`${prefix}.items.${index}.text`" v-model="item.text" label="Text" :rows="2" class="sm:col-span-2" :error="error(`items.${index}.text`)" />
                <button type="button" :class="removeButton" class="justify-self-start" :disabled="data.items.length <= 2" @click="data.items.splice(index, 1)">Remove item</button>
            </div>
            <button type="button" :class="smallButton" :disabled="data.items.length >= 4" @click="data.items.push({ icon: 'cotton', title: '', text: '' })">Add item</button>
            <p v-if="error('items')" class="text-sm text-red-700">{{ error('items') }}</p>
        </fieldset>
    </div>
</template>
