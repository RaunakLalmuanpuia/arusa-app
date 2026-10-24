<script setup>
import Field from './Field.vue';
import { removeButton, smallButton } from './fieldOptions.js';

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
            <legend class="text-sm font-medium text-ink">Questions (up to 20)</legend>
            <div v-for="(item, index) in data.items" :key="index" class="space-y-3 rounded-lg border border-brand-200 bg-brand-50/40 p-3">
                <Field :id="`${prefix}.items.${index}.question`" v-model="item.question" label="Question" :error="error(`items.${index}.question`)" />
                <Field :id="`${prefix}.items.${index}.answer`" v-model="item.answer" label="Answer (Markdown)" :rows="3" :error="error(`items.${index}.answer`)" />
                <button type="button" :class="removeButton" :disabled="data.items.length <= 1" @click="data.items.splice(index, 1)">Remove question</button>
            </div>
            <button type="button" :class="smallButton" :disabled="data.items.length >= 20" @click="data.items.push({ question: '', answer: '' })">Add question</button>
            <p v-if="error('items')" class="text-sm text-red-700">{{ error('items') }}</p>
        </fieldset>
    </div>
</template>
