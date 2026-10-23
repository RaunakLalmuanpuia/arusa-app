<script setup>
import { reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({
    rows: { type: Object, required: true },
    columns: { type: Array, required: true },
    filters: { type: Object, default: () => ({}) },
});

const query = reactive({ search: '', ...props.filters });

const apply = () => {
    router.get(window.location.pathname, query, { preserveState: true, replace: true });
};
</script>

<template>
    <div class="space-y-4">
        <form class="flex flex-wrap items-end gap-3" @submit.prevent="apply">
            <input
                v-model="query.search"
                type="search"
                placeholder="Search…"
                aria-label="Search"
                class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:w-64"
            />
            <slot name="filters" :query="query" />
            <button type="submit" class="rounded bg-gray-800 px-3 py-2 text-sm font-medium text-white">Filter</button>
        </form>

        <div class="overflow-x-auto rounded bg-white shadow-sm">
            <table class="w-full text-left text-sm">
                <thead class="border-b text-gray-500">
                    <tr>
                        <th v-for="column in columns" :key="column.key" class="px-4 py-3 font-medium">{{ column.label }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows.data" :key="row.id" class="border-b last:border-0">
                        <td v-for="column in columns" :key="column.key" class="px-4 py-3">
                            <slot :name="`cell-${column.key}`" :row="row">{{ row[column.key] }}</slot>
                        </td>
                    </tr>
                    <tr v-if="rows.data.length === 0">
                        <td :colspan="columns.length" class="px-4 py-6 text-center text-gray-500">
                            <slot name="empty">Nothing found.</slot>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav v-if="rows.total > 0" class="flex items-center justify-between text-sm text-gray-600" aria-label="Pagination">
            <span>{{ rows.from }}–{{ rows.to }} of {{ rows.total }}</span>
            <div class="flex gap-2">
                <Link v-if="rows.prev_page_url" :href="rows.prev_page_url" preserve-state class="rounded border bg-white px-3 py-1">Previous</Link>
                <Link v-if="rows.next_page_url" :href="rows.next_page_url" preserve-state class="rounded border bg-white px-3 py-1">Next</Link>
            </div>
        </nav>
    </div>
</template>
