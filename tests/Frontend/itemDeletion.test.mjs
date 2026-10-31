import test from 'node:test'
import assert from 'node:assert/strict'
import { confirmItemDeletion } from '../../resources/js/utils/itemDeletion.js'
import { dialogClasses } from '../../resources/js/utils/dialogClasses.js'

test('item deletion retains the complete theme when overriding the destructive button', async () => {
    const calls = []
    const dialog = { isLoading: () => false, fire: async options => {
        for (const key of ['popup', 'container', 'actions', 'cancelButton', 'title']) assert.equal(options.customClass[key], dialogClasses[key])
        assert.match(options.customClass.confirmButton, /app-dialog-confirm-danger/)
        assert.ok(options.text.includes('物品名稱'))
        return { isConfirmed: true, value: await options.preConfirm() }
    } }
    assert.equal(await confirmItemDeletion({ delete: async url => calls.push(url) }, dialog, { name: '物品名稱', short_id: 'a' }), true)
    assert.deepEqual(calls, ['/api/items/a'])
})
test('a confirmation from an item that is no longer active cannot delete it', async () => {
    let calls = 0
    const dialog = { fire: async options => ({ isConfirmed: true, value: await options.preConfirm() }) }
    assert.equal(await confirmItemDeletion({ delete: async () => { calls++ } }, dialog, { name: '舊物品', short_id: 'old' }, () => false), false)
    assert.equal(calls, 0)
})
