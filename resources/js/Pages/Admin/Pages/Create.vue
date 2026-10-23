<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageForm from './Partials/PageForm.vue';

const form = useForm({ title: '', slug: '', body: '' });
</script>

<template>
    <Head title="New page" />

    <AdminLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">New page</h1>
        </template>

        <PageForm :form="form" @submit="form.post(route('admin.pages.store'))" />
    </AdminLayout>
</template>
