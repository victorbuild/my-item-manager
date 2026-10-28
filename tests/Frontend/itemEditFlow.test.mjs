import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { parse, compileScript } from '@vue/compiler-sfc'
import { computed, ref, reactive, nextTick, watch, effectScope } from 'vue'
import dayjs from 'dayjs'
import { createUnsavedChangesGuard } from '../../resources/js/utils/unsavedChanges.js'
import { createNumericInputHandlers } from '../../resources/js/utils/numericInput.js'
import { usePurchaseArrivalLink } from '../../resources/js/composables/usePurchaseArrivalLink.js'
import { getDateMin, getDateMax } from '../../resources/js/utils/itemDates.js'
import { formatDateText, parseDateText } from '../../resources/js/utils/dateTextInput.js'
import { imageUuidKey } from '../../resources/js/utils/itemImageSelection.js'
import { createItemEditForm, createItemEditPayload, getItemEditErrors, getItemEditPrice } from '../../resources/js/utils/itemEditState.js'
import { preventImplicitSubmit } from '../../resources/js/utils/formKeyboard.js'

const { descriptor } = parse(readFileSync(new URL('../../resources/js/Pages/Items/Edit.vue', import.meta.url), 'utf8'))
const source = compileScript(descriptor, { id: 'edit-flow-test' }).content.replace(/^import .*\n/gm, '').replace('export default', 'return')
const saved = () => ({ short_id: 'item-a', name: '原物品', price: 0, purchased_at: '2026-10-27', received_at: '2026-10-27', images: [{ uuid: 'a', thumb_url: '/a.jpg' }, { uuid: 'b', thumb_url: '/b.jpg' }], notes: '保持原紀錄' })
const tick = () => new Promise(resolve => setImmediate(resolve))
const withEditor = async (verify, { put, get } = {}) => {
    const previousWindow = globalThis.window
    const handlers = new Map(), prompts = [], routes = [], requests = [], cleanups = []
    globalThis.window = { addEventListener: (name, handler) => handlers.set(name, handler), removeEventListener: name => handlers.delete(name), confirm: text => { prompts.push(text); return false } }
    const route = reactive({ params: { id: 'item-a' } })
    const gates = []
    const bindings = {
        computed, nextTick, onBeforeUnmount: fn => cleanups.push(fn), ref, watch,
        onBeforeRouteLeave: fn => gates.push(fn), onBeforeRouteUpdate: fn => gates.push(fn),
        useRoute: () => route, useRouter: () => ({ push: async path => { routes.push(path) } }), dayjs,
        axios: {
            get: get || (async () => ({ data: { data: saved() } })),
            put: async (url, payload) => { requests.push({ url, payload }); return put ? put(url, payload) : { data: { data: { ...saved(), ...payload, product: payload.product_id == null ? null : { id: payload.product_id, name: '儲存後產品' }, images: payload.images.filter(image => image.status !== 'removed') } } } },
        },
        AppButton: {}, AppIcon: {}, DateInput: {}, ItemPhotos: {}, ItemNameInput: {}, ProductLinkSelect: {}, ProductSummary: {}, usePurchaseArrivalLink,
        createNumericInputHandlers, createUnsavedChangesGuard, preventImplicitSubmit,
        formatDateText, parseDateText, getDateMin, getDateMax, imageUuidKey,
        createItemEditForm, createItemEditPayload, getItemEditErrors, getItemEditPrice,
    }
    const component = new Function(...Object.keys(bindings), source)(...Object.values(bindings))
    const scope = effectScope()
    let vm
    scope.run(() => { vm = component.setup({}, { expose() {} }) })
    try {
        await tick()
        vm.itemForm.value = { reportValidity: () => true, querySelector: () => null }
        await verify({ vm, handlers, gates, prompts, requests, routes, route })
    } finally {
        cleanups.forEach(fn => fn())
        scope.stop()
        globalThis.window = previousWindow
    }
}

test('loading saved data establishes a clean baseline, preserves zero and protects subsequent edits', async () => {
    await withEditor(async ({ vm, gates, handlers, prompts }) => {
        assert.equal(vm.isLoading.value, false)
        assert.equal(vm.form.value.price, '0')
        assert.equal(vm.leaveGuard.isDirty.value, false)
        assert.equal(handlers.size, 0)
        assert.equal(vm.saveDisabled.value, true)
        vm.form.value.name = '修改後'
        assert.equal(vm.saveDisabled.value, false)
        assert.equal(gates[0](), false)
        assert.equal(gates[1](), false)
        assert.equal(prompts.length, 2)
        assert.equal(handlers.size, 1)
    })
})
test('saving after removing all photos sends explicit removal and leaves unrelated fields out', async () => {
    await withEditor(async ({ vm, requests, routes }) => {
        vm.photos.value = []
        vm.form.value.name = '變更'
        await vm.submitForm(false)
        assert.equal(requests[0].url, '/api/items/item-a')
        assert.deepEqual(requests[0].payload.images, [{ uuid: 'a', status: 'removed' }, { uuid: 'b', status: 'removed' }])
        assert.equal(requests[0].payload.price, 0)
        assert.equal('notes' in requests[0].payload, false)
        assert.deepEqual(routes, ['/items'])
        assert.equal(vm.leaveGuard.isDirty.value, false)
    })
})
test('validation failure retains draft text, photo order and server errors for correction', async () => {
    await withEditor(async ({ vm, routes }) => {
        vm.form.value.name = '保留輸入'
        vm.photos.value.reverse()
        await vm.submitForm(true)
        assert.equal(vm.form.value.name, '保留輸入')
        assert.deepEqual(vm.photos.value.map(photo => photo.uuid), ['b', 'a'])
        assert.equal(vm.leaveGuard.isDirty.value, true)
        assert.equal(vm.fieldError('name'), '名稱錯誤')
        assert.deepEqual(routes, [])
        vm.form.value.name = '修正名稱'
        assert.equal(vm.fieldError('name'), '')
    }, { put: async () => { throw { response: { status: 422, data: { errors: { name: ['名稱錯誤'] } } } } } })
})
test('continue editing resets the baseline and retains the success message after reactive updates', async () => {
    await withEditor(async ({ vm, routes }) => {
        vm.form.value.name = '已儲存新名稱'
        await vm.submitForm(true)
        await tick()
        assert.equal(vm.form.value.name, '已儲存新名稱')
        assert.equal(vm.saveNotice.value, '變更已儲存。')
        assert.equal(vm.leaveGuard.isDirty.value, false)
        assert.equal(vm.saveDisabled.value, true)
        assert.deepEqual(routes, [])
    })
})
test('unfinished uploads, invalid dates and invalid amounts block both submission routes', async () => {
    await withEditor(async ({ vm, requests }) => {
        vm.form.value.name = '修改'
        vm.photos.value.push({ id: 'upload', status: 'processing' })
        await vm.submitForm()
        assert.equal(requests.length, 0)
        vm.photos.value.pop()
        vm.setDateDraft('received_at', '2026 10')
        await vm.submitForm(true)
        assert.equal(requests.length, 0)
        vm.setDateDraft('received_at', null)
        vm.form.value.price = 'invalid'
        await vm.submitForm()
        assert.equal(requests.length, 0)
    })
})
test('failed loading stays clean and can be retried', async () => {
    let fail = true
    await withEditor(async ({ vm, handlers }) => {
        assert.ok(vm.loadError.value)
        assert.equal(vm.loaded.value, false)
        assert.equal(handlers.size, 0)
        fail = false
        await vm.loadItem()
        assert.equal(vm.loaded.value, true)
        assert.equal(vm.loadError.value, '')
        assert.equal(vm.leaveGuard.isDirty.value, false)
    }, { get: async () => { if (fail) throw new Error('offline'); return { data: { data: saved() } } } })
})

test('a slow response for the previous route cannot overwrite the newly loaded item', async () => {
    const pending = new Map()
    await withEditor(async ({ vm, route }) => {
        route.params.id = 'item-b'
        await tick()
        pending.get('/api/items/item-b')({ data: { data: { ...saved(), short_id: 'item-b', name: '第二件' } } })
        await tick()
        pending.get('/api/items/item-a')({ data: { data: saved() } })
        await tick()
        assert.equal(vm.form.value.name, '第二件')
        assert.equal(vm.leaveGuard.isDirty.value, false)
    }, { get: url => new Promise(resolve => pending.set(url, resolve)) })
})

test('an update that completes after leaving its route cannot overwrite another item or navigate away', async () => {
    let release
    await withEditor(async ({ vm, route, routes }) => {
        vm.form.value.name = '第一件修改'
        const saving = vm.submitForm(true)
        route.params.id = 'item-b'
        await tick()
        release({ data: { data: { ...saved(), name: '舊請求結果' } } })
        await saving
        assert.notEqual(vm.form.value.name, '舊請求結果')
        assert.deepEqual(routes, [])
        assert.equal(vm.isSubmitting.value, false)
    }, { put: () => new Promise(resolve => { release = resolve }) })
})

test('changing the selected product is tracked as an unsaved edit and sent to the API', async () => {
    await withEditor(async ({ vm, requests }) => {
        vm.selectedProduct.value = { id: 25, name: '另一產品', short_id: 'product-b' }
        assert.equal(vm.leaveGuard.isDirty.value, true)
        assert.equal(vm.saveDisabled.value, false)
        assert.equal(vm.form.value.name, '原物品')
        await vm.submitForm(true)
        assert.equal(requests[0].payload.product_id, 25)
        assert.equal(requests[0].payload.name, '原物品')
        assert.equal(vm.selectedProduct.value.id, 25)
        assert.equal(vm.leaveGuard.isDirty.value, false)
    })
})
