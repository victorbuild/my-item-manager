import test from 'node:test'
import assert from 'node:assert/strict'
import { createItemEditForm, createItemEditPayload, getItemEditImagePayload, getItemEditErrors } from '../../resources/js/utils/itemEditState.js'

const item = { name: '衣服', price: 0, purchased_at: '2026-10-27', received_at: '2026-10-27', product: { id: 9 }, notes: '不應清除', discard_note: '原備註', serial_number: 'original' }

test('editing preserves zero price and never defaults missing saved dates to today', () => {
    const form = createItemEditForm(item)
    assert.equal(form.price, '0')
    assert.equal(form.used_at, '')
    assert.equal(createItemEditForm({}).purchased_at, '')
    assert.equal(createItemEditPayload(form, [], []).price, 0)
    assert.equal(createItemEditPayload({ ...form, price: '' }, [], []).price, null)
})
test('sends existing, new and removed images once, preserving visible order and shared originals', () => {
    const originals = [{ uuid: 'A' }, { uuid: 'B' }, { uuid: 'C' }]
    const photos = [{ uuid: 'b', status: 'done' }, { uuid: 'NEW', status: 'done' }, { uuid: 'B', status: 'done' }]
    assert.deepEqual(getItemEditImagePayload(photos, originals), [
        { uuid: 'b', status: 'original' }, { uuid: 'NEW', status: 'new' },
        { uuid: 'A', status: 'removed' }, { uuid: 'C', status: 'removed' },
    ])
    assert.equal(originals.length, 3)
})
test('removing every original still sends removal instructions and restoring reuses the original', () => {
    const originals = [{ uuid: 'A' }, { uuid: 'B' }]
    assert.deepEqual(getItemEditImagePayload([], originals), [{ uuid: 'A', status: 'removed' }, { uuid: 'B', status: 'removed' }])
    assert.deepEqual(getItemEditImagePayload([{ uuid: 'A', status: 'done' }], originals), [{ uuid: 'A', status: 'original' }, { uuid: 'B', status: 'removed' }])
})
test('only sends fields supported by the edit API, leaving unrelated saved fields untouched', () => {
    const payload = createItemEditPayload(createItemEditForm(item), [], [])
    for (const key of ['product_id', 'category_id', 'quantity', 'notes', 'discard_note', 'serial_number', 'is_discarded']) assert.equal(key in payload, false)
    assert.equal(item.notes, '不應清除')
})
test('validates edited amounts, missing required fields and all date drafts without constraining expiry order', () => {
    const form = createItemEditForm(item)
    assert.deepEqual(getItemEditErrors({ ...form, expiration_date: '2026-01-01' }), {})
    assert.ok(getItemEditErrors({ ...form, name: '  ' }).name)
    assert.ok(getItemEditErrors({ ...form, purchased_at: '' }).purchased_at)
    assert.ok(getItemEditErrors({ ...form, price: '1.005' }).price)
    assert.ok(getItemEditErrors({ ...form, used_at: '2026-10-26' }).used_at)
    assert.ok(getItemEditErrors(form, { received_at: '2026 10' }).received_at)
    assert.equal(getItemEditErrors(form, { manufactureDate: 'draft' }).received_at, undefined)
})

test('relinking and explicitly unlinking only change the selected product ID', () => {
    const form = createItemEditForm(item)
    const linked = createItemEditPayload(form, [], [], { id: 25, name: '產品名稱' })
    assert.equal(linked.product_id, 25)
    assert.equal(linked.name, item.name)
    assert.equal(linked.price, 0)
    assert.equal(createItemEditPayload(form, [], [], null).product_id, null)
    assert.equal('product_id' in createItemEditPayload(form, [], []), false)
})
