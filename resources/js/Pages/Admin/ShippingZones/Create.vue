<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
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
            <PageHeader title="New shipping zone" :back="{ label: 'Shipping zones', href: route('admin.shipping-zones.index') }" />
        </template>

        <ShippingZoneForm :form="form" @submit="submit" />
    </AdminLayout>
</template>
