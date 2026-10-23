<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({
    pages: { type: Array, required: true },
});

const deletePage = (page) => {
    router.delete(route('admin.pages.destroy', page.id), {
        onBefore: () => window.confirm(`Delete "${page.title}"?`),
    });
};
</script>

<template>
    <Head title="Pages" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold text-gray-900">Pages</h1>
                <Link :href="route('admin.pages.create')" class="rounded bg-gray-800 px-3 py-2 text-sm font-medium text-white">New page</Link>
            </div>
        </template>

        <div class="overflow-x-auto rounded bg-white shadow-sm">
            <table class="w-full text-left text-sm">
                <thead class="border-b text-gray-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">Title</th>
                        <th class="px-4 py-3 font-medium">URL</th>
                        <th class="px-4 py-3" />
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="page in pages" :key="page.id" class="border-b last:border-0">
                        <td class="px-4 py-3">
                            <Link :href="route('admin.pages.edit', page.id)" class="font-medium text-gray-900 hover:underline">{{ page.title }}</Link>
                        </td>
                        <td class="px-4 py-3 text-gray-500">/pages/{{ page.slug }}</td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" class="text-red-600 hover:underline" @click="deletePage(page)">Delete</button>
                        </td>
                    </tr>
                    <tr v-if="pages.length === 0">
                        <td colspan="3" class="px-4 py-6 text-center text-gray-500">No pages yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>
