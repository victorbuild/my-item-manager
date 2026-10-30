<script setup>
import { computed, defineAsyncComponent, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { onBeforeRouteLeave, onBeforeRouteUpdate, useRoute, useRouter } from 'vue-router'
import axios from '../../axios'
import AppButton from '../../Components/AppButton.vue'
import AppIcon from '../../Components/AppIcon.vue'
import ItemStatusBadge from '../../Components/ItemStatusBadge.vue'
import ItemStatusScene from '../../Components/ItemStatusScene.vue'
import RekaModal from '../../Components/RekaModal.vue'
import DateInput from '../../Components/DateInput.vue'
import { getDateMin, getDateMax, getTodayDate } from '../../utils/itemDates'
import { getDateTextError } from '../../utils/dateTextInput'
import { createUnsavedChangesGuard } from '../../utils/unsavedChanges'
import { ITEM_SHOW_DATES, getItemNextAction, getItemShowMetrics } from '../../utils/itemShowState'

const ItemLifecycle = defineAsyncComponent(() => import('../../Components/ItemLifecycle.vue'))
const route = useRoute(), router = useRouter()
const item = ref(null), loading = ref(true), loadError = ref(''), notice = ref('')
const today = ref(getTodayDate())
const editor = ref(null), draftDate = ref(''), draftNote = ref(''), initialDraft = ref('')
const saving = ref(false), deleting = ref(false), submitError = ref(''), serverErrors = ref({})
const typedDate = ref(null)
const deleteOpen = ref(false), deleteError = ref('')
const discardConfirmationOpen = ref(false)
let resolveDiscard, discardPromise
const finishDiscardConfirmation = accepted => {
    discardConfirmationOpen.value = false
    resolveDiscard?.(accepted)
    resolveDiscard = undefined
    discardPromise = undefined
}
const confirmDiscard = () => {
    if (!discardPromise) discardPromise = new Promise(resolve => {
        resolveDiscard = resolve
        discardConfirmationOpen.value = true
    })
    return discardPromise
}
const editorForm = ref(null), previewIndex = ref(null), activeImage = ref(0), failedImages = ref({})
let alive = true, loadController, generation = 0
const nextAction = computed(() => getItemNextAction(item.value || {}, today.value))
const metrics = computed(() => getItemShowMetrics(item.value || {}, today.value))
const images = computed(() => item.value?.images || [])
const previewOpen = computed({ get: () => previewIndex.value !== null, set: value => { if (!value) previewIndex.value = null } })
const editorOpen = computed({ get: () => editor.value !== null, set: value => { if (!value) closeEditor() } })
const isNoteEditor = computed(() => ['note', 'notes'].includes(editor.value))
const editorTitle = computed(() => editor.value === 'notes' ? '心得與回憶' : editor.value === 'note' ? '棄用備註' : ITEM_SHOW_DATES.find(date => date.key === editor.value)?.label || '')
const effectiveDates = computed(() => ({ ...item.value, ...(editor.value && !isNoteEditor.value ? { [editor.value]: draftDate.value } : {}) }))
const dateMin = computed(() => editor.value === 'expiration_date' ? undefined : getDateMin(effectiveDates.value, editor.value))
const dateMax = computed(() => editor.value === 'expiration_date' ? '9999-12-31' : getDateMax(effectiveDates.value, editor.value))
const dateError = computed(() => editor.value && !isNoteEditor.value ? getDateTextError(typedDate.value ?? draftDate.value, dateMin.value, dateMax.value) : '')
const serializeDraft = () => JSON.stringify([draftDate.value, draftNote.value, typedDate.value])
const draftChanged = computed(() => !!editor.value && serializeDraft() !== initialDraft.value)
const leaveGuard = createUnsavedChangesGuard({
    snapshot: () => editor.value && draftChanged.value ? [editor.value, draftDate.value, draftNote.value, typedDate.value] : null,
    confirm: confirmDiscard,
    pending: () => saving.value,
    pendingMessage: '變更正在儲存，離開後仍可能完成。確定離開？',
})
onBeforeRouteLeave(() => !saving.value && leaveGuard.confirmLeave())
onBeforeRouteUpdate(() => !saving.value && leaveGuard.confirmLeave())
const resetEditor = () => {
    editor.value = null
    typedDate.value = null
    draftDate.value = ''; draftNote.value = ''; initialDraft.value = ''
    submitError.value = ''; serverErrors.value = {}
    leaveGuard.markSaved()
}
const closeEditor = async () => {
    if (saving.value || !await leaveGuard.confirmLeave()) return
    resetEditor()
}
const openEditor = (field, quick = false) => {
    if (saving.value || deleting.value) return
    submitError.value = ''; serverErrors.value = {}
    draftDate.value = ['note', 'notes'].includes(field) ? '' : item.value[field] || (quick ? today.value : '')
    draftNote.value = (field === 'notes' ? item.value.notes : item.value.discard_note) || ''
    typedDate.value = null
    // 比較儲存前的實際資料，不能把快速操作預填的今天視為已儲存。
    initialDraft.value = JSON.stringify([['note', 'notes'].includes(field) ? '' : item.value[field] || '', draftNote.value, null])
    editor.value = field
    leaveGuard.markSaved()
}
watch([draftDate, draftNote], () => { serverErrors.value = {}; submitError.value = '' })
const fetchItem = async () => {
    const token = ++generation
    loadController?.abort()
    loadController = new AbortController()
    loading.value = true; loadError.value = ''; item.value = null
    deleteOpen.value = false; deleteError.value = ''; finishDiscardConfirmation(false)
    resetEditor(); previewIndex.value = null; activeImage.value = 0; failedImages.value = {}; notice.value = ''
    try {
        const response = await axios.get(`/api/items/${route.params.id}`, { signal: loadController.signal })
        if (alive && token === generation) item.value = response.data.data
    } catch (error) {
        if (!alive || token !== generation || axios.isCancel(error)) return
        loadError.value = error.response?.status === 404 ? '找不到此物品。' : error.response?.status === 403 ? '無法查看此物品。' : '載入失敗，請重試。'
    } finally { if (alive && token === generation) loading.value = false }
}
watch(() => route.params.id, fetchItem, { immediate: true })
const saveEditor = async () => {
    if (saving.value || !draftChanged.value || dateError.value || !editorForm.value?.reportValidity()) return
    const token = generation, id = item.value.short_id, field = editor.value
    const payload = field === 'notes' ? { notes: draftNote.value } : field === 'note' ? { discard_note: draftNote.value }
        : { [field]: draftDate.value || null }
    saving.value = true; submitError.value = ''; serverErrors.value = {}
    try {
        const response = await axios.patch(`/api/items/${id}`, payload)
        if (!alive || generation !== token) return
        item.value = response.data.data
        notice.value = `${editorTitle.value}已儲存。`
        resetEditor()
    } catch (error) {
        if (!alive || generation !== token) return
        serverErrors.value = error.response?.data?.errors || {}
        submitError.value = error.response?.status === 422 ? '無法儲存，請確認欄位內容。' : '儲存失敗，輸入內容已保留，請重試。'
        await nextTick()
        editorForm.value?.querySelector('[aria-invalid="true"]')?.focus()
    } finally { if (alive) saving.value = false }
}
const deleteItem = async () => {
    if (saving.value || deleting.value || !deleteOpen.value) return
    deleting.value = true
    deleteError.value = ''
    const token = generation
    try {
        await axios.delete(`/api/items/${item.value.short_id}`)
        if (alive && generation === token) await router.push('/items')
    } catch { if (alive && generation === token) deleteError.value = '刪除失敗，請稍後重試。' }
    finally { deleting.value = false }
}
const money = value => value == null || value === '' ? '未填寫' : Number(value).toLocaleString('zh-TW', { maximumFractionDigits: 2 })
const errorText = field => serverErrors.value[field]?.[0] || ''
const refreshToday = () => { today.value = getTodayDate() }
const todayTimer = setInterval(refreshToday, 60000)
onBeforeUnmount(() => { alive = false; generation++; loadController?.abort(); clearInterval(todayTimer); leaveGuard.dispose(); finishDiscardConfirmation(false) })
</script>

<template>
    <div class="item-show">
        <header class="page-heading">
            <router-link to="/items" class="quiet-link"><AppIcon name="back" :size="18" />返回列表</router-link>
            <router-link v-if="item" :to="`/items/${item.short_id}/edit`" class="quiet-link"><AppIcon name="edit" :size="18" />編輯物品</router-link>
        </header>
        <div v-if="loading" class="empty-state" role="status">載入物品中…</div>
        <div v-else-if="loadError" class="empty-state" role="alert"><p>{{ loadError }}</p><AppButton variant="secondary" @click="fetchItem">重新載入</AppButton></div>
        <template v-else-if="item">
            <div class="title-row">
                <div><p class="eyebrow">物品詳情</p><h1>{{ item.name }}</h1></div>
                <ItemStatusBadge :status="item.status" />
            </div>
            <p v-if="notice" class="notice" role="status">{{ notice }}</p>
            <div class="overview panel">
                <section class="photo-panel" aria-label="物品圖片">
                    <button v-if="images.length && !failedImages[activeImage]" type="button" class="main-photo" aria-label="放大查看物品圖片" @click="previewIndex = activeImage">
                        <img :src="images[activeImage]?.preview_url" :alt="`${item.name}，第 ${activeImage + 1} 張圖片`" @error="failedImages[activeImage] = true" />
                    </button>
                    <div v-else class="photo-empty"><AppIcon name="package" :size="48" /><span>{{ images.length ? '圖片暫時無法顯示' : '尚未加入圖片' }}</span></div>
                    <div v-if="images.length > 1" class="thumbnails" aria-label="選擇圖片">
                        <button v-for="(image, index) in images" :key="image.uuid || index" type="button" :aria-label="`查看第 ${index + 1} 張圖片`" :aria-pressed="activeImage === index" @click="activeImage = index">
                            <img :src="image.thumb_url || image.preview_url" alt="" loading="lazy" />
                        </button>
                    </div>
                </section>
                <section class="action-panel" aria-labelledby="next-action-title">
                    <div class="status-heading">
                        <div class="status-copy"><p class="eyebrow">目前狀態</p><h2 id="next-action-title">{{ nextAction.title }}</h2><p class="muted">{{ nextAction.description }}</p></div>
                        <ItemStatusScene :item="item" :today="today" />
                    </div>
                    <p v-if="metrics.expirationText" class="expiry" :class="{ overdue: metrics.expiration < 0, soon: metrics.expiration >= 0 && metrics.expiration <= 30 }">{{ metrics.expirationText }}<span v-if="metrics.expiration < 0">，不代表已棄用。</span></p>
                    <AppButton class="next-button" :disabled="deleting" @click="openEditor(nextAction.field, true)">{{ nextAction.label }}</AppButton>
                    <button v-if="!item.discarded_at && nextAction.field !== 'discarded_at'" type="button" class="quiet-link alternate-action" @click="openEditor('discarded_at', true)">記錄棄用</button>
                    <dl class="key-facts">
                        <div><dt>每件單價</dt><dd>{{ money(item.price) }}</dd></div>
                        <div><dt>位置</dt><dd>{{ item.location || '未指定' }}</dd></div>
                        <div><dt>有效期限</dt><dd>{{ item.expiration_date || '未填寫' }}</dd></div>
                    </dl>
                </section>
            </div>
            <div class="detail-grid">
                <ItemLifecycle :item="item" @edit-date="openEditor" />
                <div class="main-details">
                    <section class="panel memories-panel" aria-labelledby="memories-title">
                        <div class="section-heading"><h2 id="memories-title">心得與回憶</h2><button type="button" class="quiet-link" @click="openEditor('notes')">{{ item.notes ? '修改' : '寫下心得' }}</button></div>
                        <p v-if="item.notes" class="preserve-text">{{ item.notes }}</p>
                        <p v-else class="muted">當初為什麼買？用起來如何？有什麼想留下的？</p>
                        <p class="helper memory-hint">隨時記下，留給以後的自己參考。</p>
                        <div class="disposal-record" aria-labelledby="note-title">
                            <div class="section-heading"><h2 id="note-title">棄用原因</h2><button type="button" class="quiet-link" @click="openEditor('note')">{{ item.discard_note ? '修改' : '新增備註' }}</button></div>
                            <p v-if="item.discard_note" class="preserve-text">{{ item.discard_note }}</p>
                            <p v-else class="muted">記下不再使用的原因，也可以先寫下想法，之後再補棄用日期。</p>
                        </div>
                    </section>
                    <section v-if="item.description || item.serial_number || item.product" class="panel" aria-labelledby="details-title">
                        <h2 id="details-title">物品資訊</h2>
                        <dl class="info-list">
                            <div v-if="item.description"><dt>描述</dt><dd class="preserve-text">{{ item.description }}</dd></div>
                            <div v-if="item.serial_number"><dt>序號</dt><dd>{{ item.serial_number }}</dd></div>
                            <div v-if="item.product"><dt>關聯產品</dt><dd><router-link :to="`/products/${item.product.short_id}`" target="_blank" rel="noopener noreferrer" class="quiet-link" aria-label="查看關聯產品（另開分頁）">{{ item.product.name }}<AppIcon name="external" :size="16" /></router-link></dd></div>
                            <div v-if="item.product"><dt>分類</dt><dd>{{ item.product.category?.name || '未分類' }}</dd></div>
                            <div v-if="item.product?.barcode"><dt>產品條碼</dt><dd>{{ item.product.barcode }}</dd></div>
                        </dl>
                    </section>
                </div>
            </div>
            <footer class="page-footer"><p class="helper">棄用會保留紀錄；刪除會移除整件物品。</p><button type="button" class="delete-link" :disabled="deleting" @click="deleteError = ''; deleteOpen = true">{{ deleting ? '刪除中…' : '刪除物品' }}</button></footer>
        </template>
        <RekaModal v-model="editorOpen" :title="editorTitle" :busy="saving" compact>
            <form ref="editorForm" class="quick-form" @submit.prevent="saveEditor">
                <template v-if="editor && !isNoteEditor">
                    <label for="show-date" class="sr-only">{{ editorTitle }}</label>
                    <DateInput :key="editor" :disabled="saving" id="show-date" v-model="draftDate" :label="editorTitle" :min="dateMin" :max="dateMax" :invalid="!!(errorText(editor) || dateError)" :error-message="errorText(editor) || dateError" @draft-change="typedDate = $event" />
                    <p class="helper">{{ draftDate === today ? '目前選擇今天，可改成實際日期。' : '可輸入日期或使用日曆選取；清空日期可取消該紀錄。' }}</p>
                    <p v-if="errorText(editor) || dateError" class="error" role="alert">{{ errorText(editor) || dateError }}</p>
                </template>
                <template v-if="editor === 'notes'">
                    <label for="show-memories" class="sr-only">心得與回憶</label>
                    <textarea id="show-memories" v-model="draftNote" rows="6" :aria-invalid="!!errorText('notes')" aria-describedby="show-memories-help show-memories-error" placeholder="當初為什麼買？用起來如何？有什麼想留下的？"></textarea>
                    <p id="show-memories-help" class="helper">可以只寫一句，之後再補充。儲存不會改變物品狀態或棄用備註。</p>
                    <p id="show-memories-error" class="error" :role="errorText('notes') ? 'alert' : undefined">{{ errorText('notes') }}</p>
                </template>
                <template v-if="editor === 'note'">
                    <label for="show-discard-note" class="sr-only">棄用備註</label>
                    <textarea id="show-discard-note" v-model="draftNote" rows="5" :aria-invalid="!!errorText('discard_note')" aria-describedby="show-note-help" placeholder="為什麼不再使用這件物品？"></textarea>
                    <p id="show-note-help" class="helper">備註可單獨保存，不會變更使用狀態。</p>
                    <p v-if="errorText('discard_note')" class="error" role="alert">{{ errorText('discard_note') }}</p>
                </template>
                <p v-if="submitError" class="error" role="alert">{{ submitError }}</p>
            </form>
            <template #footer><AppButton variant="secondary" :disabled="saving" @click="closeEditor">取消</AppButton><AppButton :disabled="saving || !draftChanged || !!dateError" :aria-busy="saving" @click="saveEditor">{{ saving ? '儲存中…' : '儲存' }}</AppButton></template>
        </RekaModal>
        <RekaModal :model-value="discardConfirmationOpen" title="放棄尚未儲存的變更？" description="目前輸入的內容尚未儲存，放棄後將無法復原。" @update:model-value="value => { if (!value) finishDiscardConfirmation(false) }">
            <p>你可以繼續填寫，或放棄這次變更。</p>
            <template #footer><AppButton variant="secondary" @click="finishDiscardConfirmation(false)">繼續填寫</AppButton><AppButton @click="finishDiscardConfirmation(true)">放棄變更</AppButton></template>
        </RekaModal>
        <RekaModal v-model="deleteOpen" title="刪除物品？" description="如果只是不再使用，記錄棄用可以保留日期與回憶。" :busy="deleting">
            <p class="preserve-text">確定永久刪除「{{ item?.name }}」？</p>
            <p v-if="deleteError" class="error" role="alert">{{ deleteError }}</p>
            <template #footer><AppButton variant="secondary" :disabled="deleting" @click="deleteOpen = false">取消</AppButton><AppButton class="confirm-delete" :disabled="deleting" @click="deleteItem">{{ deleting ? '刪除中…' : '確認刪除' }}</AppButton></template>
        </RekaModal>
        <RekaModal v-model="previewOpen" title="圖片預覽" wide>
            <template v-if="previewIndex !== null && images[previewIndex]">
                <img class="preview-image" :src="images[previewIndex].original_url || images[previewIndex].preview_url" :alt="`${item.name}，第 ${previewIndex + 1} 張圖片`" />
                <div class="preview-controls"><AppButton variant="secondary" :disabled="previewIndex === 0" @click="previewIndex--">上一張</AppButton><span aria-live="polite">{{ previewIndex + 1 }} / {{ images.length }}</span><AppButton variant="secondary" :disabled="previewIndex === images.length - 1" @click="previewIndex++">下一張</AppButton></div>
            </template>
        </RekaModal>
    </div>
</template>

<style scoped>
.item-show { color: var(--text); padding: 24px 16px 32px; min-width: 0; }
.page-heading, .title-row, .section-heading, .page-footer { display: flex; justify-content: space-between; align-items: center; gap: 16px; }
.page-heading { margin-bottom: 24px; }
h1 { font-size: 24px; font-weight: 600; line-height: 1.4; overflow-wrap: anywhere; margin: 4px 0 0; }
h2 { font-size: 18px; font-weight: 600; line-height: 1.5; margin: 0; }
p { margin: 0; line-height: 1.6; }
.eyebrow, .muted, .helper, dt { color: var(--text-muted); }
.eyebrow, .helper { font-size: 14px; }
.title-row { align-items: flex-start; margin-bottom: 24px; }
.title-row > div, dd { min-width: 0; }
.disposal-record { margin-top: 24px; padding-top: 24px; border-top: 1px solid var(--border-subtle); }
.overview, .detail-grid, .main-details { display: grid; gap: 24px; min-width: 0; }
.overview { margin-bottom: 24px; align-items: start; }
.panel { min-width: 0; padding: 24px; background: var(--surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-panel); }
.photo-panel { min-width: 0; }
.main-photo, .photo-empty { width: 100%; height: 280px; background: var(--surface); border: 0; border-radius: var(--radius-control); overflow: hidden; }
.main-photo { display: block; padding: 0; cursor: zoom-in; }
.main-photo img { width: 100%; height: 100%; object-fit: contain; }
.photo-empty { display: flex; gap: 16px; flex-direction: column; align-items: center; justify-content: center; color: var(--text-muted); }
.thumbnails { display: flex; gap: 12px; padding: 12px 2px 4px; overflow-x: auto; }
.thumbnails button { width: 56px; height: 56px; flex: 0 0 56px; border: 1px solid var(--border-control); border-radius: var(--radius-control); padding: 2px; background: var(--surface); cursor: pointer; }
.thumbnails button[aria-pressed="true"] { outline: 2px solid var(--brand); outline-offset: 1px; }
.thumbnails img { width: 100%; height: 100%; object-fit: contain; }
.action-panel { display: flex; flex-direction: column; align-items: flex-start; gap: 12px; }
.status-heading { display: flex; align-items: center; gap: 12px; width: 100%; min-width: 0; }
.status-copy { flex: 1; min-width: 0; display: grid; gap: 12px; }
.next-button { width: 100%; margin-top: 4px; }
.quiet-link, .delete-link { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 44px; padding: 4px 0; border: 0; background: transparent; color: var(--brand-ink); font-size: 14px; text-decoration: underline; text-underline-offset: 3px; cursor: pointer; overflow-wrap: anywhere; }
.alternate-action { align-self: center; }
.key-facts { width: 100%; margin: 12px 0 0; padding-top: 16px; border-top: 1px solid var(--border-subtle); }
.key-facts > div { display: grid; grid-template-columns: 100px minmax(0, 1fr); gap: 12px; margin-bottom: 8px; }
dd { margin: 0; overflow-wrap: anywhere; }
.date-list { margin: 16px 0 0; }
.date-list > div { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: center; column-gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--border-subtle); }
.date-list > div:last-child { border-bottom: 0; padding-bottom: 0; }
.date-list dt { font-size: 14px; }
.date-list .row-action { grid-column: 2; grid-row: 1 / 3; }
.info-list { margin: 16px 0 0; display: grid; gap: 20px; }
.info-list dt { font-size: 14px; margin-bottom: 4px; }
.info-list .quiet-link { justify-content: flex-start; }
.insights { align-self: start; }
.insights .helper { margin-top: 24px; }
.memory-hint { margin-top: 12px; }
.preserve-text { max-width: 72ch; white-space: pre-wrap; overflow-wrap: anywhere; }
.section-heading { margin-bottom: 12px; }
.expiry { font-size: 14px; color: var(--text-muted); }
.expiry.overdue { color: var(--danger); }
.expiry.soon { color: var(--warning); }
.notice { background: var(--success-soft); color: var(--success); padding: 12px 16px; border-radius: var(--radius-control); margin-bottom: 24px; }
.lifecycle-details { margin-top: 24px; }
summary { min-height: 44px; align-content: center; cursor: pointer; color: var(--brand-ink); }
.page-footer { margin-top: 24px; flex-wrap: wrap; }
.delete-link { color: var(--danger); }
.confirm-delete { background: var(--danger); border-color: var(--danger); }
.confirm-delete:hover:not(:disabled) { background: var(--danger); border-color: var(--danger); }
.delete-link:disabled { color: var(--text-muted); cursor: not-allowed; }
.empty-state { min-height: 320px; display: grid; place-content: center; gap: 16px; text-align: center; }
.quick-form { display: grid; gap: 8px; }
.quick-form label { font-size: 14px; font-weight: 500; }
.quick-form textarea { width: 100%; min-height: 120px; border: 1px solid var(--border-control); border-radius: var(--radius-control); background: var(--surface); color: var(--text); padding: 12px; font: inherit; font-size: 16px; resize: vertical; }
.quick-form textarea[aria-invalid="true"] { border-color: var(--danger); }
.quick-form label:not(:first-child):not(.sr-only) { margin-top: 16px; }
.error { color: var(--danger); font-size: 14px; }
.preview-image { width: 100%; max-height: 65dvh; object-fit: contain; }
.preview-controls { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-top: 16px; }
img { dynamic-range-limit: standard; }
button:focus-visible, a:focus-visible, textarea:focus-visible, summary:focus-visible { outline: 2px solid var(--brand-ink); outline-offset: 3px; }
@media (min-width: 640px) { .date-list > div { grid-template-columns: 150px minmax(0, 1fr) auto; } .date-list .row-action { grid-column: 3; grid-row: auto; } }
@media (min-width: 960px) { h1 { font-size: 28px; } .overview { grid-template-columns: minmax(0, 1.2fr) minmax(320px, 1fr); } .main-photo, .photo-empty { height: 360px; } }
@media (max-width: 479px) { .panel { padding: 16px; } .title-row { flex-direction: column; gap: 12px; } }
@media (max-width: 767px) { .action-panel { order: -1; } }
</style>
