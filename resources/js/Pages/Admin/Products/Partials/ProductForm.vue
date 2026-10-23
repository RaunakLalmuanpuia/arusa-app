<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

defineProps({
    form: { type: Object, required: true },
    categories: { type: Array, required: true },
});

defineEmits(['submit']);
</script>

<template>
    <form class="max-w-3xl space-y-6 rounded bg-white p-6 shadow-sm" @submit.prevent="$emit('submit')">
        <div>
            <InputLabel for="name" value="Name" />
            <TextInput id="name" v-model="form.name" class="mt-1 block w-full" required />
            <InputError class="mt-2" :message="form.errors.name" />
        </div>
        <div>
            <InputLabel for="slug" value="URL slug (e.g. wolf-badge-tee)" />
            <TextInput id="slug" v-model="form.slug" class="mt-1 block w-full" required />
            <InputError class="mt-2" :message="form.errors.slug" />
        </div>
        <div>
            <InputLabel for="category_id" value="Category" />
            <select
                id="category_id"
                v-model="form.category_id"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                required
            >
                <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.label }}</option>
            </select>
            <InputError class="mt-2" :message="form.errors.category_id" />
        </div>
        <div>
            <InputLabel for="base_price" value="Price (₹)" />
            <TextInput id="base_price" v-model="form.base_price" type="number" min="1" step="0.01" class="mt-1 block w-40" required />
            <p class="mt-1 text-xs text-gray-500">Variants can override this price.</p>
            <InputError class="mt-2" :message="form.errors.base_price" />
        </div>
        <div>
            <InputLabel for="description" value="Description" />
            <textarea
                id="description"
                v-model="form.description"
                rows="6"
                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            />
            <InputError class="mt-2" :message="form.errors.description" />
        </div>
        <label class="flex items-center gap-2 text-sm text-gray-700">
            <Checkbox v-model:checked="form.is_active" />
            Visible on the store
        </label>
        <PrimaryButton :disabled="form.processing">Save</PrimaryButton>
    </form>
</template>
