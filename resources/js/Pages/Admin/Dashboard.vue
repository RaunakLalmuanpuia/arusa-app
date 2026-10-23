<script setup>
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
</script>

<template>
    <Head title="Admin" />

    <AdminLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">Dashboard</h1>
        </template>

        <p class="text-sm text-gray-600">Orders and stock overview arrive in Phase 5.</p>
    </AdminLayout>
</template>
