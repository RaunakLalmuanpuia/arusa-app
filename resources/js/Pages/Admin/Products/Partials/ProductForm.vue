<script setup>
import FormField from '@/Components/Admin/FormField.vue';
import FormSection from '@/Components/Admin/FormSection.vue';
import SaveBar from '@/Components/Admin/SaveBar.vue';

defineProps({
    form: { type: Object, required: true },
    categories: { type: Array, required: true },
    genders: { type: Array, required: true },
});

defineEmits(['submit']);
</script>

<template>
    <form @submit.prevent="$emit('submit')">
        <div class="admin-card divide-y divide-brand-100">
            <FormSection title="Details" description="Name, address and where the product is listed.">
                <FormField label="Name" for="name" :error="form.errors.name">
                    <input id="name" v-model="form.name" class="admin-field" required />
                </FormField>
                <FormField label="URL slug" for="slug" hint="e.g. wolf-badge-tee" :error="form.errors.slug">
                    <input id="slug" v-model="form.slug" class="admin-field" required />
                </FormField>
                <div class="grid gap-5 sm:grid-cols-2">
                    <FormField label="Category" for="category_id" :error="form.errors.category_id">
                        <select id="category_id" v-model="form.category_id" class="admin-field" required>
                            <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.label }}</option>
                        </select>
                    </FormField>
                    <FormField label="Gender" for="gender" :error="form.errors.gender">
                        <select id="gender" v-model="form.gender" class="admin-field">
                            <option :value="null">None (not clothing)</option>
                            <option v-for="gender in genders" :key="gender.value" :value="gender.value">{{ gender.label }}</option>
                        </select>
                    </FormField>
                </div>
            </FormSection>
            <FormSection title="Pricing">
                <FormField label="Price (₹)" for="base_price" hint="Variants can override this price." :error="form.errors.base_price">
                    <input id="base_price" v-model="form.base_price" type="number" min="1" step="0.01" class="admin-field w-40" required />
                </FormField>
            </FormSection>
            <FormSection title="Description">
                <FormField label="Description" for="description" :error="form.errors.description">
                    <textarea id="description" v-model="form.description" rows="6" class="admin-field" />
                </FormField>
            </FormSection>
            <FormSection title="Visibility">
                <label class="flex items-center gap-3 text-sm">
                    <input v-model="form.is_active" type="checkbox" class="admin-checkbox" />
                    Visible on the store
                </label>
            </FormSection>
        </div>
        <SaveBar :form="form" />
    </form>
</template>
