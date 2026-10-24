<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import IconExternal from '@/Components/Icons/IconExternal.vue';
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
            <PageHeader :title="page.title" :back="{ label: 'Pages', href: route('admin.pages.index') }">
                <template #actions>
                    <a :href="route('pages.show', page.slug)" target="_blank" rel="noopener" class="admin-btn-secondary">
                        <IconExternal class="h-4 w-4" />
                        View on store
                    </a>
                </template>
            </PageHeader>
        </template>

        <PageForm :form="form" :image-base-url="imageBaseUrl" @submit="form.transform(withoutUids).put(route('admin.pages.update', page.id))" />
    </AdminLayout>
</template>
