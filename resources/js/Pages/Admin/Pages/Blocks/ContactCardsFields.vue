<script setup>
import InputError from '@/Components/InputError.vue';
import Field from './Field.vue';
import { contactKinds, removeButton, smallButton } from './fieldOptions.js';

const props = defineProps({
    data: { type: Object, required: true },
    errors: { type: Object, required: true },
    prefix: { type: String, required: true },
});

const error = (field) => props.errors[`${props.prefix}.${field}`];

const hint = (kind) => contactKinds.find((option) => option.value === kind)?.hint ?? 'Value';
</script>

<template>
    <div class="space-y-4">
        <Field :id="`${prefix}.heading`" v-model="data.heading" label="Heading (optional)" :error="error('heading')" />
        <fieldset class="space-y-3">
            <legend class="text-sm font-medium text-gray-700">Cards (up to 6). Phone, WhatsApp and email cards link automatically.</legend>
            <div v-for="(item, index) in data.items" :key="index" class="grid gap-3 rounded border border-gray-200 p-3 sm:grid-cols-3">
                <Field :id="`${prefix}.items.${index}.kind`" v-model="item.kind" label="Type" :options="contactKinds" :error="error(`items.${index}.kind`)" />
                <Field :id="`${prefix}.items.${index}.label`" v-model="item.label" label="Label" :error="error(`items.${index}.label`)" />
                <Field :id="`${prefix}.items.${index}.value`" v-model="item.value" :label="hint(item.kind)" :error="error(`items.${index}.value`)" />
                <button type="button" :class="removeButton" class="justify-self-start" :disabled="data.items.length <= 1" @click="data.items.splice(index, 1)">Remove card</button>
            </div>
            <button type="button" :class="smallButton" :disabled="data.items.length >= 6" @click="data.items.push({ kind: 'phone', label: '', value: '' })">Add card</button>
            <InputError :message="error('items')" />
        </fieldset>
    </div>
</template>
