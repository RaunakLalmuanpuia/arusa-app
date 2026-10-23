<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

defineProps({
    form: { type: Object, required: true },
});

defineEmits(['submit']);
</script>

<template>
    <form class="max-w-3xl space-y-6 rounded bg-white p-6 shadow-sm" @submit.prevent="$emit('submit')">
        <div>
            <InputLabel for="title" value="Title" />
            <TextInput id="title" v-model="form.title" class="mt-1 block w-full" required />
            <InputError class="mt-2" :message="form.errors.title" />
        </div>
        <div>
            <InputLabel for="slug" value="URL slug (e.g. size-guide)" />
            <TextInput id="slug" v-model="form.slug" class="mt-1 block w-full" required />
            <InputError class="mt-2" :message="form.errors.slug" />
        </div>
        <div>
            <InputLabel for="body" value="Content (Markdown)" />
            <textarea
                id="body"
                v-model="form.body"
                rows="16"
                class="mt-1 block w-full rounded-md border-gray-300 font-mono text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                required
            />
            <InputError class="mt-2" :message="form.errors.body" />
        </div>
        <PrimaryButton :disabled="form.processing">Save</PrimaryButton>
    </form>
</template>
