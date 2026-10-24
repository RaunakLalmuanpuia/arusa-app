<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageForm from './Partials/PageForm.vue';
import { withoutUids, withUid } from './Blocks/blockTypes.js';

const props = defineProps({
    page: { type: Object, required: true },
    imageBaseUrl: { type: String, required: true },
});

const form = useForm({ title: props.page.title, slug: props.page.slug, blocks: props.page.blocks.map(withUid) });
</script>

<template>
    <Head :title="`Edit ${page.title}`" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold text-gray-900">Edit page</h1>
                <Link :href="route('pages.show', page.slug)" class="text-sm text-gray-600 underline">View on store</Link>
            </div>
        </template>

        <PageForm :form="form" :image-base-url="imageBaseUrl" @submit="form.transform(withoutUids).put(route('admin.pages.update', page.id))" />
    </AdminLayout>
</template>
