<script setup>
import { computed } from 'vue';
import Field from './Field.vue';
import { fieldClass, removeButton, smallButton } from './fieldOptions.js';

const props = defineProps({
    data: { type: Object, required: true },
    errors: { type: Object, required: true },
    prefix: { type: String, required: true },
});

const error = (field) => props.errors[`${props.prefix}.${field}`];

// Cell errors have many keys; list them together under the table.
const tableErrors = computed(() => Object.entries(props.errors)
    .filter(([key]) => key.startsWith(`${props.prefix}.columns`) || key.startsWith(`${props.prefix}.rows`)));

const addColumn = () => {
    props.data.columns.push('');
    props.data.rows.forEach((row) => row.push(''));
};

const removeColumn = (index) => {
    props.data.columns.splice(index, 1);
    props.data.rows.forEach((row) => row.splice(index, 1));
};

const addRow = () => props.data.rows.push(props.data.columns.map(() => ''));
</script>

<template>
    <div class="space-y-4">
        <Field :id="`${prefix}.heading`" v-model="data.heading" label="Heading (optional)" :error="error('heading')" />
        <div class="overflow-x-auto">
            <table class="text-sm">
                <thead>
                    <tr>
                        <th v-for="(column, index) in data.columns" :key="index" class="min-w-32 p-1 text-left align-top font-normal">
                            <input v-model="data.columns[index]" :class="fieldClass" class="font-semibold" placeholder="Column heading" :aria-label="`Column ${index + 1} heading`" />
                            <button type="button" :class="removeButton" class="mt-1 text-xs" :disabled="data.columns.length <= 2" @click="removeColumn(index)">Remove column</button>
                        </th>
                        <th />
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, rowIndex) in data.rows" :key="rowIndex">
                        <td v-for="(cell, cellIndex) in row" :key="cellIndex" class="p-1">
                            <input v-model="row[cellIndex]" :class="fieldClass" :aria-label="`Row ${rowIndex + 1}, ${data.columns[cellIndex] || `column ${cellIndex + 1}`}`" />
                        </td>
                        <td class="p-1">
                            <button type="button" :class="removeButton" :disabled="data.rows.length <= 1" @click="data.rows.splice(rowIndex, 1)">Remove</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="flex gap-2">
            <button type="button" :class="smallButton" :disabled="data.columns.length >= 6" @click="addColumn">Add column</button>
            <button type="button" :class="smallButton" :disabled="data.rows.length >= 30" @click="addRow">Add row</button>
        </div>
        <ul v-if="tableErrors.length" class="text-sm text-red-600">
            <li v-for="[key, message] in tableErrors" :key="key">{{ message }}</li>
        </ul>
        <Field :id="`${prefix}.note`" v-model="data.note" label="Note under the table (optional)" :error="error('note')" />
    </div>
</template>
