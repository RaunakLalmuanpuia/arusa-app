<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';

const props = defineProps({
    settings: { type: Object, required: true },
});

const form = useForm({
    store_name: props.settings.store_name ?? '',
    contact_phone: props.settings.contact_phone ?? '',
    whatsapp_number: props.settings.whatsapp_number ?? '',
    contact_email: props.settings.contact_email ?? '',
    cod_enabled: props.settings.cod_enabled === '1',
});

const fields = [
    { key: 'store_name', label: 'Store name', type: 'text' },
    { key: 'contact_phone', label: 'Contact phone (10 digits)', type: 'tel' },
    { key: 'whatsapp_number', label: 'WhatsApp number (10 digits)', type: 'tel' },
    { key: 'contact_email', label: 'Contact email', type: 'email' },
];
</script>

<template>
    <Head title="Settings" />

    <AdminLayout>
        <template #header>
            <h1 class="text-xl font-semibold text-gray-900">Settings</h1>
        </template>

        <form class="max-w-xl space-y-6 rounded bg-white p-6 shadow-sm" @submit.prevent="form.put(route('admin.settings.update'))">
            <div v-for="field in fields" :key="field.key">
                <InputLabel :for="field.key" :value="field.label" />
                <TextInput :id="field.key" v-model="form[field.key]" :type="field.type" class="mt-1 block w-full" />
                <InputError class="mt-2" :message="form.errors[field.key]" />
            </div>

            <label class="flex items-center gap-2">
                <Checkbox v-model:checked="form.cod_enabled" />
                <span class="text-sm text-gray-700">Cash on delivery enabled</span>
            </label>

            <PrimaryButton :disabled="form.processing">Save</PrimaryButton>
        </form>
    </AdminLayout>
</template>
