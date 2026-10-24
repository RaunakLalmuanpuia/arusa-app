<script setup>
import { inject, ref } from 'vue';
import { xsrfToken } from '@/xsrfToken';

defineProps({
    label: { type: String, required: true },
    error: { type: String, default: null },
    clearable: { type: Boolean, default: false },
});

const path = defineModel({ type: String, default: null });
const imageBaseUrl = inject('imageBaseUrl');
const uploading = ref(false);
const uploadError = ref(null);

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
        <p class="admin-label">{{ label }}</p>
        <div class="mt-1.5 flex flex-wrap items-center gap-3">
            <img v-if="path" :src="`${imageBaseUrl}${path}-800.webp`" alt="" class="h-20 w-20 rounded-lg object-cover" />
            <label class="admin-btn-secondary cursor-pointer focus-within:outline focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-brand-700">
                {{ uploading ? 'Uploading…' : path ? 'Replace image' : 'Upload image' }}
                <input type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" :disabled="uploading" @change="onFile" />
            </label>
            <button v-if="path && clearable" type="button" class="admin-btn-danger min-h-8 px-2" @click="path = null">Remove image</button>
        </div>
        <p v-if="uploadError ?? error" class="mt-1.5 text-sm text-red-700">{{ uploadError ?? error }}</p>
    </div>
</template>
