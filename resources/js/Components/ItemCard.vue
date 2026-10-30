<script setup>
import { computed, ref, watch } from 'vue'
import AppIcon from './AppIcon.vue'
import ItemStatusBadge from './ItemStatusBadge.vue'
import { getTodayDate } from '../utils/itemDates'

const props = defineProps({
    item: { type: Object, required: true },
})
const emit = defineEmits(['open'])
const imageFailed = ref(false)
watch(() => props.item.main_image?.thumb_url, () => { imageFailed.value = false })
const expiration = computed(() => {
    const date = props.item.expiration_date
    if (!date) return { label: '未設定期限', tone: '' }
    const today = getTodayDate()
    if (date < today) return { label: `${date} · 已過期`, tone: 'danger' }
    if (date === today) return { label: `${date} · 今天到期`, tone: 'warning' }
    return { label: date, tone: '' }
})
</script>

<template>
    <article class="item-row">
        <span class="thumbnail">
            <img v-if="item.main_image?.thumb_url && !imageFailed" :src="item.main_image.thumb_url" alt="" loading="lazy" width="56" height="56" @error="imageFailed = true" />
            <AppIcon v-else name="package" :size="24" />
        </span>
        <div class="heading-content">
            <router-link class="item-name" :to="`/items/${item.short_id}`">{{ item.name }}</router-link>
            <div class="item-tags">
                <ItemStatusBadge :status="item.status" />
                <span v-if="item.expiration_date" :class="['status-badge', 'expiry-tag', expiration.tone]">有效期限 {{ expiration.label }}</span>
            </div>
        </div>
        <button type="button" class="view-label" aria-haspopup="dialog" :aria-label="`快速查看 ${item.name} 的價格與日期`" @click="emit('open', item)">查看</button>
    </article>
</template>

<style scoped>
.item-row { width: 100%; min-width: 0; min-height: 88px; padding: 16px; display: flex; align-items: center; gap: 12px; text-align: left; border: 1px solid var(--border-subtle); border-radius: var(--radius-panel); background: var(--surface); color: var(--text); }
.view-label:focus-visible, .item-name:focus-visible { outline: 2px solid var(--brand-ink); outline-offset: 2px; }
.thumbnail { width: 56px; height: 56px; flex-shrink: 0; border-radius: var(--radius-control); background: var(--canvas); color: var(--text-muted); display: flex; align-items: center; justify-content: center; overflow: hidden; }
.thumbnail img { width: 100%; height: 100%; object-fit: cover; }
.heading-content { min-width: 0; flex: 1; }
.item-name { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; overflow-wrap: anywhere; font-size: 16px; font-weight: 600; line-height: 1.5; color: var(--text); text-decoration: none; }
.item-name:hover { color: var(--brand-ink); text-decoration: underline; }
.item-tags { display: flex; flex-wrap: wrap; gap: 4px 8px; margin-top: 4px; }
.status-badge { display: inline-block; border-radius: 100px; padding: 2px 8px; font-size: 14px; line-height: 1.5; }
.expiry-tag { background: var(--canvas); color: var(--text-muted); overflow-wrap: anywhere; }
.danger { background: var(--danger-soft); color: var(--danger); }
.warning { background: var(--warning-soft); color: var(--warning); }
.view-label { display: inline-flex; align-items: center; justify-content: center; min-width: 44px; min-height: 44px; padding: 8px; color: var(--brand-ink); background: transparent; border: 0; border-radius: var(--radius-control); font-size: 14px; flex-shrink: 0; cursor: pointer; text-decoration: underline; text-underline-offset: 3px; }
.view-label:hover { background: var(--brand-soft); }
</style>
