import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { parse, compileScript } from '@vue/compiler-sfc'
import { computed, ref, watch, nextTick, reactive, effectScope } from 'vue'
import { getDateMin, getDateMax, getTodayDate } from '../../resources/js/utils/itemDates.js'
import { getDateTextError } from '../../resources/js/utils/dateTextInput.js'
import { createUnsavedChangesGuard } from '../../resources/js/utils/unsavedChanges.js'
import { ITEM_SHOW_DATES, getItemNextAction, getItemShowMetrics } from '../../resources/js/utils/itemShowState.js'
const { descriptor } = parse(readFileSync(new URL('../../resources/js/Pages/Items/Show.vue', import.meta.url), 'utf8'))
const source = compileScript(descriptor, { id: 'show-flow' }).content.replace(/^import .*\n/gm, '').replace('export default', 'return')
const tick = () => new Promise(resolve => setImmediate(resolve))
const fixture = () => ({ short_id: 'a', name: '物品', purchased_at: '2026-10-01', received_at: '2026-10-02', used_at: null, discarded_at: null, discard_note: '回憶', price: 10, images: [{ uuid: 'image-a' }] })
const run = async (verify, patch) => {
    const oldWindow = globalThis.window, cleanups = [], gates = [], requests = []
    globalThis.window = { addEventListener() {}, removeEventListener() {}, confirm: () => false }
    const route = reactive({ params: { id: 'a' } })
    const bindings = { computed, ref, watch, nextTick, defineAsyncComponent: () => ({}), onBeforeUnmount: fn => cleanups.push(fn), onBeforeRouteLeave: fn => gates.push(fn), onBeforeRouteUpdate: fn => gates.push(fn), useRoute: () => route, useRouter: () => ({ push() {} }), AppButton: {}, AppIcon: {}, ItemStatusBadge: {}, ItemStatusScene: {}, RekaModal: {}, DateInput: {}, getDateMin, getDateMax, getTodayDate, getDateTextError, createUnsavedChangesGuard, ITEM_SHOW_DATES, getItemNextAction, getItemShowMetrics,
        axios: { isCancel: () => false, get: async () => ({ data: { data: fixture() } }), delete: async url => { requests.push({ url, method: 'DELETE' }) }, patch: async (url, payload) => { requests.push({ url, payload }); return patch ? patch(url, payload) : { data: { data: { ...fixture(), ...payload } } } } } }
    const component = new Function(...Object.keys(bindings), source)(...Object.values(bindings))
    const scope = effectScope()
    let vm
    scope.run(() => { vm = component.setup({}, { expose() {} }) })
    try { await tick(); vm.editorForm.value = { reportValidity: () => true, querySelector: () => null }; await verify({ vm, gates, requests, route }) }
    finally { cleanups.forEach(fn => fn()); scope.stop(); globalThis.window = oldWindow }
}
test('viewing never writes; starting use confirms a visible date and patches only that field', async () => {
    await run(async ({ vm, requests }) => {
        assert.equal(requests.length, 0)
        vm.openEditor('used_at', true)
        assert.equal(vm.draftDate.value, getTodayDate())
        assert.equal(requests.length, 0)
        vm.draftDate.value = '2026-10-03'
        await vm.saveEditor()
        assert.deepEqual(requests[0].payload, { used_at: '2026-10-03' })
        assert.equal(vm.editor.value, null)
        assert.equal(vm.leaveGuard.isDirty.value, false)
    })
})
test('recording disposal patches only the date and preserves the existing independent note', async () => {
    await run(async ({ vm, requests }) => {
        vm.openEditor('discarded_at', true)
        vm.draftDate.value = '2026-10-03'
        vm.draftNote.value = '捨不得丟，留下紀錄'
        await vm.saveEditor()
        assert.deepEqual(requests[0].payload, { discarded_at: '2026-10-03' })
        assert.equal(vm.item.value.discard_note, '回憶')
    })
})
test('memory can be saved independently and a cleared event is explicitly null', async () => {
    await run(async ({ vm, requests }) => {
        vm.openEditor('note'); vm.draftNote.value = '還在使用的回憶'
        await vm.saveEditor()
        assert.deepEqual(requests[0].payload, { discard_note: '還在使用的回憶' })
        vm.openEditor('received_at'); vm.draftDate.value = ''
        await vm.saveEditor()
        assert.deepEqual(requests[1].payload, { received_at: null })
    })
})
test('failed save keeps input and leave/cancel guards protect a draft', async () => {
    await run(async ({ vm, gates }) => {
        vm.openEditor('note'); vm.draftNote.value = '不要清掉'
        await vm.saveEditor()
        assert.equal(vm.draftNote.value, '不要清掉')
        assert.equal(vm.editor.value, 'note')
        assert.ok(vm.submitError.value)
        const leave = gates[0]()
        assert.equal(vm.discardConfirmationOpen.value, true)
        vm.finishDiscardConfirmation(false)
        assert.equal(await leave, false)
        const close = vm.closeEditor()
        vm.finishDiscardConfirmation(false)
        await close
        assert.equal(vm.editor.value, 'note')
    }, async () => { throw new Error('offline') })
})
test('pending save is not duplicated and late responses do not replace a different routed item', async () => {
    let resolve
    await run(async ({ vm, requests, route }) => {
        vm.openEditor('note'); vm.draftNote.value = '新備註'
        const pending = vm.saveEditor()
        await vm.saveEditor()
        assert.equal(requests.length, 1)
        route.params.id = 'b'; await tick()
        resolve({ data: { data: { ...fixture(), name: '過期回應' } } })
        await pending
        assert.equal(vm.item.value.name, '物品')
    }, () => new Promise(done => { resolve = done }))
})

test('memories use the existing notes field and preserve independent disposal remarks', async () => {
    await run(async ({ vm, requests }) => {
        vm.openEditor('notes')
        assert.equal(vm.editorTitle.value, '心得與回憶')
        assert.equal(vm.isNoteEditor.value, true)
        assert.equal(vm.draftDate.value, '')
        vm.draftNote.value = '喜歡哥吉拉，買來收藏，還想繼續留著'
        await vm.saveEditor()
        assert.deepEqual(requests[0].payload, { notes: '喜歡哥吉拉，買來收藏，還想繼續留著' })
        assert.equal(vm.item.value.discard_note, '回憶')
        assert.equal(vm.item.value.discarded_at, null)
        vm.openEditor('notes')
        assert.equal(vm.draftNote.value, '喜歡哥吉拉，買來收藏，還想繼續留著')
        vm.draftNote.value = ''
        await vm.saveEditor()
        assert.deepEqual(requests[1].payload, { notes: '' })
    })
})

test('quick arrival defaulting to today can be saved without changing the prefilled date', async () => {
    await run(async ({ vm, requests }) => {
        vm.item.value.received_at = null
        vm.openEditor('received_at', true)
        assert.equal(vm.draftDate.value, getTodayDate())
        assert.equal(vm.draftChanged.value, true)
        assert.equal(vm.leaveGuard.isDirty.value, false)
        await vm.saveEditor()
        assert.deepEqual(requests[0].payload, { received_at: getTodayDate() })
    })
})
test('an existing arrival date equal to purchase can be saved and an unchanged date remains unchanged', async () => {
    await run(async ({ vm, requests }) => {
        vm.openEditor('received_at')
        assert.equal(vm.draftChanged.value, false)
        vm.draftDate.value = vm.item.value.purchased_at
        assert.equal(vm.dateError.value, '')
        await vm.saveEditor()
        assert.deepEqual(requests[0].payload, { received_at: '2026-10-01' })
    })
})

test('deleting requires the dedicated confirmation dialog and never occurs merely by opening it', async () => {
    await run(async ({ vm, requests }) => {
        await vm.deleteItem()
        assert.equal(requests.length, 0)
        vm.deleteOpen.value = true
        assert.equal(requests.length, 0)
        await vm.deleteItem()
        assert.deepEqual(requests[0], { url: '/api/items/a', method: 'DELETE' })
    })
})
test('confirming discard closes the editor without saving and repeated close requests share one decision', async () => {
    await run(async ({ vm, requests }) => {
        vm.openEditor('notes'); vm.draftNote.value = '未儲存'
        const first = vm.closeEditor()
        const second = vm.closeEditor()
        assert.equal(vm.discardConfirmationOpen.value, true)
        vm.finishDiscardConfirmation(true)
        await Promise.all([first, second])
        assert.equal(vm.editor.value, null)
        assert.equal(requests.length, 0)
    })
})

test('conflicting purchase and arrival drafts never save or rewrite stored dates', async () => {
    await run(async ({ vm, requests }) => {
        vm.openEditor('purchased_at')
        assert.equal(vm.dateMax.value, '2026-10-02')
        vm.draftDate.value = '2026-10-30'
        assert.ok(vm.dateError.value)
        await vm.saveEditor()
        assert.equal(requests.length, 0)
        assert.equal(vm.item.value.purchased_at, '2026-10-01')
        assert.equal(vm.item.value.received_at, '2026-10-02')
    })
})
test('uncommitted conflicting date text blocks saving even before the picker applies it', async () => {
    await run(async ({ vm, requests }) => {
        vm.openEditor('purchased_at')
        vm.typedDate.value = '2026-10-30'
        assert.ok(vm.dateError.value)
        await vm.saveEditor()
        assert.equal(requests.length, 0)
        assert.equal(vm.item.value.received_at, '2026-10-02')
    })
})
