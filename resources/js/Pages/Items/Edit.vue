<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { onBeforeRouteLeave, onBeforeRouteUpdate, useRoute, useRouter } from 'vue-router'
import dayjs from 'dayjs'
import axios from '../../axios'
import AppButton from '../../Components/AppButton.vue'
import AppIcon from '../../Components/AppIcon.vue'
import DateInput from '../../Components/DateInput.vue'
import ItemPhotos from '../../Components/ItemPhotos.vue'
import ItemNameInput from '../../Components/ItemNameInput.vue'
import ProductLinkSelect from '../../Components/ProductLinkSelect.vue'
import ProductSummary from '../../Components/ProductSummary.vue'
import { usePurchaseArrivalLink } from '../../composables/usePurchaseArrivalLink'
import { createNumericInputHandlers } from '../../utils/numericInput'
import { createUnsavedChangesGuard } from '../../utils/unsavedChanges'
import { preventImplicitSubmit } from '../../utils/formKeyboard'
import { formatDateText, parseDateText } from '../../utils/dateTextInput'
import { getDateMin, getDateMax } from '../../utils/itemDates'
import { imageUuidKey } from '../../utils/itemImageSelection'
import { createItemEditForm, createItemEditPayload, getItemEditErrors, getItemEditPrice } from '../../utils/itemEditState'

const route = useRoute()
const router = useRouter()
const itemForm = ref(null)
const item = ref(null)
const selectedProduct = ref(null)
const productCreationPending = ref(false)
const productDraft = ref(null)
const form = ref(createItemEditForm({}))
const photos = ref([])
const originalPhotos = ref([])
const isLoading = ref(true)
const loaded = ref(false)
const loadError = ref('')
const isSubmitting = ref(false)
const submitError = ref('')
const saveNotice = ref('')
const formErrors = ref({})
const touched = ref({})
const dateDrafts = ref({})
const summaryImageFailed = ref(false)
let active = true
let loadController
let saveGeneration = 0
const { receivedWithPurchase, resetArrivalLink } = usePurchaseArrivalLink(form)
const purchaseDateMax = computed(() => getDateMax(receivedWithPurchase.value ? { ...form.value, received_at: '' } : form.value, 'purchased_at'))
const validationErrors = computed(() => getItemEditErrors(form.value, dateDrafts.value, purchaseDateMax.value))
const priceSummary = computed(() => getItemEditPrice(form.value.price))
const amountInput = createNumericInputHandlers(value => { form.value.price = value })
const setDateDraft = (field, value) => {
    if (value == null) delete dateDrafts.value[field]
    else dateDrafts.value[field] = value
}
const fieldError = field => formErrors.value[field] || (touched.value[field] ? validationErrors.value[field] : '') || ''
const initialDates = [{ key: 'purchased_at', label: '購買日期', required: true }, { key: 'received_at', label: '到貨日期' }, { key: 'expiration_date', label: '有效期限' }]
const otherDates = [{ key: 'used_at', label: '開始使用日期' }, { key: 'discarded_at', label: '報廢日期' }]
const dateMin = key => key === 'expiration_date' ? undefined : getDateMin(form.value, key)
const dateMax = key => key === 'expiration_date' ? '9999-12-31' : key === 'purchased_at' ? purchaseDateMax.value : getDateMax(form.value, key)
const removedPhotos = computed(() => {
    const current = new Set(photos.value.map(photo => imageUuidKey(photo.uuid)).filter(Boolean))
    return originalPhotos.value.filter(photo => !current.has(imageUuidKey(photo.uuid)))
})
const restorePhoto = photo => {
    if (photos.value.length >= 9) return
    if (!photos.value.some(current => imageUuidKey(current.uuid) === imageUuidKey(photo.uuid))) photos.value = [...photos.value, { ...photo }]
}
const photosReady = computed(() => photos.value.every(photo => photo.status === 'done' && photo.uuid))
const formatAmount = value => value == null ? '未填寫' : value.toLocaleString('zh-TW', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

const showManufactureDateModal = ref(false)
const manufactureDate = ref('')
const expirationValue = ref('')
const expirationUnit = ref('years')
const expirationInput = createNumericInputHandlers(value => { expirationValue.value = value })
const calculatedExpirationDate = computed(() => {
    const date = parseDateText(manufactureDate.value)
    const count = Number(expirationValue.value)
    if (!date || dateDrafts.value.manufactureDate != null || !Number.isSafeInteger(count) || count < 1) return ''
    const result = dayjs(date).add(count, expirationUnit.value)
    return result.isValid() && result.year() <= 9999 ? result.format('YYYY-MM-DD') : ''
})
const applyCalculatedDate = () => {
    if (!calculatedExpirationDate.value) return
    form.value.expiration_date = calculatedExpirationDate.value
    delete dateDrafts.value.expiration_date
    delete dateDrafts.value.manufactureDate
    showManufactureDateModal.value = false
    manufactureDate.value = ''
    expirationValue.value = ''
    expirationUnit.value = 'years'
}
const takeDraftSnapshot = () => {
    if (!loaded.value) return { loading: true }
    const pendingDates = Object.fromEntries(Object.entries(dateDrafts.value).filter(([field, value]) => {
        const current = field === 'manufactureDate' ? manufactureDate.value : form.value[field]
        const parsed = parseDateText(value)
        return (parsed ? formatDateText(parsed) : value) !== (current || '')
    }))
    return {
        form: { ...form.value }, productDraft: productDraft.value, productId: selectedProduct.value?.id ?? null, photos: photos.value.map(photo => photo.uuid || `pending:${photo.id}`), pendingDates,
        receivedWithPurchase: receivedWithPurchase.value, manufactureDate: manufactureDate.value,
        expirationValue: expirationValue.value, expirationUnit: expirationUnit.value,
    }
}
const leaveGuard = createUnsavedChangesGuard({
    snapshot: takeDraftSnapshot,
    pending: () => isSubmitting.value,
    pendingMessage: '物品正在儲存，離開後仍可能完成更新。確定離開此頁面？',
})
const saveBlockReason = computed(() => {
    if (!loaded.value || isLoading.value) return '請等待物品資料載入完成。'
    if (productCreationPending.value) return '請先完成或取消建立產品。'
    const invalid = Object.values(validationErrors.value)[0]
    if (invalid) return invalid
    if (photos.value.some(photo => photo.status === 'error')) return '照片上傳失敗，請重試或移除。'
    if (!photosReady.value) return '請等待所有照片上傳及處理完成。'
    if (!leaveGuard.isDirty.value) return '尚未修改資料。'
    return ''
})
const saveDisabled = computed(() => isSubmitting.value || Boolean(saveBlockReason.value))
const confirmLeave = () => !loaded.value || leaveGuard.confirmLeave()
onBeforeRouteLeave(confirmLeave)
onBeforeRouteUpdate(confirmLeave)

const applyItem = data => {
    resetArrivalLink()
    item.value = data
    selectedProduct.value = data.product ?? null
    productDraft.value = null
    productCreationPending.value = false
    form.value = createItemEditForm(data)
    photos.value = (data.images || []).map(image => ({
        id: `original-${image.uuid}`, uuid: image.uuid, status: 'done', progress: 100,
        preview: image.thumb_url || image.preview_url || image.original_url,
        thumb_url: image.thumb_url, preview_url: image.preview_url, url: image.original_url,
    }))
    originalPhotos.value = photos.value.map(photo => ({ ...photo }))
    dateDrafts.value = {}
    formErrors.value = {}
    touched.value = {}
    manufactureDate.value = ''
    expirationValue.value = ''
    expirationUnit.value = 'years'
    showManufactureDateModal.value = false
    summaryImageFailed.value = false
    loaded.value = true
    leaveGuard.markSaved()
}
const loadItem = async () => {
    loadController?.abort()
    const controller = new AbortController()
    loadController = controller
    loaded.value = false
    saveGeneration += 1
    isSubmitting.value = false
    isLoading.value = true
    loadError.value = ''
    saveNotice.value = ''
    submitError.value = ''
    leaveGuard.markSaved()
    try {
        const response = await axios.get(`/api/items/${encodeURIComponent(route.params.id)}`, { signal: controller.signal })
        if (active && loadController === controller) applyItem(response.data.data)
    } catch (error) {
        if (!active || loadController !== controller || error.code === 'ERR_CANCELED') return
        loadError.value = error.response?.status === 404 ? '找不到這件物品，可能已被刪除。' : '無法載入物品資料，請重試。'
    } finally { if (active && loadController === controller) isLoading.value = false }
}
const submitForm = async (stay = false) => {
    if (saveDisabled.value) return
    if (!itemForm.value?.reportValidity()) return
    const id = route.params.id
    const generation = ++saveGeneration
    const payload = createItemEditPayload(form.value, photos.value, originalPhotos.value, selectedProduct.value)
    isSubmitting.value = true
    formErrors.value = {}
    submitError.value = ''
    saveNotice.value = ''
    try {
        const response = await axios.put(`/api/items/${encodeURIComponent(id)}`, payload)
        if (!active || route.params.id !== id || saveGeneration !== generation) return
        applyItem(response.data.data)
        if (stay) saveNotice.value = '變更已儲存。'
        else await router.push('/items')
    } catch (error) {
        if (!active || route.params.id !== id || saveGeneration !== generation) return
        if (error.response?.status === 422) {
            formErrors.value = Object.fromEntries(Object.entries(error.response.data.errors || {}).map(([field, messages]) => [field, Array.isArray(messages) ? messages[0] : messages]))
            submitError.value = '儲存失敗，請修正標示的欄位後重試。'
            await nextTick()
            const first = itemForm.value?.querySelector('[aria-invalid="true"]')
            first?.focus()
        } else submitError.value = error.response?.data?.message || '儲存失敗，輸入內容已保留，請重試。'
    } finally { if (active && saveGeneration === generation) isSubmitting.value = false }
}
watch(() => ({ ...form.value }), (current, previous) => {
    saveNotice.value = ''
    if (previous) for (const key of Object.keys(current)) if (current[key] !== previous[key]) delete formErrors.value[key]
}, { flush: 'sync' })
watch(selectedProduct, () => { delete formErrors.value.product_id; saveNotice.value = '' }, { flush: 'sync' })
watch(() => photos.value[0]?.preview, () => { summaryImageFailed.value = false })
watch(() => route.params.id, loadItem, { immediate: true })
onBeforeUnmount(() => { active = false; loadController?.abort(); leaveGuard.dispose() })
</script>

<template>
    <main class="edit-page">
        <header class="page-heading">
            <h1>編輯物品</h1>
            <router-link to="/items" class="back-link">返回列表</router-link>
        </header>
        <div v-if="isLoading" class="page-state" role="status">正在載入物品資料…</div>
        <div v-else-if="loadError" class="page-state" role="alert">
            <p>{{ loadError }}</p>
            <AppButton variant="secondary" @click="loadItem">重新載入</AppButton>
        </div>
        <form v-else-if="loaded" ref="itemForm" class="item-form" :inert="isSubmitting" :aria-busy="isSubmitting" @submit.prevent="submitForm(false)" @keydown="preventImplicitSubmit">
            <div class="form-fields">
                <section class="form-section" aria-labelledby="edit-basic-title">
                    <h2 id="edit-basic-title">基本資料與日期</h2>
                    <div>
                        <label for="edit-name">名稱 <span class="required">*</span></label>
                        <ItemNameInput id="edit-name" v-model="form.name" required maxlength="255" :aria-invalid="Boolean(fieldError('name'))" :aria-describedby="fieldError('name') ? 'edit-name-error' : undefined" @blur="touched.name = true" />
                        <p v-if="fieldError('name')" id="edit-name-error" class="field-error">{{ fieldError('name') }}</p>
                    </div>
                    <div v-for="field in initialDates" :key="field.key">
                        <div class="field-heading">
                            <label :for="`edit-${field.key}`">{{ field.label }} <span v-if="field.required" class="required">*</span></label>
                            <label v-if="field.key === 'received_at'" class="same-day-toggle"><input v-model="receivedWithPurchase" type="checkbox" />到貨日同購買日</label>
                            <button v-if="field.key === 'expiration_date'" type="button" class="text-action" @click="showManufactureDateModal = true">使用製造日期換算</button>
                        </div>
                        <DateInput :id="`edit-${field.key}`" v-model="form[field.key]" :label="field.label" :required="field.required" :disabled="field.key === 'received_at' && receivedWithPurchase" :min="dateMin(field.key)" :max="dateMax(field.key)" :invalid="Boolean(fieldError(field.key))" :error-message="fieldError(field.key)" @draft-change="value => setDateDraft(field.key, value)" @blur="touched[field.key] = true" />
                    </div>
                </section>
                <section class="form-section" aria-labelledby="edit-price-title">
                    <h2 id="edit-price-title">物品成本</h2>
                    <div>
                        <label for="edit-price">單價</label>
                        <input id="edit-price" :value="form.price" type="text" inputmode="decimal" autocomplete="off" :aria-invalid="Boolean(fieldError('price'))" :aria-describedby="fieldError('price') ? 'edit-price-help edit-price-error' : 'edit-price-help'" @input="amountInput.onInput" @compositionstart="amountInput.onCompositionStart" @compositionend="amountInput.onCompositionEnd" @blur="touched.price = true" />
                        <p id="edit-price-help" class="field-help">此件物品的購入成本。</p>
                        <p v-if="fieldError('price')" id="edit-price-error" class="field-error">{{ fieldError('price') }}</p>
                    </div>
                </section>
                <ItemPhotos v-model="photos" :item-name="form.name" :error-message="formErrors.images || Object.entries(formErrors).find(([key]) => key.startsWith('images.'))?.[1] || ''">
                    <template #after>
                        <div v-if="removedPhotos.length" class="removed-photos">
                            <p class="field-help">{{ removedPhotos.length }} 張原有照片將於儲存後解除關聯。</p>
                            <div class="restore-actions">
                                <AppButton v-for="photo in removedPhotos" :key="photo.uuid" variant="secondary" :disabled="photos.length >= 9" @click="restorePhoto(photo)">
                                    <img :src="photo.thumb_url || photo.preview" alt="" />復原第 {{ originalPhotos.findIndex(original => original.uuid === photo.uuid) + 1 }} 張
                                </AppButton>
                            </div>
                        </div>
                    </template>
                </ItemPhotos>
                <section class="form-section" aria-labelledby="edit-description-title">
                    <h2 id="edit-description-title">描述與位置</h2>
                    <div>
                        <label for="edit-description">描述</label>
                        <textarea id="edit-description" v-model="form.description" rows="4" placeholder="可輸入多行描述" :aria-invalid="Boolean(fieldError('description'))" :aria-describedby="fieldError('description') ? 'edit-description-error' : undefined"></textarea>
                        <p v-if="fieldError('description')" id="edit-description-error" class="field-error">{{ fieldError('description') }}</p>
                    </div>
                    <div>
                        <label for="edit-location">位置</label>
                        <input id="edit-location" v-model="form.location" type="text" maxlength="255" :aria-invalid="Boolean(fieldError('location'))" :aria-describedby="fieldError('location') ? 'edit-location-error' : undefined" @blur="touched.location = true" />
                        <p v-if="fieldError('location')" id="edit-location-error" class="field-error">{{ fieldError('location') }}</p>
                    </div>
                </section>
                <section class="form-section" aria-labelledby="edit-other-dates-title">
                    <h2 id="edit-other-dates-title">其他日期</h2>
                    <div v-for="field in otherDates" :key="field.key">
                        <label :for="`edit-${field.key}`">{{ field.label }}</label>
                        <DateInput :id="`edit-${field.key}`" v-model="form[field.key]" :label="field.label" :min="dateMin(field.key)" :max="dateMax(field.key)" :invalid="Boolean(fieldError(field.key))" :error-message="fieldError(field.key)" @draft-change="value => setDateDraft(field.key, value)" @blur="touched[field.key] = true" />
                    </div>
                </section>
                <section class="form-section" aria-labelledby="edit-product-title">
                    <h2 id="edit-product-title">關聯產品</h2>
                    <div>
                        <label for="edit-product">選擇產品</label>
                        <ProductLinkSelect id="edit-product" v-model="selectedProduct" :error-message="formErrors.product_id || ''" @creation-pending="value => productCreationPending = value" @draft-change="value => productDraft = value" />
                    </div>
                    <template v-if="selectedProduct">
                        <ProductSummary :product="selectedProduct" />
                        <router-link v-if="selectedProduct.short_id" :to="`/products/${selectedProduct.short_id}`" target="_blank" rel="noopener noreferrer" class="text-action product-view-link" aria-label="查看產品（另開分頁）" title="另開分頁">查看產品<AppIcon name="external" :size="16" /></router-link>
                    </template>
                </section>
            </div>
            <aside class="form-section summary-panel" aria-labelledby="edit-summary-title">
                <h2 id="edit-summary-title">儲存前確認</h2>
                <div class="summary-item">
                    <div class="summary-thumbnail"><img v-if="photos[0]?.preview && !summaryImageFailed" :src="photos[0].preview" alt="" @error="summaryImageFailed = true" /><AppIcon v-else name="package" :size="28" /></div>
                    <p class="summary-name">{{ form.name || '尚未填寫名稱' }}</p>
                </div>
                <dl class="summary-details">
                    <div v-for="field in initialDates" :key="field.key"><dt>{{ field.label }}</dt><dd>{{ form[field.key] || '未填寫' }}</dd></div>
                    <div><dt>每件單價</dt><dd>{{ priceSummary.valid ? formatAmount(priceSummary.unitPrice) : '請檢查金額' }}</dd></div>
                    <div><dt>照片</dt><dd>{{ photos.length }} 張</dd></div>
                    <div><dt>關聯產品</dt><dd>{{ selectedProduct?.name || '未關聯' }}</dd></div>
                </dl>
                <p v-if="submitError" class="field-error" role="alert">{{ submitError }}</p>
                <div class="form-actions">
                    <AppButton type="submit" :disabled="saveDisabled" :aria-busy="isSubmitting" :aria-describedby="saveBlockReason ? 'edit-save-reason' : undefined">{{ isSubmitting ? '儲存中…' : '儲存變更' }}</AppButton>
                    <AppButton variant="secondary" :disabled="saveDisabled" :aria-describedby="saveBlockReason ? 'edit-save-reason' : undefined" @click="submitForm(true)">儲存並繼續編輯</AppButton>
                </div>
                <p class="save-notice" role="status">{{ saveNotice }}</p>
                <p id="edit-save-reason" class="sr-only">{{ saveBlockReason }}</p>
            </aside>
            <div v-if="showManufactureDateModal" class="converter-overlay">
                <section class="converter-panel" role="dialog" aria-modal="true" aria-labelledby="edit-converter-title">
                    <h2 id="edit-converter-title">製造日期換算有效期限</h2>
                    <div><label for="edit-manufacture-date">製造日期</label><DateInput id="edit-manufacture-date" v-model="manufactureDate" label="製造日期" @draft-change="value => setDateDraft('manufactureDate', value)" /></div>
                    <div><label for="edit-expiration-length">有效期限長度</label><div class="converter-length"><input id="edit-expiration-length" type="text" inputmode="numeric" :value="expirationValue" @input="expirationInput.onInput" @compositionstart="expirationInput.onCompositionStart" @compositionend="expirationInput.onCompositionEnd" /><select v-model="expirationUnit" aria-label="有效期限單位"><option value="years">年</option><option value="months">月</option><option value="days">日</option></select></div></div>
                    <p class="field-help">計算結果：{{ calculatedExpirationDate || '請填寫製造日期與有效期限長度' }}</p>
                    <div class="converter-actions"><AppButton variant="secondary" @click="showManufactureDateModal = false">取消</AppButton><AppButton :disabled="!calculatedExpirationDate" @click="applyCalculatedDate">套用</AppButton></div>
                </section>
            </div>
        </form>
    </main>
</template>

<style scoped>
.edit-page { min-height: 100vh; padding: 24px 16px 32px; background: var(--canvas); color: var(--text); }
.page-heading { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
h1 { margin: 0; font-size: 24px; line-height: 1.3; font-weight: 600; }
h2 { margin: 0; font-size: 18px; line-height: 1.5; font-weight: 600; }
.back-link { min-height: 44px; padding: 10px 12px; border: 1px solid var(--border-control); border-radius: var(--radius-control); background: var(--surface); color: var(--text); font-size: 14px; text-decoration: none; display: inline-flex; align-items: center; }
.page-state { margin-top: 24px; min-height: 240px; display: grid; align-content: center; justify-items: center; gap: 16px; color: var(--text-muted); }
.item-form { display: grid; grid-template-columns: minmax(0, 1fr); align-items: start; gap: 24px; max-width: 720px; margin: 24px auto 0; }
.form-fields { display: grid; gap: 24px; min-width: 0; }
.form-section { min-width: 0; padding: 16px; display: grid; gap: 24px; background: var(--surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-panel); }
.edit-page label { display: block; margin-bottom: 8px; font-size: 14px; font-weight: 500; line-height: 1.5; }
.edit-page input:not([type=checkbox]), .edit-page textarea, .edit-page select { width: 100%; min-width: 0; min-height: 44px; padding: 10px 12px; border: 1px solid var(--border-control); border-radius: var(--radius-control); background: var(--surface); color: var(--text); font-size: 16px; font-weight: 400; line-height: 1.5; }
.edit-page input[aria-invalid=true], .edit-page textarea[aria-invalid=true] { border-color: var(--danger); }
.edit-page textarea:not(.item-name-input) { resize: vertical; }
.edit-page input::placeholder, .edit-page textarea::placeholder { color: var(--text-muted); opacity: 1; }
a:focus-visible, button:focus-visible, input:focus-visible, textarea:focus-visible, select:focus-visible { outline: 2px solid var(--brand-ink); outline-offset: 2px; }
.field-heading { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 8px; }
.field-heading > label { margin: 0; }
.edit-page .same-day-toggle { display: inline-flex; align-items: center; min-height: 44px; gap: 8px; margin: 0; color: var(--text-muted); cursor: pointer; }
.same-day-toggle input { width: 20px; height: 20px; accent-color: var(--brand); }
.required, .field-error { color: var(--danger); }
.field-help, .field-error { margin: 4px 0 0; font-size: 14px; line-height: 1.5; }
.field-help { color: var(--text-muted); }
.product-view-link { gap: 6px; }
.text-action { display: inline-flex; align-items: center; min-height: 44px; padding: 8px 0; border: 0; background: transparent; color: var(--brand-ink); font-size: 14px; text-decoration: underline; text-underline-offset: 3px; cursor: pointer; }
.summary-item { display: flex; align-items: center; gap: 12px; min-width: 0; }
.summary-thumbnail { width: 64px; height: 64px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; border-radius: var(--radius-control); background: var(--canvas); color: var(--text-muted); overflow: hidden; }
.summary-thumbnail img { width: 100%; height: 100%; object-fit: cover; dynamic-range-limit: standard; }
.summary-name { min-width: 0; margin: 0; font-size: 16px; font-weight: 500; overflow-wrap: anywhere; }
.summary-details { display: grid; gap: 12px; margin: 0; font-size: 14px; line-height: 1.5; }
.summary-details > div { display: grid; grid-template-columns: 76px minmax(0, 1fr); gap: 8px; }
.summary-details dt { color: var(--text-muted); }
.summary-details dd { margin: 0; overflow-wrap: anywhere; }
.form-actions { display: grid; gap: 12px; }
.save-notice { min-height: 1.5em; margin: 0; color: var(--text-muted); font-size: 14px; line-height: 1.5; }
.restore-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
.restore-actions img { width: 32px; height: 32px; object-fit: contain; margin-right: 8px; dynamic-range-limit: standard; }
.converter-overlay { position: fixed; inset: 0; z-index: 50; display: flex; align-items: center; justify-content: center; padding: 16px; background: var(--backdrop); }
.converter-panel { display: grid; gap: 24px; width: 100%; max-width: 400px; max-height: 90dvh; overflow-y: auto; padding: 24px; border-radius: var(--radius-panel); background: var(--surface); }
.converter-length { display: grid; grid-template-columns: minmax(0, 1fr) 80px; gap: 8px; }
.converter-actions { display: flex; justify-content: flex-end; gap: 12px; }
@media (min-width: 768px) { h1 { font-size: 28px; } }
@media (min-width: 1024px) { .item-form { max-width: none; grid-template-columns: minmax(0, 1fr) 320px; } .summary-panel { position: sticky; top: 88px; max-height: calc(100dvh - 104px); overflow-y: auto; scrollbar-gutter: stable; } }
</style>
