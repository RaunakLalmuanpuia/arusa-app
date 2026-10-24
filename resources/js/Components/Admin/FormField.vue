<script setup>
defineProps({
    label: { type: String, required: true },
    for: { type: String, required: true },
    error: { type: String, default: null },
    hint: { type: String, default: null },
});
</script>

<template>
    <div>
        <label :for="$props.for" class="admin-label">{{ label }}</label>
        <div class="mt-1.5">
            <slot />
        </div>
        <p v-if="hint" class="mt-1.5 text-xs text-brand-500">{{ hint }}</p>
        <p v-if="error" :id="`${$props.for}-error`" class="mt-1.5 text-sm text-red-700">{{ error }}</p>
    </div>
</template>
