import test from 'node:test'
import assert from 'node:assert/strict'
import { getItemSaveBlockReason } from '../../resources/js/utils/itemSaveState.js'

const valid = { name: '物品', purchasedAt: '2026-10-27', summary: { valid: true, quantity: 1 }, confirmation: '', images: [], hasDateDrafts: false, dateError: '', productPending: false }

test('allows a complete form without optional photos or price', () => {
    assert.equal(getItemSaveBlockReason(valid), '')
    assert.equal(getItemSaveBlockReason({ ...valid, images: [{ uuid: 'image', status: 'done' }] }), '')
})
test('blocks missing required values and invalid calculations', () => {
    assert.ok(getItemSaveBlockReason({ ...valid, name: '   ' }))
    assert.ok(getItemSaveBlockReason({ ...valid, purchasedAt: '' }))
    assert.equal(getItemSaveBlockReason({ ...valid, summary: { valid: false, message: '數量錯誤' } }), '數量錯誤')
    assert.ok(getItemSaveBlockReason({ ...valid, name: 'a'.repeat(256) }))
})
test('blocks every unfinished upload stage and completed rows without a server UUID', () => {
    for (const status of ['preparing', 'waiting', 'uploading', 'processing', 'error']) {
        assert.ok(getItemSaveBlockReason({ ...valid, images: [{ uuid: 'image', status }] }))
    }
    assert.ok(getItemSaveBlockReason({ ...valid, images: [{ status: 'done' }] }))
    assert.match(getItemSaveBlockReason({ ...valid, images: [{ status: 'error' }] }), /重試或移除/)
})
test('blocks uncommitted or invalid date inputs and pending product creation', () => {
    assert.ok(getItemSaveBlockReason({ ...valid, hasDateDrafts: true }))
    assert.equal(getItemSaveBlockReason({ ...valid, dateError: '日期超出範圍' }), '日期超出範圍')
    assert.ok(getItemSaveBlockReason({ ...valid, productPending: true }))
})
test('requires bulk confirmation and becomes ready once the exact count is entered', () => {
    const bulk = { ...valid, summary: { valid: true, quantity: 10 } }
    assert.ok(getItemSaveBlockReason(bulk))
    assert.equal(getItemSaveBlockReason({ ...bulk, confirmation: '10' }), '')
})
