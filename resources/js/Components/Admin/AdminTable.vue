<script setup>
import { reactive, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import IconSearch from '@/Components/Icons/IconSearch.vue';
import IconSliders from '@/Components/Icons/IconSliders.vue';

const props = defineProps({
    rows: { type: Object, required: true },
    columns: { type: Array, required: true },
    filters: { type: Object, default: () => ({}) },
});

const query = reactive({ search: '', ...props.filters });
const areFiltersOpen = ref(false);

const apply = () => {
    router.get(window.location.pathname, query, { preserveState: true, replace: true });
};
</script>

<template>
    <div class="space-y-4">
        <form class="flex flex-wrap items-center gap-2" @submit.prevent="apply">
            <div class="relative w-full sm:w-72">
                <IconSearch class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-brand-400" />
                <input v-model="query.search" type="search" placeholder="Search…" aria-label="Search" class="admin-field pl-9" />
            </div>
            <button
                v-if="$slots.filters"
                type="button"
                class="admin-btn-secondary md:hidden"
                aria-controls="table-filters"
                :aria-expanded="areFiltersOpen"
                @click="areFiltersOpen = !areFiltersOpen"
            >
                <IconSliders class="h-4 w-4" />
                Filters
            </button>
            <div v-if="$slots.filters" id="table-filters" :class="areFiltersOpen ? 'flex' : 'hidden'" class="w-full flex-wrap items-center gap-2 md:flex md:w-auto">
                <slot name="filters" :query="query" />
            </div>
            <button type="submit" class="admin-btn">Apply</button>
        </form>

        <template v-if="rows.data.length">
            <div class="admin-card hidden overflow-x-auto md:block">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-brand-200 bg-brand-50/60 text-xs uppercase tracking-wide text-brand-500">
                        <tr>
                            <th v-for="column in columns" :key="column.key" scope="col" class="px-4 py-3 font-medium" :class="{ 'text-right': column.align === 'right' }">{{ column.label }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-100">
                        <tr v-for="row in rows.data" :key="row.id" class="transition-colors duration-150 hover:bg-brand-50/60 motion-reduce:transition-none">
                            <td v-for="column in columns" :key="column.key" class="px-4 py-3 align-middle" :class="{ 'text-right': column.align === 'right' }">
                                <slot :name="`cell-${column.key}`" :row="row">{{ row[column.key] }}</slot>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <ul class="space-y-3 md:hidden">
                <li v-for="row in rows.data" :key="row.id" class="admin-card p-4">
                    <div class="font-medium">
                        <slot :name="`cell-${columns[0].key}`" :row="row">{{ row[columns[0].key] }}</slot>
                    </div>
                    <dl class="mt-3 grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-2 text-sm">
                        <template v-for="column in columns.slice(1)" :key="column.key">
                            <dt class="text-brand-500">{{ column.label }}</dt>
                            <dd>
                                <slot :name="`cell-${column.key}`" :row="row">{{ row[column.key] }}</slot>
                            </dd>
                        </template>
                    </dl>
                </li>
            </ul>
        </template>
        <div v-else class="admin-card px-4 py-12 text-center text-sm text-brand-500">
            <slot name="empty">Nothing found.</slot>
        </div>

        <nav v-if="rows.total > 0" class="flex items-center justify-between text-sm text-brand-600" aria-label="Pagination">
            <span class="tabular-nums">{{ rows.from }}–{{ rows.to }} of {{ rows.total }}</span>
            <div class="flex gap-2">
                <Link v-if="rows.prev_page_url" :href="rows.prev_page_url" preserve-state class="admin-btn-secondary min-h-9">Previous</Link>
                <Link v-if="rows.next_page_url" :href="rows.next_page_url" preserve-state class="admin-btn-secondary min-h-9">Next</Link>
            </div>
        </nav>
    </div>
</template>
