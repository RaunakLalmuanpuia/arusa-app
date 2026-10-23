<script setup>
import { computed, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    images: { type: Array, required: true },
    colours: { type: Array, default: () => [] },
    uploadUrl: { type: String, required: true },
    saveUrl: { type: String, required: true },
});

const editable = (images) => images.map(({ id, alt, colour }) => ({ id, alt, colour }));

const upload = useForm({ images: [] });
const form = useForm({ images: editable(props.images) });

// Uploads and deletes change the server list; edits not yet saved are dropped.
watch(() => props.images, (images) => {
    form.images = editable(images);
});

const byId = computed(() => Object.fromEntries(props.images.map((image) => [image.id, image])));

const onFiles = (event) => {
    upload.images = Array.from(event.target.files);
    upload.post(props.uploadUrl, {
        preserveScroll: true,
        onFinish: () => {
            event.target.value = '';
        },
    });
};

const move = (index, offset) => {
    const [image] = form.images.splice(index, 1);
    form.images.splice(index + offset, 0, image);
};

const remove = (image) => {
    router.delete(byId.value[image.id].delete_url, {
        preserveScroll: true,
        onBefore: () => window.confirm('Delete this image?'),
    });
};

const save = () => form.put(props.saveUrl, { preserveScroll: true });

const field = 'w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500';
</script>

<template>
    <section class="rounded bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-gray-900">Images</h2>
        <p class="mt-1 text-sm text-gray-600">JPG, PNG or WebP, up to 8 MB each. The first image is the listing photo.</p>

        <label class="mt-4 inline-flex cursor-pointer items-center rounded border border-gray-300 px-3 py-2 text-sm">
            {{ upload.processing ? 'Uploading…' : 'Add images' }}
            <input type="file" accept="image/jpeg,image/png,image/webp" multiple class="sr-only" :disabled="upload.processing" @change="onFiles" />
        </label>
        <ul v-if="Object.keys(upload.errors).length" class="mt-2 text-sm text-red-600">
            <li v-for="(message, key) in upload.errors" :key="key">{{ message }}</li>
        </ul>

        <form v-if="form.images.length" class="mt-6" @submit.prevent="save">
            <ul class="space-y-3">
                <li v-for="(image, index) in form.images" :key="image.id" class="flex flex-wrap items-center gap-3 border-b pb-3 last:border-0">
                    <img :src="byId[image.id]?.url" :alt="image.alt" class="h-20 w-20 rounded object-cover" />
                    <div class="flex-1 space-y-2">
                        <input v-model="image.alt" placeholder="Alt text" :class="field" :aria-label="`Alt text image ${index + 1}`" />
                        <select v-model="image.colour" :class="field" :aria-label="`Colour image ${index + 1}`">
                            <option :value="null">All colours</option>
                            <option v-for="colour in colours" :key="colour" :value="colour">{{ colour }}</option>
                        </select>
                    </div>
                    <div class="flex gap-1">
                        <button type="button" class="rounded border px-2 py-1 text-sm disabled:opacity-40" :disabled="index === 0" aria-label="Move up" @click="move(index, -1)">↑</button>
                        <button type="button" class="rounded border px-2 py-1 text-sm disabled:opacity-40" :disabled="index === form.images.length - 1" aria-label="Move down" @click="move(index, 1)">↓</button>
                        <button type="button" class="rounded px-2 py-1 text-sm text-red-600 hover:underline" @click="remove(image)">Delete</button>
                    </div>
                </li>
            </ul>
            <PrimaryButton class="mt-4" :disabled="form.processing">Save images</PrimaryButton>
        </form>
    </section>
</template>
