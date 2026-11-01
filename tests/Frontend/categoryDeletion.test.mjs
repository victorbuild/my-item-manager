import test from 'node:test'
import assert from 'node:assert/strict'
import { confirmCategoryDeletion } from '../../resources/js/utils/categoryDeletion.js'

const product = { id: '123', name: '<img src=x onerror=alert(1)>' }

test('cancelling the category confirmation never sends a delete request', async () => {
    let calls = 0
    const api = { delete: async () => { calls++ } }
    const dialog = { fire: async options => {
        assert.ok(options.text.includes(product.name))
        assert.equal(options.html, undefined)
        assert.equal(options.focusCancel, true)
        assert.equal(options.customClass.popup, 'app-dialog')
        assert.equal(options.customClass.actions, 'app-dialog-actions')
        assert.equal(options.customClass.cancelButton, 'app-dialog-cancel')
        return { isConfirmed: false }
    } }
    assert.equal(await confirmCategoryDeletion(api, dialog, product), false)
    assert.equal(calls, 0)
})

test('confirmed deletion targets the existing category endpoint and blocks dismissal while saving', async () => {
    const calls = []
    const dialog = { isLoading: () => true, fire: async options => {
        assert.equal(options.allowOutsideClick(), false)
        assert.equal(options.allowEscapeKey(), false)
        return { isConfirmed: true, value: await options.preConfirm() }
    } }
    assert.equal(await confirmCategoryDeletion({ delete: async url => calls.push(url) }, dialog, product), true)
    assert.deepEqual(calls, ['/api/categories/123'])
})

test('a server rejection keeps the confirmation open with a safe retry message', async () => {
    let message
    const dialog = { showValidationMessage: text => { message = text }, fire: async options => {
        const value = await options.preConfirm()
        assert.equal(value, false)
        return { isConfirmed: false }
    } }
    const api = { delete: async () => { throw { response: { data: { message: '<script>unsafe</script>' } } } } }
    assert.equal(await confirmCategoryDeletion(api, dialog, product), false)
    assert.match(message, /刪除失敗/)
    assert.ok(!message.includes('<script>'))
})
