<script setup>
import { computed, nextTick, onBeforeUnmount, ref, useId, watch } from 'vue'
import { Html5Qrcode } from 'html5-qrcode'
import axios from '../axios'
import AppModal from './AppModal.vue'
import AppButton from './AppButton.vue'
import ItemNameInput from './ItemNameInput.vue'
import SingleSelect from './SingleSelect.vue'
import { preventImplicitSubmit } from '../utils/formKeyboard'

const props = defineProps({ modelValue: Boolean, initialName: { type: String, default: '' } })
const emit = defineEmits(['update:modelValue', 'created', 'draft-change'])
const inputPrefix = `create-product-${useId().replace(/[^a-z0-9-]/gi, '')}`
const blankProduct = () => ({ name: '', brand: '', category_id: '', model: '', spec: '', barcode: '' })
const product = ref(blankProduct())
const categories = ref([])
const categoryError = ref('')
const categoryLoading = ref(false)
const isCreating = ref(false)
const categoryCreateOpen = ref(false)
const categoryName = ref('')
const isCreatingCategory = ref(false)
const categoryCreateError = ref('')
const formErrors = ref({})
const submitError = ref('')
const scannerOpen = ref(false)
const scannerError = ref('')
const categoryOptions = computed(() => categories.value.map(category => ({ value: String(category.id), label: category.name })))
const canCreate = computed(() => product.value.name.trim().length > 0 && product.value.name.length <= 255 && !isCreating.value && !scannerOpen.value && !categoryCreateOpen.value && !isCreatingCategory.value)
let active = true
let categoryController
let scanner
let scanGeneration = 0
const loadCategories = async () => {
    categoryController?.abort()
    const controller = new AbortController()
    categoryController = controller
    categoryLoading.value = true
    categoryError.value = ''
    try {
        const response = await axios.get('/api/categories', { signal: controller.signal })
        if (active && categoryController === controller) categories.value = response.data.data || []
    } catch (error) {
        if (active && categoryController === controller && error.code !== 'ERR_CANCELED') categoryError.value = '分類載入失敗，請重試。'
    } finally { if (active && categoryController === controller) categoryLoading.value = false }
}
const createCategory = async () => {
    const name = categoryName.value.trim()
    if (!name || name.length > 255 || isCreatingCategory.value) return
    isCreatingCategory.value = true
    categoryCreateError.value = ''
    try {
        const response = await axios.post('/api/categories', { name })
        if (!active) return
        const category = response.data.items[0]
        categories.value = [...categories.value.filter(item => item.id !== category.id), category]
        product.value.category_id = String(category.id)
        categoryCreateOpen.value = false
        categoryName.value = ''
    } catch (error) {
        if (active) categoryCreateError.value = error.response?.data?.errors?.name?.[0] || error.response?.data?.message || '分類建立失敗，請重試。'
    } finally { if (active) isCreatingCategory.value = false }
}
const stopScanner = async () => {
    scanGeneration++
    const current = scanner
    scanner = null
    if (current) {
        try { if (current.isScanning) await current.stop(); current.clear() } catch { /* 相機尚未啟動或已關閉時不阻擋取消。 */ }
    }
    scannerOpen.value = false
}
const startScanner = async () => {
    if (scannerOpen.value) return
    scannerError.value = ''
    scannerOpen.value = true
    const generation = ++scanGeneration
    await nextTick()
    if (!active || generation !== scanGeneration) return
    const current = new Html5Qrcode(`${inputPrefix}-scanner`)
    scanner = current
    try {
        await current.start({ facingMode: 'environment' }, { fps: 10, qrbox: { width: 240, height: 100 } }, value => {
            if (!active || generation !== scanGeneration) return
            product.value.barcode = value
            stopScanner()
        }, () => {})
        if (!active || generation !== scanGeneration) {
            try { if (current.isScanning) await current.stop(); current.clear() } catch {}
        }
    } catch {
        if (active && generation === scanGeneration) {
            scannerError.value = '無法啟動相機，請確認權限，或直接輸入條碼。'
            await stopScanner()
        }
    }
}
const close = async () => {
    if (isCreating.value || isCreatingCategory.value) return
    if (scannerOpen.value) await stopScanner()
    emit('update:modelValue', false)
    emit('draft-change', null)
}
const createProduct = async () => {
    if (!canCreate.value) return
    isCreating.value = true
    formErrors.value = {}
    submitError.value = ''
    try {
        const response = await axios.post('/api/products', {
            ...product.value,
            name: product.value.name.trim(),
            category_id: product.value.category_id ? Number(product.value.category_id) : null,
        })
        if (!active) return
        emit('created', response.data.data)
        emit('update:modelValue', false)
        emit('draft-change', null)
    } catch (error) {
        if (!active) return
        if (error.response?.status === 422) formErrors.value = Object.fromEntries(Object.entries(error.response.data.errors || {}).map(([key, value]) => [key, Array.isArray(value) ? value[0] : value]))
        submitError.value = error.response?.data?.message || '建立失敗，輸入內容已保留，請重試。'
    } finally { if (active) isCreating.value = false }
}
watch(() => props.modelValue, open => {
    if (!open) { emit('draft-change', null); return }
    product.value = { ...blankProduct(), name: props.initialName }
    formErrors.value = {}
    submitError.value = ''
    scannerError.value = ''
    categoryCreateOpen.value = false
    categoryName.value = ''
    categoryCreateError.value = ''
    loadCategories()
}, { immediate: true })
watch(product, value => { if (props.modelValue) emit('draft-change', { ...value }) }, { deep: true, flush: 'sync' })
onBeforeUnmount(() => { active = false; categoryController?.abort(); stopScanner() })
</script>

<template>
    <AppModal :model-value="modelValue" title="建立新產品" :busy="isCreating || isCreatingCategory" @update:model-value="close">
        <div class="product-form" @keydown="preventImplicitSubmit">
            <div>
                <label :for="`${inputPrefix}-name`">產品名稱 <span class="required">*</span></label>
                <ItemNameInput :id="`${inputPrefix}-name`" v-model="product.name" aria-required="true" :aria-invalid="Boolean(formErrors.name)" :aria-describedby="formErrors.name ? `${inputPrefix}-name-error` : undefined" />
                <p v-if="formErrors.name" :id="`${inputPrefix}-name-error`" class="field-error">{{ formErrors.name }}</p>
            </div>
            <div><label :for="`${inputPrefix}-brand`">品牌</label><input :id="`${inputPrefix}-brand`" v-model="product.brand" type="text" maxlength="255" :aria-invalid="Boolean(formErrors.brand)" /><p v-if="formErrors.brand" class="field-error">{{ formErrors.brand }}</p></div>
            <div>
                <label :id="`${inputPrefix}-category-label`" :for="`${inputPrefix}-category`">分類</label>
                <SingleSelect :id="`${inputPrefix}-category`" v-model="product.category_id" :options="categoryOptions" label="分類" :placeholder="categoryLoading ? '分類載入中…' : '選擇分類'" />
                <div class="category-actions">
                    <button type="button" class="text-action" @click="categoryCreateOpen = true">新增分類</button>
                    <button v-if="product.category_id" type="button" class="text-action" @click="product.category_id = ''">清除分類</button>
                </div>
                <div v-if="categoryCreateOpen" class="category-create">
                    <label :for="`${inputPrefix}-category-name`">分類名稱</label>
                    <input :id="`${inputPrefix}-category-name`" v-model="categoryName" type="text" maxlength="255" />
                    <p v-if="categoryCreateError" class="field-error" role="alert">{{ categoryCreateError }}</p>
                    <div class="category-actions"><AppButton variant="secondary" :disabled="isCreatingCategory" @click="categoryCreateOpen = false">取消</AppButton><AppButton :disabled="!categoryName.trim() || isCreatingCategory" @click="createCategory">{{ isCreatingCategory ? '建立中…' : '建立分類' }}</AppButton></div>
                </div>
                <p v-if="formErrors.category_id" class="field-error">{{ formErrors.category_id }}</p>
                <div v-if="categoryError" class="field-error" role="alert">{{ categoryError }} <button type="button" class="text-action" @click="loadCategories">重試</button></div>
            </div>
            <div><label :for="`${inputPrefix}-model`">型號</label><input :id="`${inputPrefix}-model`" v-model="product.model" type="text" maxlength="255" :aria-invalid="Boolean(formErrors.model)" /><p v-if="formErrors.model" class="field-error">{{ formErrors.model }}</p></div>
            <div><label :for="`${inputPrefix}-spec`">規格</label><input :id="`${inputPrefix}-spec`" v-model="product.spec" type="text" maxlength="255" :aria-invalid="Boolean(formErrors.spec)" /><p v-if="formErrors.spec" class="field-error">{{ formErrors.spec }}</p></div>
            <div>
                <label :for="`${inputPrefix}-barcode`">條碼</label>
                <div class="barcode-input"><input :id="`${inputPrefix}-barcode`" v-model="product.barcode" type="text" maxlength="255" :aria-invalid="Boolean(formErrors.barcode)" /><button type="button" class="text-action" @click="startScanner">掃描</button></div>
                <p v-if="formErrors.barcode" class="field-error">{{ formErrors.barcode }}</p>
                <p v-if="scannerError" class="field-error" role="alert">{{ scannerError }}</p>
            </div>
            <p v-if="submitError" class="field-error" role="alert">{{ submitError }}</p>
            <div v-if="scannerOpen" class="scanner-panel"><div :id="`${inputPrefix}-scanner`"></div><AppButton variant="secondary" @click="stopScanner">關閉掃描</AppButton></div>
        </div>
        <template #footer><AppButton variant="secondary" :disabled="isCreating || isCreatingCategory" @click="close">取消</AppButton><AppButton :disabled="!canCreate" :aria-busy="isCreating" @click="createProduct">{{ isCreating ? '建立中…' : '建立產品' }}</AppButton></template>
    </AppModal>
</template>

<style scoped>
.product-form { display: grid; gap: 24px; color: var(--text); }
label { display: block; margin-bottom: 8px; font-size: 14px; font-weight: 500; line-height: 1.5; }
input { width: 100%; min-width: 0; min-height: 44px; padding: 10px 12px; border: 1px solid var(--border-control); border-radius: var(--radius-control); background: var(--surface); color: var(--text); font-size: 16px; line-height: 1.5; }
input[aria-invalid=true] { border-color: var(--danger); }
.required, .field-error { color: var(--danger); }
.field-error { margin: 4px 0 0; font-size: 14px; line-height: 1.5; }
.category-actions { display: flex; flex-wrap: wrap; gap: 8px 16px; margin-top: 8px; }
.category-create { padding: 16px; border-radius: var(--radius-control); background: var(--canvas); }
.barcode-input { display: flex; align-items: center; gap: 8px; }
.text-action { flex-shrink: 0; min-height: 44px; padding: 8px 0; border: 0; background: transparent; color: var(--brand-ink); text-decoration: underline; font-size: 14px; cursor: pointer; }
input:focus-visible, button:focus-visible { outline: 2px solid var(--brand-ink); outline-offset: 2px; }
.scanner-panel { display: grid; gap: 16px; }
</style>
