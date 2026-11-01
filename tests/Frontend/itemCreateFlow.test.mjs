import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { parse, compileScript } from '@vue/compiler-sfc'
import { computed, ref, watch, nextTick, effectScope } from 'vue'
import { usePurchaseArrivalLink } from '../../resources/js/composables/usePurchaseArrivalLink.js'
import { useItemPricing } from '../../resources/js/composables/useItemPricing.js'
import { createNumericInputHandlers } from '../../resources/js/utils/numericInput.js'
import { createUnsavedChangesGuard } from '../../resources/js/utils/unsavedChanges.js'
import { preventImplicitSubmit } from '../../resources/js/utils/formKeyboard.js'
import { getItemSaveBlockReason } from '../../resources/js/utils/itemSaveState.js'
import { requiresQuantityConfirmation, isQuantityConfirmed } from '../../resources/js/utils/itemCreationSummary.js'
import { getTodayDate, getDateMin, getDateMax } from '../../resources/js/utils/itemDates.js'
import { getAvailableItemMedia, getNewItemMedia, getUniqueItemImagePayload, imageUuidKey } from '../../resources/js/utils/itemImageSelection.js'
import { formatDateText, parseDateText, getDateTextError } from '../../resources/js/utils/dateTextInput.js'

const { descriptor } = parse(readFileSync(new URL('../../resources/js/Pages/Items/Create.vue', import.meta.url), 'utf8'))
const source = compileScript(descriptor, { id: 'create-flow-test' }).content.replace(/^import .*\n/gm, '').replace('export default', 'return')
const withCreate = async verify => {
    const previousWindow = globalThis.window, previousDocument = globalThis.document
    globalThis.window = { addEventListener() {}, removeEventListener() {}, confirm: () => false }
    globalThis.document = { querySelector: () => null, getElementById: () => null }
    const calls = [], cleanups = [], routes = []
    const bindings = {
        computed, ref, watch, nextTick, onMounted() {}, onBeforeUnmount: fn => cleanups.push(fn), onBeforeRouteLeave() {},
        useRouter: () => ({ push: path => routes.push(path) }),
        AppIcon: {}, ItemNameInput: {}, ProductSummary: {}, ProductLinkSelect: {}, AppButton: {}, AppModal: {}, DateInput: {},
        useSortablePhotos: () => ({ photoGrid: ref(null), reorderNotice: ref(''), onPreviewClick() {}, movePhotoWithKeyboard() {} }),
        usePurchaseArrivalLink, createNumericInputHandlers, useItemPricing, getItemSaveBlockReason, requiresQuantityConfirmation, isQuantityConfirmed,
        getTodayDate, getDateMin, getDateMax, createUnsavedChangesGuard, preventImplicitSubmit,
        getAvailableItemMedia, getNewItemMedia, getUniqueItemImagePayload, imageUuidKey, formatDateText, parseDateText, getDateTextError,
        axios: { post: async (url, payload) => { calls.push({ url, payload }); return { data: { data: {} } } } },
        Swal: { fire: async () => ({ isConfirmed: true }) }, isDngFile: () => false, isSupportedImageFile: () => true, prepareImageForUpload: async value => value,
    }
    const component = new Function(...Object.keys(bindings), source)(...Object.values(bindings))
    const scope = effectScope()
    let vm
    scope.run(() => { vm = component.setup({}, { expose() {} }) })
    vm.itemForm.value = { reportValidity: () => true }
    try { await verify({ vm, calls, routes }) }
    finally { cleanups.forEach(fn => fn()); scope.stop(); globalThis.window = previousWindow; globalThis.document = previousDocument }
}

test('the create page saves the selected product through the shared selector without overwriting the item name', async () => {
    await withCreate(async ({ vm, calls }) => {
        vm.form.value.name = '物品名稱'
        vm.selectedProduct.value = { id: 9, name: '產品名稱' }
        await vm.submitForm(false)
        assert.equal(calls[0].payload.product_id, 9)
        assert.equal(calls[0].payload.name, '物品名稱')
    })
})
test('unfinished product creation blocks saving and participates in unsaved-changes protection', async () => {
    await withCreate(async ({ vm, calls }) => {
        vm.form.value.name = '物品'
        vm.productCreationPending.value = true
        vm.productDraft.value = { name: '未建立產品' }
        assert.equal(vm.saveDisabled.value, true)
        assert.equal(vm.leaveGuard.confirmLeave(), false)
        await vm.submitForm(false)
        assert.equal(calls.length, 0)
        vm.productCreationPending.value = false
        vm.productDraft.value = null
        assert.equal(vm.saveDisabled.value, false)
    })
})
test('save-and-continue clears the selected product and its draft for the next item', async () => {
    await withCreate(async ({ vm, calls, routes }) => {
        vm.form.value.name = '第一件'
        vm.selectedProduct.value = { id: 9, name: '產品' }
        await vm.submitForm(true)
        assert.equal(calls.length, 1)
        assert.equal(vm.form.value.name, '')
        assert.equal(vm.selectedProduct.value, null)
        assert.equal(vm.productDraft.value, null)
        assert.equal(vm.leaveGuard.isDirty.value, false)
        assert.deepEqual(routes, [])
    })
})
