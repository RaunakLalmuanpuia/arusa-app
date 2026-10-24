<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
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
    <div>
        <InputLabel :for="id" :value="label" />
        <select v-if="options" :id="id" v-model="value" :class="fieldClass" class="mt-1">
            <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
        <textarea v-else-if="rows" :id="id" v-model="value" :rows="rows" :class="fieldClass" class="mt-1" />
        <input v-else :id="id" v-model="value" :class="fieldClass" class="mt-1" />
        <InputError class="mt-2" :message="error" />
    </div>
</template>
