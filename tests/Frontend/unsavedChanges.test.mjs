import test from 'node:test'
import assert from 'node:assert/strict'
import { ref } from 'vue'
import { createUnsavedChangesGuard } from '../../resources/js/utils/unsavedChanges.js'

const setup = () => {
    const draft = ref({ name: '', images: [], pendingDates: {} })
    const saving = ref(false)
    const handlers = new Set()
    const prompts = []
    let answer = false
    const guard = createUnsavedChangesGuard({ snapshot: () => draft.value, pending: () => saving.value,
        target: { addEventListener: (_, handler) => handlers.add(handler), removeEventListener: (_, handler) => handlers.delete(handler) },
        confirm: message => { prompts.push(message); return answer } })
    return { draft, saving, handlers, prompts, guard, allow: () => { answer = true } }
}

test('does not warn for an untouched form or changes reverted to the original values', () => {
    const { draft, handlers, prompts, guard } = setup()
    assert.equal(guard.confirmLeave(), true)
    assert.equal(handlers.size, 0)
    draft.value.name = '物品'
    assert.equal(handlers.size, 1)
    draft.value.name = ''
    assert.equal(guard.isDirty.value, false)
    assert.equal(handlers.size, 0)
    assert.equal(prompts.length, 0)
    guard.dispose()
})
test('cancelling navigation preserves entered fields, while confirmation allows leaving', () => {
    const { draft, guard, allow } = setup()
    draft.value.name = '未儲存物品'
    assert.equal(guard.confirmLeave(), false)
    assert.equal(draft.value.name, '未儲存物品')
    allow()
    assert.equal(guard.confirmLeave(), true)
    assert.equal(guard.isDirty.value, true)
    guard.dispose()
})
test('protects images and uncommitted date text on reload', () => {
    const { draft, handlers, guard } = setup()
    draft.value.pendingDates.purchased_at = '2026 10'
    const event = { preventDefault() { this.prevented = true } }
    for (const handler of handlers) handler(event)
    assert.equal(event.prevented, true)
    assert.equal(event.returnValue, true)
    draft.value.pendingDates = {}
    assert.equal(guard.isDirty.value, false)
    draft.value.images.push('pending-upload-1')
    assert.equal(guard.isDirty.value, true)
    guard.dispose()
})
test('marking saved removes unload protection, with fresh protection for the next item', () => {
    const { draft, handlers, guard } = setup()
    draft.value.name = '已儲存物品'
    guard.markSaved()
    assert.equal(handlers.size, 0)
    assert.equal(guard.confirmLeave(), true)
    draft.value.name = '下一件物品'
    assert.equal(handlers.size, 1)
    guard.dispose()
    assert.equal(handlers.size, 0)
    draft.value.name = '卸載後'
    assert.equal(handlers.size, 0)
})
test('makes pending submission explicit when a user chooses to leave', () => {
    const { draft, saving, prompts, guard } = setup()
    draft.value.name = '物品'
    saving.value = true
    guard.confirmLeave()
    assert.match(prompts[0], /仍可能完成建立/)
    guard.dispose()
})
