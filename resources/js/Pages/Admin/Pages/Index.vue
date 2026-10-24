<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import IconPlus from '@/Components/Icons/IconPlus.vue';

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
            <PageHeader title="Pages">
                <template #actions>
                    <Link :href="route('admin.pages.create')" class="admin-btn">
                        <IconPlus class="h-4 w-4" />
                        New page
                    </Link>
                </template>
            </PageHeader>
        </template>

        <ul v-if="pages.length" class="admin-card divide-y divide-brand-100">
            <li v-for="page in pages" :key="page.id" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                <div class="min-w-0">
                    <Link :href="route('admin.pages.edit', page.id)" class="focus-ring rounded font-medium text-ink hover:underline">{{ page.title }}</Link>
                    <p class="truncate font-mono text-xs text-brand-500">/pages/{{ page.slug }}</p>
                </div>
                <div class="flex gap-1">
                    <a :href="route('pages.show', page.slug)" target="_blank" rel="noopener" class="admin-btn-secondary min-h-8 px-3">View</a>
                    <button type="button" class="admin-btn-danger min-h-8 px-3" @click="deletePage(page)">Delete</button>
                </div>
            </li>
        </ul>
        <div v-else class="admin-card px-4 py-12 text-center text-sm text-brand-500">No pages yet.</div>
    </AdminLayout>
</template>
