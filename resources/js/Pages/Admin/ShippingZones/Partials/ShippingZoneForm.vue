<script setup>
import { computed } from 'vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

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
    <form class="max-w-xl space-y-6 rounded bg-white p-6 shadow-sm" @submit.prevent="$emit('submit')">
        <div>
            <InputLabel for="name" value="Name" />
            <TextInput id="name" v-model="form.name" class="mt-1 block w-full" required />
            <InputError class="mt-2" :message="form.errors.name" />
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <InputLabel for="rate" value="Shipping rate (₹)" />
                <TextInput id="rate" v-model="form.rate" type="number" min="0" step="0.01" class="mt-1 block w-full" required />
                <InputError class="mt-2" :message="form.errors.rate" />
            </div>
            <div>
                <InputLabel for="free_above" value="Free shipping from (₹, optional)" />
                <TextInput id="free_above" v-model="form.free_above" type="number" min="0" step="0.01" class="mt-1 block w-full" />
                <InputError class="mt-2" :message="form.errors.free_above" />
            </div>
        </div>
        <label class="flex items-center gap-2 text-sm text-gray-700">
            <Checkbox v-model:checked="form.cod_allowed" />
            Cash on delivery allowed
        </label>
        <div v-if="form.cod_allowed">
            <InputLabel for="cod_max" value="COD only up to (₹, optional)" />
            <TextInput id="cod_max" v-model="form.cod_max" type="number" min="1" step="0.01" class="mt-1 block w-40" />
            <InputError class="mt-2" :message="form.errors.cod_max" />
        </div>
        <div>
            <InputLabel for="pincodes" value="Pincodes (separate with commas, spaces or new lines)" />
            <textarea
                id="pincodes"
                v-model="form.pincodes"
                rows="6"
                class="mt-1 block w-full rounded-md border-gray-300 font-mono text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                required
            />
            <ul v-if="pincodeErrors.length" class="mt-2 space-y-1 text-sm text-red-600">
                <li v-for="message in pincodeErrors" :key="message">{{ message }}</li>
            </ul>
        </div>
        <label class="flex items-center gap-2 text-sm text-gray-700">
            <Checkbox v-model:checked="form.is_active" />
            Active
        </label>
        <PrimaryButton :disabled="form.processing">Save</PrimaryButton>
    </form>
</template>
