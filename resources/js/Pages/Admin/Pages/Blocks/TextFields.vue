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
    <div>
        <Field
            :id="`${prefix}.markdown`"
            v-model="data.markdown"
            label="Text (Markdown: ## heading, **bold**, - list, [link](https://…), tables)"
            :rows="12"
            class="font-mono"
            :error="error('markdown')"
        />
    </div>
</template>
