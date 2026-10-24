<script setup>
import { onMounted, onUpdated, ref } from 'vue';

const props = defineProps({
    label: { type: String, required: true },
    for: { type: String, required: true },
    error: { type: String, default: null },
    hint: { type: String, default: null },
});

const root = ref(null);

// The control comes through the slot, so link it to the hint and error here.
const describeControl = () => {
    const control = root.value?.querySelector(`[id="${CSS.escape(props.for)}"]`);

    if (!control) {
        return;
    }

    const describedBy = [props.hint && `${props.for}-hint`, props.error && `${props.for}-error`].filter(Boolean).join(' ');

    if (describedBy) {
        control.setAttribute('aria-describedby', describedBy);
    } else {
        control.removeAttribute('aria-describedby');
    }

    if (props.error) {
        control.setAttribute('aria-invalid', 'true');
    } else {
        control.removeAttribute('aria-invalid');
    }
};

onMounted(describeControl);
onUpdated(describeControl);
</script>

<template>
    <div ref="root">
        <label :for="$props.for" class="admin-label">{{ label }}</label>
        <div class="mt-1.5">
            <slot />
        </div>
        <p v-if="hint" :id="`${$props.for}-hint`" class="mt-1.5 text-xs text-brand-500">{{ hint }}</p>
        <p v-if="error" :id="`${$props.for}-error`" class="mt-1.5 text-sm text-red-700">{{ error }}</p>
    </div>
</template>
