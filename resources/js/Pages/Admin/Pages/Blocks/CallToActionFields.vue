<script setup>
import Field from './Field.vue';

const props = defineProps({
    data: { type: Object, required: true },
    errors: { type: Object, required: true },
    prefix: { type: String, required: true },
});

const error = (field) => props.errors[`${props.prefix}.${field}`];
</script>

<template>
    <div class="space-y-4">
        <Field :id="`${prefix}.heading`" v-model="data.heading" label="Heading" :error="error('heading')" />
        <Field :id="`${prefix}.text`" v-model="data.text" label="Text (optional)" :rows="2" :error="error('text')" />
        <Field :id="`${prefix}.button_label`" v-model="data.button_label" label="Button label" :error="error('button_label')" />
        <Field
            :id="`${prefix}.button_url`"
            v-model="data.button_url"
            label="Button link: a store path like /pages/size-guide, or an https:// address"
            :error="error('button_url')"
        />
    </div>
</template>
