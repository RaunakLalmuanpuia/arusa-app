<script setup>
const props = defineProps({
    genders: { type: Array, required: true },
    modelValue: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const options = [{ value: null, label: 'All' }, ...props.genders];
</script>

<template>
    <fieldset class="inline-flex border border-ink">
        <legend class="sr-only">Shop for</legend>
        <label v-for="option in options" :key="option.label" class="relative cursor-pointer">
            <input
                type="radio"
                name="gender"
                class="peer sr-only"
                :checked="modelValue === option.value"
                @change="emit('update:modelValue', option.value)"
            />
            <span
                class="flex min-h-11 min-w-20 items-center justify-center px-4 text-xs font-medium uppercase tracking-[0.2em] transition-colors duration-150 peer-checked:bg-ink peer-checked:text-brand-50 peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-brand-700 motion-reduce:transition-none"
            >
                {{ option.label }}
            </span>
        </label>
    </fieldset>
</template>
