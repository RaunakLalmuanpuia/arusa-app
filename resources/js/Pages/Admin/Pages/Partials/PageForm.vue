<script setup>
import { provide, ref } from 'vue';
import FormField from '@/Components/Admin/FormField.vue';
import FormSection from '@/Components/Admin/FormSection.vue';
import SaveBar from '@/Components/Admin/SaveBar.vue';
import PagePreview from './PagePreview.vue';
import { blockTypes, newBlock } from '../Blocks/blockTypes.js';
import { fieldClass, removeButton } from '../Blocks/fieldOptions.js';

const props = defineProps({
    form: { type: Object, required: true },
    imageBaseUrl: { type: String, required: true },
});

defineEmits(['submit']);

provide('imageBaseUrl', props.imageBaseUrl);

const newType = ref('text');
const tab = ref('edit');

const tabs = [
    { value: 'edit', label: 'Edit' },
    { value: 'preview', label: 'Preview' },
];

const move = (index, offset) => {
    const [block] = props.form.blocks.splice(index, 1);
    props.form.blocks.splice(index + offset, 0, block);
    props.form.clearErrors();
};

const remove = (index) => {
    if (window.confirm(`Remove this ${blockTypes[props.form.blocks[index].type].label} block?`)) {
        props.form.blocks.splice(index, 1);
        props.form.clearErrors();
    }
};

const hasErrors = (index) => Object.keys(props.form.errors).some((key) => key.startsWith(`blocks.${index}.`));
</script>

<template>
    <form class="max-w-4xl" @submit.prevent="$emit('submit')">
        <div class="admin-card">
            <FormSection title="Page" description="The title shows in the browser tab and in the default banner.">
                <FormField label="Title" for="title" :error="form.errors.title">
                    <input id="title" v-model="form.title" class="admin-field" required />
                </FormField>
                <FormField label="URL slug" for="slug" hint="e.g. size-guide" :error="form.errors.slug">
                    <input id="slug" v-model="form.slug" class="admin-field" required />
                </FormField>
            </FormSection>
        </div>

        <section class="admin-card mt-6">
            <div class="flex flex-wrap items-end justify-between gap-3 border-b border-brand-200 px-6 pt-5">
                <div class="pb-3">
                    <h2 class="font-medium">Sections</h2>
                    <p class="mt-1 text-sm text-brand-600">Shown on the page from top to bottom. Start with a Hero for a large title banner.</p>
                </div>
                <div role="tablist" aria-label="Sections view" class="flex gap-1">
                    <button
                        v-for="option in tabs"
                        :id="`sections-tab-${option.value}`"
                        :key="option.value"
                        type="button"
                        role="tab"
                        aria-controls="sections-panel"
                        :aria-selected="tab === option.value"
                        :class="tab === option.value ? 'border-ink text-ink' : 'border-transparent text-brand-500 hover:text-ink'"
                        class="focus-ring -mb-px border-b-2 px-3 py-2 text-sm font-medium transition-colors duration-150 motion-reduce:transition-none"
                        @click="tab = option.value"
                    >
                        {{ option.label }}
                    </button>
                </div>
            </div>

            <div id="sections-panel" role="tabpanel" :aria-labelledby="`sections-tab-${tab}`" :class="tab === 'edit' ? 'p-6' : 'p-3'">
                <template v-if="tab === 'edit'">
                    <ol class="space-y-4">
                        <li
                            v-for="(block, index) in form.blocks"
                            :key="block.uid"
                            class="rounded-xl border"
                            :class="hasErrors(index) ? 'border-red-300 bg-red-50/30' : 'border-brand-200'"
                        >
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-brand-100 px-4 py-3">
                                <h3 class="flex items-center gap-2 text-sm font-semibold">
                                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-brand-100 text-xs tabular-nums text-brand-700">{{ index + 1 }}</span>
                                    {{ blockTypes[block.type].label }}
                                </h3>
                                <div class="flex gap-1">
                                    <button type="button" class="admin-btn-secondary min-h-8 px-2" :disabled="index === 0" aria-label="Move up" @click="move(index, -1)">↑</button>
                                    <button type="button" class="admin-btn-secondary min-h-8 px-2" :disabled="index === form.blocks.length - 1" aria-label="Move down" @click="move(index, 1)">↓</button>
                                    <button type="button" :class="removeButton" @click="remove(index)">Remove</button>
                                </div>
                            </div>
                            <component :is="blockTypes[block.type].fields" class="p-4" :data="block.data" :errors="form.errors" :prefix="`blocks.${index}.data`" />
                        </li>
                    </ol>

                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <select v-model="newType" :class="fieldClass" class="w-auto" aria-label="Section type">
                            <option v-for="(type, key) in blockTypes" :key="key" :value="key">{{ type.label }}</option>
                        </select>
                        <button type="button" class="admin-btn-secondary" :disabled="form.blocks.length >= 30" @click="form.blocks.push(newBlock(newType))">Add section</button>
                    </div>
                    <p v-if="form.errors.blocks" class="mt-2 text-sm text-red-700">{{ form.errors.blocks }}</p>
                </template>
                <PagePreview v-else :form="form" @edit="tab = 'edit'" />
            </div>
        </section>

        <SaveBar :form="form" />
    </form>
</template>
