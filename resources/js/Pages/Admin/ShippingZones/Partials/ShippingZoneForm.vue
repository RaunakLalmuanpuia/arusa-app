<script setup>
import { computed } from 'vue';
import FormField from '@/Components/Admin/FormField.vue';
import FormSection from '@/Components/Admin/FormSection.vue';
import SaveBar from '@/Components/Admin/SaveBar.vue';

const props = defineProps({
    form: { type: Object, required: true },
});

defineEmits(['submit']);

const pincodeErrors = computed(() =>
    Object.entries(props.form.errors)
        .filter(([key]) => key.startsWith('pincodes'))
        .map(([, message]) => message),
);
</script>

<template>
    <form class="max-w-4xl" @submit.prevent="$emit('submit')">
        <div class="admin-card divide-y divide-brand-100">
            <FormSection title="Details">
                <FormField label="Name" for="name" :error="form.errors.name">
                    <input id="name" v-model="form.name" class="admin-field" required />
                </FormField>
            </FormSection>
            <FormSection title="Rates" description="What delivery costs in this zone, and whether cash on delivery is offered.">
                <div class="grid gap-5 sm:grid-cols-2">
                    <FormField label="Shipping rate (₹)" for="rate" :error="form.errors.rate">
                        <input id="rate" v-model="form.rate" type="number" min="0" step="0.01" class="admin-field" required />
                    </FormField>
                    <FormField label="Free shipping from (₹)" for="free_above" hint="Optional." :error="form.errors.free_above">
                        <input id="free_above" v-model="form.free_above" type="number" min="0" step="0.01" class="admin-field" />
                    </FormField>
                </div>
                <label class="flex items-center gap-3 text-sm">
                    <input v-model="form.cod_allowed" type="checkbox" class="admin-checkbox" />
                    Cash on delivery allowed
                </label>
                <FormField v-if="form.cod_allowed" label="COD only up to (₹)" for="cod_max" hint="Optional." :error="form.errors.cod_max">
                    <input id="cod_max" v-model="form.cod_max" type="number" min="1" step="0.01" class="admin-field w-40" />
                </FormField>
            </FormSection>
            <FormSection title="Pincodes" description="Separate with commas, spaces or new lines.">
                <FormField label="Pincodes" for="pincodes">
                    <textarea id="pincodes" v-model="form.pincodes" rows="6" class="admin-field font-mono" required />
                </FormField>
                <ul v-if="pincodeErrors.length" class="space-y-1 text-sm text-red-700">
                    <li v-for="message in pincodeErrors" :key="message">{{ message }}</li>
                </ul>
            </FormSection>
            <FormSection title="Status">
                <label class="flex items-center gap-3 text-sm">
                    <input v-model="form.is_active" type="checkbox" class="admin-checkbox" />
                    Active
                </label>
            </FormSection>
        </div>
        <SaveBar :form="form" />
    </form>
</template>
