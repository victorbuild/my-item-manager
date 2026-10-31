<script setup>
import '../../../css/aligned-pages.css'
import '../../../css/management-pages.css'
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from '../../axios'
import ProductForm from '../../Components/ProductForm.vue'

const route = useRoute()
const router = useRouter()
const isEdit = computed(() => Boolean(route.params.id))
const editor = ref(null)
const busy = computed(() => Boolean(editor.value?.isCreating || editor.value?.isCreatingCategory))
const preventBusyReturn = event => { if (busy.value) event.preventDefault() }
const initialProduct = ref(null)
const loading = ref(isEdit.value)
const loadError = ref('')
let controller
const loadProduct = async () => {
    controller?.abort()
    initialProduct.value = null
    loadError.value = ''
    if (!isEdit.value) { loading.value = false; return }
    const request = new AbortController()
    controller = request
    loading.value = true
    loadError.value = ''
    try {
        const response = await axios.get(`/api/products/${route.params.id}`, { signal: request.signal })
        if (!request.signal.aborted) initialProduct.value = response.data.data
    } catch {
        if (!request.signal.aborted) loadError.value = '載入產品資料失敗，請重試。'
    } finally {
        if (!request.signal.aborted) loading.value = false
    }
}
const cancel = () => router.push('/products')
const saved = () => router.push(isEdit.value ? `/products/${route.params.id}` : '/products')
watch(() => route.params.id, loadProduct, { immediate: true })
onBeforeUnmount(() => controller?.abort())
</script>

<template>
    <div class="aligned-page management-page min-h-screen space-y-6">
        <div class="product-heading">
            <h1>{{ isEdit ? '編輯產品' : '建立產品' }}</h1>
            <router-link to="/products" class="product-secondary" :aria-disabled="busy" :tabindex="busy ? -1 : undefined" @click="preventBusyReturn">返回列表</router-link>
        </div>
        <p v-if="loading" role="status">載入中...</p>
        <p v-else-if="loadError" role="alert">{{ loadError }} <button type="button" class="product-secondary" @click="loadProduct">重試</button></p>
        <section v-else class="product-editor-panel">
            <ProductForm ref="editor" :key="String(route.params.id || 'create')" :mode="isEdit ? 'edit' : 'create'" :product-id="String(route.params.id || '')" :initial-product="initialProduct" @cancel="cancel" @saved="saved" />
        </section>
    </div>
</template>

<style scoped>
.product-editor-panel { padding: 24px; border: 1px solid var(--border-subtle); border-radius: var(--radius-panel); background: var(--surface); }
@media (max-width: 639px) { .product-editor-panel { padding: 16px; } }
</style>
