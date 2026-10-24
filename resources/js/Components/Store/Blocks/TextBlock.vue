<script setup>
defineProps({
    data: { type: Object, required: true },
});
</script>

<template>
    <div class="mx-auto max-w-3xl px-4">
        <!-- html is server-rendered Markdown with raw HTML stripped -->
        <div class="content-prose" v-html="data.html" />
    </div>
</template>
