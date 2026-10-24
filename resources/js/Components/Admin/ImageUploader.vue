<script setup>
import { computed, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';

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

const field = 'admin-field';
</script>

<template>
    <section class="admin-card p-6">
        <h2 class="font-medium">Images</h2>
        <p class="mt-1 text-sm text-brand-600">JPG, PNG or WebP, up to 8 MB each. The first image is the listing photo.</p>

        <label class="admin-btn-secondary mt-4 cursor-pointer focus-within:outline focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-brand-700">
            {{ upload.processing ? 'Uploading…' : 'Add images' }}
            <input type="file" accept="image/jpeg,image/png,image/webp" multiple class="sr-only" :disabled="upload.processing" @change="onFiles" />
        </label>
        <ul v-if="Object.keys(upload.errors).length" class="mt-2 text-sm text-red-700">
            <li v-for="(message, key) in upload.errors" :key="key">{{ message }}</li>
        </ul>

        <form v-if="form.images.length" class="mt-6" @submit.prevent="save">
            <ul class="divide-y divide-brand-100 rounded-lg border border-brand-200">
                <li v-for="(image, index) in form.images" :key="image.id" class="flex flex-wrap items-center gap-4 p-3">
                    <div class="relative">
                        <img :src="byId[image.id]?.url" :alt="image.alt" class="h-20 w-20 rounded-lg object-cover" />
                        <span v-if="index === 0" class="absolute left-1 top-1 rounded bg-ink/80 px-1.5 py-0.5 text-[10px] font-medium text-brand-50">Listing</span>
                    </div>
                    <div class="min-w-[12rem] flex-1 space-y-2">
                        <input v-model="image.alt" placeholder="Alt text" :class="field" :aria-label="`Alt text image ${index + 1}`" />
                        <select v-model="image.colour" :class="field" :aria-label="`Colour image ${index + 1}`">
                            <option :value="null">All colours</option>
                            <option v-for="colour in colours" :key="colour" :value="colour">{{ colour }}</option>
                        </select>
                    </div>
                    <div class="flex gap-1">
                        <button type="button" class="admin-btn-secondary min-h-8 px-2" :disabled="index === 0" aria-label="Move up" @click="move(index, -1)">↑</button>
                        <button type="button" class="admin-btn-secondary min-h-8 px-2" :disabled="index === form.images.length - 1" aria-label="Move down" @click="move(index, 1)">↓</button>
                        <button type="button" class="admin-btn-danger min-h-8 px-2" @click="remove(image)">Delete</button>
                    </div>
                </li>
            </ul>
            <button type="submit" class="admin-btn mt-4" :disabled="form.processing">Save images</button>
        </form>
    </section>
</template>
