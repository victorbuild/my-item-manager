<script setup>
import { ChevronLeft, ChevronRight } from '@lucide/vue'
import AppButton from './AppButton.vue'

defineProps({
    currentPage: { type: Number, required: true },
    lastPage: { type: Number, required: true },
    disabled: { type: Boolean, default: false },
    label: { type: String, default: '分頁' },
})
const emit = defineEmits(['change'])
</script>

<template>
    <nav class="app-pagination" :aria-label="label">
        <AppButton variant="secondary" :disabled="disabled || currentPage <= 1" aria-label="上一頁" title="上一頁" @click="emit('change', currentPage - 1)">
            <ChevronLeft :size="20" :stroke-width="1.75" aria-hidden="true" />
        </AppButton>
        <span :aria-label="`第 ${currentPage} 頁，共 ${lastPage} 頁`">{{ currentPage }} / {{ lastPage }}</span>
        <AppButton variant="secondary" :disabled="disabled || currentPage >= lastPage" aria-label="下一頁" title="下一頁" @click="emit('change', currentPage + 1)">
            <ChevronRight :size="20" :stroke-width="1.75" aria-hidden="true" />
        </AppButton>
    </nav>
</template>

<style scoped>
.app-pagination { display: flex; flex-wrap: wrap; justify-content: center; align-items: center; gap: 12px; margin-top: 24px; }
.app-pagination :deep(.app-button) { width: 44px; height: 44px; padding: 0; }
.app-pagination span { font-size: 14px; color: var(--text-muted); font-variant-numeric: tabular-nums; }
</style>
