<script setup>
import { onMounted, ref } from 'vue';
import PageBlocks from '@/Components/Store/Blocks/PageBlocks.vue';
import IconAlert from '@/Components/Icons/IconAlert.vue';
import { withoutUids } from '../Blocks/blockTypes.js';
import { xsrfToken } from '@/xsrfToken';

const props = defineProps({
    form: { type: Object, required: true },
});

defineEmits(['edit']);

const state = ref('loading');
const blocks = ref([]);

const load = async () => {
    state.value = 'loading';

    try {
        const response = await fetch(route('admin.pages.preview'), {
            method: 'POST',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
            body: JSON.stringify({ blocks: withoutUids({ blocks: props.form.blocks }).blocks }),
        });

        if (response.status === 422) {
            const { errors } = await response.json();
            props.form.clearErrors(...Object.keys(props.form.errors).filter((key) => key.startsWith('blocks')));
            props.form.setError(Object.fromEntries(Object.entries(errors).map(([key, messages]) => [key, messages[0]])));
            state.value = 'invalid';
        } else if (response.ok) {
            blocks.value = (await response.json()).blocks;
            state.value = 'ready';
        } else {
            state.value = 'failed';
        }
    } catch {
        state.value = 'failed';
    }
};

onMounted(load);
</script>

<template>
    <div>
        <div v-if="state === 'loading'" class="space-y-4 p-4" aria-busy="true" aria-label="Loading preview">
            <div class="h-48 animate-pulse rounded-lg bg-brand-100 motion-reduce:animate-none" />
            <div class="h-4 w-2/3 animate-pulse rounded bg-brand-100 motion-reduce:animate-none" />
            <div class="h-4 w-1/2 animate-pulse rounded bg-brand-100 motion-reduce:animate-none" />
        </div>
        <div v-else-if="state === 'invalid'" class="flex flex-col items-center gap-3 px-6 py-12 text-center text-sm text-brand-700" role="alert">
            <IconAlert class="h-6 w-6 text-amber-600" />
            Fix the highlighted fields to preview.
            <button type="button" class="admin-btn-secondary" @click="$emit('edit')">Back to edit</button>
        </div>
        <div v-else-if="state === 'failed'" class="flex flex-col items-center gap-3 px-6 py-12 text-center text-sm text-brand-700" role="alert">
            <IconAlert class="h-6 w-6 text-red-600" />
            Preview unavailable, try again.
            <button type="button" class="admin-btn-secondary" @click="load">Try again</button>
        </div>
        <div v-else class="overflow-hidden rounded-lg border border-brand-200 bg-brand-50 font-sans text-ink">
            <PageBlocks :title="form.title || 'Untitled page'" :blocks="blocks" />
        </div>
    </div>
</template>
