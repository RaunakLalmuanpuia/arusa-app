<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    title: { type: String, required: true },
    back: { type: Object, default: null },
    meta: { type: String, default: null },
});
</script>

<template>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="min-w-0">
            <Link v-if="back" :href="back.href" class="focus-ring rounded text-sm text-brand-600 hover:text-ink">← {{ back.label }}</Link>
            <div class="mt-1 flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-semibold tracking-tight text-ink">{{ title }}</h1>
                <slot name="badges" />
            </div>
            <p v-if="meta" class="mt-1 text-sm text-brand-600">{{ meta }}</p>
        </div>
        <div v-if="$slots.actions" class="flex flex-wrap gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
