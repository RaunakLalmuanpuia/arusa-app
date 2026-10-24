<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import ShippingZoneForm from './Partials/ShippingZoneForm.vue';
import { toPaise } from '@/money';

const props = defineProps({
    zone: { type: Object, required: true },
});

const rupees = (paise) => (paise === null ? '' : paise / 100);

const form = useForm({
    ...props.zone,
    rate: rupees(props.zone.rate),
    free_above: rupees(props.zone.free_above),
    cod_max: rupees(props.zone.cod_max),
});

const submit = () => {
    form.transform((data) => ({
        ...data,
        rate: toPaise(data.rate),
        free_above: toPaise(data.free_above),
        cod_max: data.cod_allowed ? toPaise(data.cod_max) : null,
    })).put(route('admin.shipping-zones.update', props.zone.id));
};
</script>

<template>
    <Head :title="`Edit ${zone.name}`" />

    <AdminLayout>
        <template #header>
            <PageHeader :title="zone.name" :back="{ label: 'Shipping zones', href: route('admin.shipping-zones.index') }" />
        </template>

        <ShippingZoneForm :form="form" @submit="submit" />
    </AdminLayout>
</template>
