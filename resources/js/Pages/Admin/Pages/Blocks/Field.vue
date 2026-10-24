<script setup>
import FormField from '@/Components/Admin/FormField.vue';
import { fieldClass } from './fieldOptions.js';

defineProps({
    id: { type: String, required: true },
    label: { type: String, required: true },
    error: { type: String, default: null },
    rows: { type: Number, default: 0 },
    options: { type: Array, default: null },
});

const value = defineModel({ type: String, default: '' });
</script>

<template>
    <FormField :label="label" :for="id" :error="error">
        <select v-if="options" :id="id" v-model="value" :class="fieldClass">
            <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
        <textarea v-else-if="rows" :id="id" v-model="value" :rows="rows" :class="fieldClass" />
        <input v-else :id="id" v-model="value" :class="fieldClass" />
    </FormField>
</template>
