<script setup>
import { ref, watch } from 'vue'
import AppIcon from './AppIcon.vue'
const props = defineProps({ src: { type: String, default: '' } })
const failed = ref(false)
watch(() => props.src, () => { failed.value = false })
</script>

<template>
    <span class="item-thumbnail">
        <img v-if="src && !failed" :src="src" alt="" loading="lazy" width="64" height="64" @error="failed = true" />
        <span v-else class="item-thumbnail-empty" role="img" aria-label="無圖片"><AppIcon name="package" :size="24" /></span>
    </span>
</template>

<style scoped>
.item-thumbnail { width: 64px; height: 64px; flex-shrink: 0; border-radius: var(--radius-control); background: var(--disabled); color: var(--text-muted); display: flex; align-items: center; justify-content: center; overflow: hidden; }
.item-thumbnail img { width: 100%; height: 100%; object-fit: cover; }
.item-thumbnail-empty { display: flex; flex-direction: column; align-items: center; gap: 2px; font-size: 12px; line-height: 1.5; }
</style>
