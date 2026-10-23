<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
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
            <h1 class="text-xl font-semibold text-gray-900">Edit shipping zone</h1>
        </template>

        <ShippingZoneForm :form="form" @submit="submit" />
    </AdminLayout>
</template>
