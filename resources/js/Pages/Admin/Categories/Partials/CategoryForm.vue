<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

defineProps({
    form: { type: Object, required: true },
    parents: { type: Array, required: true },
    imageUrl: { type: String, default: null },
});

defineEmits(['submit']);
</script>

<template>
    <form class="max-w-xl space-y-6 rounded bg-white p-6 shadow-sm" @submit.prevent="$emit('submit')">
        <div>
            <InputLabel for="name" value="Name" />
            <TextInput id="name" v-model="form.name" class="mt-1 block w-full" required />
            <InputError class="mt-2" :message="form.errors.name" />
        </div>
        <div>
            <InputLabel for="slug" value="URL slug (e.g. t-shirts)" />
            <TextInput id="slug" v-model="form.slug" class="mt-1 block w-full" required />
            <InputError class="mt-2" :message="form.errors.slug" />
        </div>
        <div>
            <InputLabel for="parent_id" value="Parent category" />
            <select
                id="parent_id"
                v-model="form.parent_id"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            >
                <option :value="null">None (top level)</option>
                <option v-for="parent in parents" :key="parent.id" :value="parent.id">{{ parent.name }}</option>
            </select>
            <InputError class="mt-2" :message="form.errors.parent_id" />
        </div>
        <div>
            <InputLabel for="image" value="Image (shown on the home page tile)" />
            <img v-if="imageUrl && !form.image" :src="imageUrl" alt="" class="mt-2 aspect-[2/1] w-64 rounded object-cover" />
            <input
                id="image"
                type="file"
                accept="image/jpeg,image/png,image/webp"
                class="mt-2 block w-full text-sm text-gray-700"
                @input="form.image = $event.target.files[0] ?? null"
            />
            <InputError class="mt-2" :message="form.errors.image" />
        </div>
        <div>
            <InputLabel for="sort_order" value="Sort order (lower shows first)" />
            <TextInput id="sort_order" v-model="form.sort_order" type="number" min="0" class="mt-1 block w-32" required />
            <InputError class="mt-2" :message="form.errors.sort_order" />
        </div>
        <label class="flex items-center gap-2 text-sm text-gray-700">
            <Checkbox v-model:checked="form.is_active" />
            Visible on the store
        </label>
        <PrimaryButton :disabled="form.processing">Save</PrimaryButton>
    </form>
</template>
