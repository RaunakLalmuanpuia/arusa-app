<script setup>
import { inject, ref } from 'vue';
import InputError from '@/Components/InputError.vue';

defineProps({
    label: { type: String, required: true },
    error: { type: String, default: null },
    clearable: { type: Boolean, default: false },
});

const path = defineModel({ type: String, default: null });
const imageBaseUrl = inject('imageBaseUrl');
const uploading = ref(false);
const uploadError = ref(null);

// Laravel sets this cookie on every response and accepts it back as a CSRF header.
const xsrfToken = () => decodeURIComponent(document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)?.[1] ?? '');

const onFile = async (event) => {
    const file = event.target.files[0];

    if (!file) {
        return;
    }

    const body = new FormData();
    body.append('image', file);
    uploading.value = true;
    uploadError.value = null;

    try {
        const response = await fetch(route('admin.page-images.store'), {
            method: 'POST',
            body,
            headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
        });
        const isJson = response.headers.get('content-type')?.includes('json');

        if (response.status === 419) {
            uploadError.value = 'Your session expired. Reload the page and try again.';
        } else if (!isJson) {
            uploadError.value = `Upload failed (HTTP ${response.status}).`;
        } else {
            const json = await response.json();

            if (response.ok) {
                path.value = json.path;
            } else {
                uploadError.value = json.errors?.image?.[0] ?? json.message ?? `Upload failed (HTTP ${response.status}).`;
            }
        }
    } catch {
        uploadError.value = 'Upload failed. Check your connection and try again.';
    } finally {
        uploading.value = false;
        event.target.value = '';
    }
};
</script>

<template>
    <div>
        <p class="text-sm font-medium text-gray-700">{{ label }}</p>
        <div class="mt-1 flex flex-wrap items-center gap-3">
            <img v-if="path" :src="`${imageBaseUrl}${path}-800.webp`" alt="" class="h-20 w-20 rounded object-cover" />
            <label class="inline-flex cursor-pointer items-center rounded border border-gray-300 px-3 py-2 text-sm focus-within:ring-2 focus-within:ring-indigo-500 focus-within:ring-offset-2">
                {{ uploading ? 'Uploading…' : path ? 'Replace image' : 'Upload image' }}
                <input type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" :disabled="uploading" @change="onFile" />
            </label>
            <button v-if="path && clearable" type="button" class="text-sm text-red-600 hover:underline" @click="path = null">Remove image</button>
        </div>
        <InputError class="mt-2" :message="uploadError ?? error" />
    </div>
</template>
