<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    points: { type: Array, required: true },
    format: { type: Function, required: true },
});

// Chart drawing units; the SVG stretches to its box (preserveAspectRatio="none").
const width = 300;
const height = 100;

const activeIndex = ref(null);
const max = computed(() => Math.max(0, ...props.points.map((point) => point.value)));
const total = computed(() => props.points.reduce((sum, point) => sum + point.value, 0));
const slotWidth = computed(() => width / props.points.length);
const barHeight = (value) => (max.value ? (value / max.value) * height : 0);
const isLabelled = (index) => (props.points.length - 1 - index) % 7 === 0;

// Centred over its bar, except near the ends where it anchors to the bar's outer edge so it stays inside the card.
const tooltipPosition = computed(() => {
    const count = props.points.length;
    const index = activeIndex.value ?? 0;

    if (index < 3) {
        return { style: { left: `${(index / count) * 100}%` }, class: '' };
    }

    if (index >= count - 3) {
        return { style: { right: `${((count - 1 - index) / count) * 100}%` }, class: '' };
    }

    return { style: { left: `${((index + 0.5) / count) * 100}%` }, class: '-translate-x-1/2' };
});
</script>

<template>
    <div>
        <p v-if="max === 0" class="py-12 text-center text-sm text-brand-500">No payments received in the last 30 days.</p>
        <div v-else class="flex gap-3">
            <div class="flex h-48 w-16 shrink-0 flex-col justify-between text-right text-[11px] tabular-nums text-brand-400" aria-hidden="true">
                <span class="-translate-y-1/2">{{ format(max) }}</span>
                <span>{{ format(Math.round(max / 2)) }}</span>
                <span class="translate-y-1/2">{{ format(0) }}</span>
            </div>
            <div class="min-w-0 flex-1">
                <div class="relative">
                    <svg
                        :viewBox="`0 0 ${width} ${height}`"
                        preserveAspectRatio="none"
                        class="block h-48 w-full overflow-visible"
                        role="img"
                        :aria-label="`Received per day over the last ${points.length} days, ${format(total)} in total`"
                    >
                        <line v-for="y in [0, height / 2, height]" :key="y" x1="0" :x2="width" :y1="y" :y2="y" class="stroke-brand-200" stroke-dasharray="2 3" vector-effect="non-scaling-stroke" />
                        <rect
                            v-for="(point, index) in points"
                            :key="point.date"
                            :x="index * slotWidth + slotWidth * 0.15"
                            :width="slotWidth * 0.7"
                            :y="height - barHeight(point.value)"
                            :height="barHeight(point.value)"
                            rx="1"
                            :class="activeIndex === index ? 'fill-ink' : 'fill-brand-600'"
                            class="transition-colors duration-150 motion-reduce:transition-none"
                        />
                        <rect
                            v-for="(point, index) in points"
                            :key="`hit-${point.date}`"
                            :x="index * slotWidth"
                            :width="slotWidth"
                            y="0"
                            :height="height"
                            fill="transparent"
                            @mouseenter="activeIndex = index"
                            @mouseleave="activeIndex = null"
                        />
                    </svg>
                    <div
                        v-if="activeIndex !== null"
                        :class="tooltipPosition.class"
                        class="pointer-events-none absolute top-0 z-10 -translate-y-full whitespace-nowrap rounded-lg bg-ink px-2.5 py-1.5 text-xs text-brand-50 shadow"
                        :style="tooltipPosition.style"
                    >
                        <span class="block text-brand-300">{{ points[activeIndex].label }}</span>
                        <span class="font-medium tabular-nums">{{ format(points[activeIndex].value) }}</span>
                    </div>
                </div>
                <div class="mt-2 flex h-4 text-[11px] text-brand-500" aria-hidden="true">
                    <span v-for="(point, index) in points" :key="point.date" class="relative flex-1">
                        <span v-if="isLabelled(index)" class="absolute left-1/2 -translate-x-1/2 whitespace-nowrap">{{ point.label }}</span>
                    </span>
                </div>
            </div>
        </div>
        <table class="sr-only">
            <caption>Received per day</caption>
            <thead>
                <tr>
                    <th scope="col">Day</th>
                    <th scope="col">Received</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="point in points" :key="point.date">
                    <td>{{ point.label }}</td>
                    <td>{{ format(point.value) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
