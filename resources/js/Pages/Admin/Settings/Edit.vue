<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import FormField from '@/Components/Admin/FormField.vue';
import FormSection from '@/Components/Admin/FormSection.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import SaveBar from '@/Components/Admin/SaveBar.vue';

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

const contactFields = [
    { key: 'contact_phone', label: 'Contact phone', hint: '10 digits.', type: 'tel' },
    { key: 'whatsapp_number', label: 'WhatsApp number', hint: '10 digits.', type: 'tel' },
    { key: 'contact_email', label: 'Contact email', hint: null, type: 'email' },
];
</script>

<template>
    <Head title="Settings" />

    <AdminLayout>
        <template #header>
            <PageHeader title="Settings" />
        </template>

        <form class="max-w-4xl" @submit.prevent="form.put(route('admin.settings.update'))">
            <div class="admin-card divide-y divide-brand-100">
                <FormSection title="Store">
                    <FormField label="Store name" for="store_name" :error="form.errors.store_name">
                        <input id="store_name" v-model="form.store_name" type="text" class="admin-field" />
                    </FormField>
                </FormSection>
                <FormSection title="Contact" description="Shown to customers and used for order messages.">
                    <FormField v-for="field in contactFields" :key="field.key" :label="field.label" :for="field.key" :hint="field.hint" :error="form.errors[field.key]">
                        <input :id="field.key" v-model="form[field.key]" :type="field.type" class="admin-field" />
                    </FormField>
                </FormSection>
                <FormSection title="Payments">
                    <label class="flex items-center gap-3 text-sm">
                        <input v-model="form.cod_enabled" type="checkbox" class="admin-checkbox" />
                        Cash on delivery enabled
                    </label>
                </FormSection>
            </div>
            <SaveBar :form="form" />
        </form>
    </AdminLayout>
</template>
