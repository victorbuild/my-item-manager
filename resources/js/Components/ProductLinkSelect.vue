<script setup>
import { computed, onBeforeUnmount, onMounted, ref, useId } from 'vue'
import Multiselect from 'vue-multiselect'
import 'vue-multiselect/dist/vue-multiselect.css'
import AppButton from './AppButton.vue'
import ProductCreateModal from './ProductCreateModal.vue'
import axios from '../axios'

const props = defineProps({
    modelValue: { type: Object, default: null },
    id: { type: String, required: true },
    errorMessage: { type: String, default: '' },
})
const emit = defineEmits(['update:modelValue', 'draft-change', 'creation-pending'])
const errorId = `product-link-error-${useId()}`
const showCreate = ref(false)
const createName = ref('')
const openCreate = name => {
    createName.value = name.trim()
    showCreate.value = true
    emit('creation-pending', true)
    emit('draft-change', { name: createName.value })
}
const setCreateOpen = open => {
    showCreate.value = open
    emit('creation-pending', open)
    if (!open) emit('draft-change', null)
}
const onCreated = product => {
    results.value = [product, ...results.value.filter(candidate => candidate.id !== product.id)]
    emit('update:modelValue', product)
    setCreateOpen(false)
}
const selection = computed({ get: () => props.modelValue, set: value => emit('update:modelValue', value) })
const results = ref([])
const loading = ref(false)
const searchError = ref('')
const query = ref('')
const options = computed(() => !query.value.trim() && props.modelValue && !results.value.some(product => product.id === props.modelValue.id)
    ? [props.modelValue, ...results.value] : results.value)
let active = true
let controller
let timer
const searchProducts = async search => {
    controller?.abort()
    const request = new AbortController()
    controller = request
    loading.value = true
    searchError.value = ''
    try {
        const response = await axios.get('/api/products', { params: { q: search, per_page: 20 }, signal: request.signal })
        if (active && controller === request) results.value = response.data.data || []
    } catch (error) {
        if (active && controller === request && error.code !== 'ERR_CANCELED') searchError.value = '產品載入失敗，請重試。'
    } finally { if (active && controller === request) loading.value = false }
}
const onSearch = value => {
    query.value = value
    clearTimeout(timer)
    controller?.abort()
    controller = undefined
    loading.value = true
    searchError.value = ''
    results.value = []
    timer = setTimeout(() => searchProducts(value), 250)
}
onMounted(() => searchProducts(''))
onBeforeUnmount(() => { active = false; clearTimeout(timer); controller?.abort() })
</script>

<template>
    <div class="product-link-control">
        <Multiselect :id="id" class="product-link-picker" v-model="selection" :options="options" :searchable="true" :internal-search="false"
            :loading="loading" :preserve-search="true" :allow-empty="false" :close-on-select="true" :custom-label="product => product.name" track-by="id"
            placeholder="請輸入或選擇產品" select-label="按 Enter 選取" selected-label="已選取" deselect-label="已選取"
            :aria-invalid="Boolean(errorMessage)" :aria-describedby="errorMessage ? errorId : undefined" @search-change="onSearch">
            <template #noOptions>{{ loading ? '載入中…' : '目前沒有可選擇的產品。' }}</template>
            <template #noResult>找不到符合的產品，請嘗試其他關鍵字。</template>
        </Multiselect>
        <p v-if="errorMessage" :id="errorId" class="error-message">{{ errorMessage }}</p>
        <div v-if="searchError" class="search-error" role="alert"><p>{{ searchError }}</p><AppButton variant="secondary" @click="searchProducts(query)">重試</AppButton></div>
        <div class="product-actions">
            <button type="button" class="unlink-product" @click="openCreate(query)">新增產品</button>
            <button v-if="modelValue" type="button" class="unlink-product" @click="selection = null">解除關聯</button>
        </div>
        <ProductCreateModal :model-value="showCreate" :initial-name="createName" @update:model-value="setCreateOpen" @created="onCreated" @draft-change="value => emit('draft-change', value)" />
    </div>
</template>

<style scoped>
.product-link-control { min-width: 0; }
.product-link-control :deep(.product-link-picker.multiselect) { display: block; min-width: 0; min-height: 44px; margin: 0; padding: 0; border: 0; background: transparent; color: var(--text); box-shadow: none; }
.product-link-control :deep(.multiselect__tags) { min-height: 44px; padding: 10px 40px 0 12px; border-color: var(--border-control); border-radius: var(--radius-control); background: var(--surface); font-size: 16px; }
.product-link-control :deep(.multiselect__input), .product-link-control :deep(.multiselect__single) { min-width: 0; margin-bottom: 10px; padding: 0; background: var(--surface); color: var(--text); font-size: 16px; line-height: 24px; }
.product-link-control :deep(.multiselect__single) { white-space: normal; overflow-wrap: anywhere; }
.product-link-control :deep(.multiselect__input) { min-height: 24px; border: 0; border-radius: 0; }
.product-link-control :deep(.multiselect__placeholder) { margin-bottom: 10px; padding: 0; color: var(--text-muted); font-size: 16px; line-height: 24px; }
.product-link-control :deep(.multiselect__content-wrapper) { border-color: var(--border-control); background: var(--surface); color: var(--text); }
.product-link-control :deep(.multiselect__option) { min-height: 44px; white-space: normal; overflow-wrap: anywhere; line-height: 1.5; }
.product-link-control :deep(.multiselect__option--highlight), .product-link-control :deep(.multiselect__option--highlight::after) { background: var(--brand); color: var(--on-brand); }
.product-link-control :deep(.multiselect__option--selected), .product-link-control :deep(.multiselect__option--selected::after) { background: var(--brand-soft); color: var(--brand-ink); }
.product-link-control :deep(.multiselect__select::before) { border-top-color: var(--text-muted); }
.product-link-control :deep(.multiselect__spinner) { background: var(--surface); }
.product-link-control :deep(.multiselect__spinner::before), .product-link-control :deep(.multiselect__spinner::after) { border-top-color: var(--brand); }
.product-link-control :deep(.product-link-picker:focus-within) { outline: 2px solid var(--brand-ink); outline-offset: 2px; border-radius: var(--radius-control); }
.product-link-control :deep(.product-link-picker[aria-invalid=true] .multiselect__tags) { border-color: var(--danger); }
.error-message, .search-error { margin: 8px 0 0; color: var(--danger); font-size: 14px; line-height: 1.5; }
.product-actions { display: flex; flex-wrap: wrap; gap: 8px 24px; }
.unlink-product { min-height: 44px; margin-top: 8px; padding: 8px 0; border: 0; background: transparent; color: var(--brand-ink); font-size: 14px; text-decoration: underline; text-underline-offset: 3px; cursor: pointer; }
.unlink-product:focus-visible { outline: 2px solid var(--brand-ink); outline-offset: 2px; }
</style>
