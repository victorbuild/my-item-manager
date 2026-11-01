<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import axios from '../axios'

const props = defineProps({ product: { type: Object, required: true } })
const details = ref(props.product)
const detailError = ref('')
let controller
let active = true
const fields = [{ key: 'brand', label: '品牌' }, { key: 'model', label: '型號' }, { key: 'spec', label: '規格' }, { key: 'barcode', label: '條碼' }]
const ownedCount = computed(() => {
    const stats = details.value.stats
    if (stats && ['pre_arrival', 'unused', 'in_use'].every(key => Number.isInteger(stats[key]) && stats[key] >= 0)) {
        return stats.pre_arrival + stats.unused + stats.in_use
    }
    return details.value.owned_items_count ?? null
})
const loadDetails = async () => {
    controller?.abort()
    controller = undefined
    details.value = { ...props.product }
    detailError.value = ''
    if (!props.product.short_id) return
    const request = new AbortController()
    controller = request
    try {
        const response = await axios.get(`/api/products/${encodeURIComponent(props.product.short_id)}`, { signal: request.signal })
        if (active && controller === request) details.value = { ...props.product, ...response.data.data }
    } catch (error) {
        if (active && controller === request && error.code !== 'ERR_CANCELED') detailError.value = '無法載入完整產品資料。'
    }
}
watch(() => props.product, loadDetails, { immediate: true })
onBeforeUnmount(() => { active = false; controller?.abort() })
</script>

<template>
    <section class="product-summary" aria-label="已選產品資訊">
        <p class="product-name">{{ details.name }}</p>
        <dl>
            <template v-for="field in fields" :key="field.key">
                <div v-if="details[field.key]"><dt>{{ field.label }}</dt><dd>{{ details[field.key] }}</dd></div>
            </template>
            <div v-if="details.category?.name"><dt>分類</dt><dd>{{ details.category.name }}</dd></div>
            <div v-if="ownedCount != null"><dt>目前持有</dt><dd>{{ ownedCount }} 件</dd></div>
        </dl>
        <p v-if="detailError" class="detail-error" role="alert">{{ detailError }} <button type="button" @click="loadDetails">重試</button></p>
    </section>
</template>

<style scoped>
.product-summary { min-width: 0; padding: 16px; border: 1px solid var(--border-subtle); border-radius: var(--radius-control); background: var(--surface); color: var(--text-muted); }
.product-summary .product-name { margin: 0 0 12px; font-size: 16px; line-height: 1.5; font-weight: 600; overflow-wrap: anywhere; }
dl { display: grid; gap: 8px; margin: 0; font-size: 14px; line-height: 1.5; }
dl > div { display: grid; grid-template-columns: 72px minmax(0, 1fr); gap: 8px; }
dt { font-weight: 400; }
dd { margin: 0; overflow-wrap: anywhere; }
.detail-error { margin: 8px 0 0; color: var(--danger); font-size: 14px; line-height: 1.5; }
.detail-error button { min-height: 44px; padding: 8px 0; border: 0; background: transparent; color: var(--brand-ink); font-size: 14px; text-decoration: underline; cursor: pointer; }
.detail-error button:focus-visible { outline: 2px solid var(--brand-ink); outline-offset: 2px; }
</style>
