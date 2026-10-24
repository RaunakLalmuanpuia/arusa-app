<script setup>
defineProps({
    data: { type: Object, required: true },
});
</script>

<template>
    <section class="mx-auto max-w-3xl px-4">
        <h2 v-if="data.heading" class="mb-4 font-display text-2xl font-semibold sm:text-3xl">{{ data.heading }}</h2>
        <div class="overflow-x-auto border border-brand-200">
            <table class="w-full min-w-max text-left text-sm">
                <thead class="bg-brand-900 text-brand-50">
                    <tr>
                        <th
                            v-for="(column, index) in data.columns"
                            :key="index"
                            scope="col"
                            class="px-4 py-3 text-xs font-medium uppercase tracking-[0.2em]"
                            :class="{ 'sticky left-0 bg-brand-900': index === 0 }"
                        >
                            {{ column }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, rowIndex) in data.rows" :key="rowIndex" class="odd:bg-white even:bg-brand-100">
                        <template v-for="(cell, cellIndex) in row" :key="cellIndex">
                            <th v-if="cellIndex === 0" scope="row" class="sticky left-0 bg-inherit px-4 py-3 font-semibold">{{ cell }}</th>
                            <td v-else class="px-4 py-3 tabular-nums text-brand-800">{{ cell }}</td>
                        </template>
                    </tr>
                </tbody>
            </table>
        </div>
        <p v-if="data.note" class="mt-3 text-xs text-brand-700">{{ data.note }}</p>
    </section>
</template>
