<script setup>
defineProps({
    form: { type: Object, required: true },
    label: { type: String, default: 'Save' },
});
</script>

<template>
    <div class="sticky bottom-4 z-20 mt-6 flex items-center justify-between gap-3 rounded-xl border border-brand-200 bg-white/95 px-4 py-3 shadow-lg shadow-ink/5 backdrop-blur">
        <Transition
            enter-active-class="transition-opacity duration-150 motion-reduce:transition-none"
            enter-from-class="opacity-0"
            leave-active-class="transition-opacity duration-150 motion-reduce:transition-none"
            leave-to-class="opacity-0"
        >
            <p v-if="form.isDirty" class="flex items-center gap-2 text-sm text-brand-700">
                <span class="h-2 w-2 rounded-full bg-amber-500" aria-hidden="true" />
                Unsaved changes
            </p>
        </Transition>
        <div class="ml-auto flex gap-2">
            <button v-if="form.isDirty" type="button" class="admin-btn-secondary" :disabled="form.processing" @click="form.reset(); form.clearErrors()">Discard</button>
            <button type="submit" class="admin-btn" :disabled="form.processing">{{ form.processing ? 'Saving…' : label }}</button>
        </div>
    </div>
</template>
