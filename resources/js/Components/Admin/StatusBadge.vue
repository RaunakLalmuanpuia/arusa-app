<script setup>
import { computed } from 'vue';

const props = defineProps({
    value: { type: String, required: true },
    label: { type: String, required: true },
});

const amber = 'bg-amber-50 text-amber-800 ring-amber-200';
const sky = 'bg-sky-50 text-sky-800 ring-sky-200';
const emerald = 'bg-emerald-50 text-emerald-800 ring-emerald-200';
const red = 'bg-red-50 text-red-800 ring-red-200';

// Keys are FulfilmentStatus and PaymentStatus values, plus "active" for visibility columns.
const tones = {
    pending: amber,
    awaiting_payment: amber,
    confirmed: sky,
    packed: sky,
    dispatched: 'bg-violet-50 text-violet-800 ring-violet-200',
    delivered: emerald,
    paid: emerald,
    active: emerald,
    cancelled: red,
    failed: red,
};

const tone = computed(() => tones[props.value] ?? 'bg-brand-100 text-brand-700 ring-brand-200');
</script>

<template>
    <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset" :class="tone">
        <span class="h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true" />
        {{ label }}
    </span>
</template>
