<script setup>
import { provide, ref } from 'vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { blockTypes, newBlock } from '../Blocks/blockTypes.js';
import { fieldClass, removeButton, smallButton } from '../Blocks/fieldOptions.js';

const props = defineProps({
    form: { type: Object, required: true },
    imageBaseUrl: { type: String, required: true },
});

defineEmits(['submit']);

provide('imageBaseUrl', props.imageBaseUrl);

const newType = ref('text');

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
    <form class="max-w-3xl space-y-6" @submit.prevent="$emit('submit')">
        <div class="space-y-6 rounded bg-white p-6 shadow-sm">
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
        </div>

        <section class="rounded bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900">Sections</h2>
            <p class="mt-1 text-sm text-gray-600">Shown on the page from top to bottom. Start with a Hero for a large title banner.</p>

            <ol class="mt-4 space-y-4">
                <li
                    v-for="(block, index) in form.blocks"
                    :key="block.uid"
                    class="rounded border p-4"
                    :class="hasErrors(index) ? 'border-red-400' : 'border-gray-200'"
                >
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold text-gray-900">{{ index + 1 }}. {{ blockTypes[block.type].label }}</h3>
                        <div class="flex gap-1">
                            <button type="button" class="rounded border px-2 py-1 text-sm disabled:opacity-40" :disabled="index === 0" aria-label="Move up" @click="move(index, -1)">↑</button>
                            <button type="button" class="rounded border px-2 py-1 text-sm disabled:opacity-40" :disabled="index === form.blocks.length - 1" aria-label="Move down" @click="move(index, 1)">↓</button>
                            <button type="button" :class="removeButton" class="px-2" @click="remove(index)">Remove</button>
                        </div>
                    </div>
                    <component :is="blockTypes[block.type].fields" class="mt-4" :data="block.data" :errors="form.errors" :prefix="`blocks.${index}.data`" />
                </li>
            </ol>

            <div class="mt-4 flex flex-wrap items-center gap-2">
                <select v-model="newType" :class="fieldClass" class="w-auto" aria-label="Section type">
                    <option v-for="(type, key) in blockTypes" :key="key" :value="key">{{ type.label }}</option>
                </select>
                <button type="button" :class="smallButton" :disabled="form.blocks.length >= 30" @click="form.blocks.push(newBlock(newType))">Add section</button>
            </div>
            <InputError class="mt-2" :message="form.errors.blocks" />
        </section>

        <PrimaryButton :disabled="form.processing">Save</PrimaryButton>
    </form>
</template>
