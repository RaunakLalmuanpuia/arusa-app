<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ShippingZoneForm from './Partials/ShippingZoneForm.vue';
import { toPaise } from '@/money';

const form = useForm({ name: '', rate: '', free_above: '', cod_allowed: true, cod_max: '', is_active: true, pincodes: '' });

const submit = () => {
    form.transform((data) => ({
        ...data,
        rate: toPaise(data.rate),
        free_above: toPaise(data.free_above),
        cod_max: data.cod_allowed ? toPaise(data.cod_max) : null,
    })).post(route('admin.shipping-zones.store'));
};
</script>

<template>
    <Head title="New shipping zone" />

    <AdminLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">New shipping zone</h1>
        </template>

        <ShippingZoneForm :form="form" @submit="submit" />
    </AdminLayout>
</template>
