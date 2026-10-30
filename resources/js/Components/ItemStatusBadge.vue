<script setup>
import { computed } from 'vue'

const props = defineProps({ status: { type: String, default: '' } })
const statuses = {
    pre_arrival: { label: '未到貨', tone: 'info' },
    unused: { label: '未使用', tone: 'neutral' },
    in_use: { label: '使用中', tone: 'success' },
    unused_discarded: { label: '未使用就棄用', tone: 'neutral' },
    used_discarded: { label: '使用後棄用', tone: 'neutral' },
}
const badge = computed(() => statuses[props.status] || { label: '未設定狀態', tone: 'neutral' })
</script>

<template>
    <span :class="['item-status-badge', badge.tone]">{{ badge.label }}</span>
</template>

<style scoped>
.item-status-badge { display: inline-block; flex-shrink: 0; border-radius: 100px; padding: 2px 8px; font-size: 14px; line-height: 1.5; }
.neutral { background: var(--canvas); color: var(--text-muted); }
.info { background: var(--info-soft); color: var(--info); }
.success { background: var(--success-soft); color: var(--success); }
</style>
