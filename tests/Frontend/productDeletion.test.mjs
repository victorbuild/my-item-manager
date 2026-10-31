import test from 'node:test'
import assert from 'node:assert/strict'
import { confirmProductDeletion } from '../../resources/js/utils/productDeletion.js'

const product = { short_id: 'product-123', name: '<img src=x onerror=alert(1)>' }

test('cancelling the product confirmation never sends a delete request', async () => {
    let calls = 0
    const api = { delete: async () => { calls++ } }
    const dialog = { fire: async options => {
        assert.ok(options.text.includes(product.name))
        assert.equal(options.html, undefined)
        assert.equal(options.focusCancel, true)
        return { isConfirmed: false }
    } }
    assert.equal(await confirmProductDeletion(api, dialog, product), false)
    assert.equal(calls, 0)
})

test('confirmed deletion targets the existing product endpoint and blocks dismissal while saving', async () => {
    const calls = []
    const dialog = { isLoading: () => true, fire: async options => {
        assert.equal(options.allowOutsideClick(), false)
        assert.equal(options.allowEscapeKey(), false)
        return { isConfirmed: true, value: await options.preConfirm() }
    } }
    assert.equal(await confirmProductDeletion({ delete: async url => calls.push(url) }, dialog, product), true)
    assert.deepEqual(calls, ['/api/products/product-123'])
})

test('a server rejection keeps the confirmation open with a safe retry message', async () => {
    let message
    const dialog = { showValidationMessage: text => { message = text }, fire: async options => {
        const value = await options.preConfirm()
        assert.equal(value, false)
        return { isConfirmed: false }
    } }
    const api = { delete: async () => { throw { response: { data: { message: '<script>unsafe</script>' } } } } }
    assert.equal(await confirmProductDeletion(api, dialog, product), false)
    assert.match(message, /刪除失敗/)
    assert.ok(!message.includes('<script>'))
})
