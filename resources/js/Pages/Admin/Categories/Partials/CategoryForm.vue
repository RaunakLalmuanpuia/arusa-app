<script setup>
import FormField from '@/Components/Admin/FormField.vue';
import FormSection from '@/Components/Admin/FormSection.vue';
import SaveBar from '@/Components/Admin/SaveBar.vue';

defineProps({
    form: { type: Object, required: true },
    parents: { type: Array, required: true },
    imageUrl: { type: String, default: null },
});

defineEmits(['submit']);
</script>

<template>
    <form class="max-w-4xl" @submit.prevent="$emit('submit')">
        <div class="admin-card divide-y divide-brand-100">
            <FormSection title="Details" description="How the category is named and where it sits.">
                <FormField label="Name" for="name" :error="form.errors.name">
                    <input id="name" v-model="form.name" class="admin-field" required />
                </FormField>
                <FormField label="URL slug" for="slug" hint="e.g. t-shirts" :error="form.errors.slug">
                    <input id="slug" v-model="form.slug" class="admin-field" required />
                </FormField>
                <FormField label="Parent category" for="parent_id" :error="form.errors.parent_id">
                    <select id="parent_id" v-model="form.parent_id" class="admin-field">
                        <option :value="null">None (top level)</option>
                        <option v-for="parent in parents" :key="parent.id" :value="parent.id">{{ parent.name }}</option>
                    </select>
                </FormField>
                <FormField label="Sort order" for="sort_order" hint="Lower shows first." :error="form.errors.sort_order">
                    <input id="sort_order" v-model="form.sort_order" type="number" min="0" class="admin-field w-32" required />
                </FormField>
            </FormSection>
            <FormSection title="Image" description="Shown on the home page tile.">
                <FormField label="Image file" for="image" hint="JPG, PNG or WebP." :error="form.errors.image">
                    <img v-if="imageUrl && !form.image" :src="imageUrl" alt="" class="mb-3 aspect-[2/1] w-64 rounded-lg object-cover" />
                    <input
                        id="image"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        class="block w-full text-sm text-brand-700 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-ink hover:file:bg-brand-200"
                        @input="form.image = $event.target.files[0] ?? null"
                    />
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
