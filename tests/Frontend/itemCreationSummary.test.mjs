import test from 'node:test'
import assert from 'node:assert/strict'
import { getItemCreationSummary, isQuantityConfirmed } from '../../resources/js/utils/itemCreationSummary.js'

const summary = overrides => getItemCreationSummary({ quantity: 1, maxQuantity: 100, price: '', totalAmount: '', priceMode: 'unit', ...overrides })

test('uses integer cents for quantity and unit price totals', () => {
    assert.equal(summary({ quantity: 3, price: '0.10' }).totalPrice, 0.3)
    assert.equal(summary({ quantity: 5, price: '20' }).totalPrice, 100)
})
test('distinguishes missing and zero prices', () => {
    assert.equal(summary({}).unitPrice, null)
    assert.equal(summary({ price: '0' }).unitPrice, 0)
})
test('reports rounded totals instead of implying an exact split', () => {
    const result = summary({ quantity: 3, priceMode: 'total', totalAmount: '100' })
    assert.equal(result.unitPrice, 33.33)
    assert.equal(result.totalPrice, 99.99)
    assert.equal(result.enteredTotal, 100)
    assert.equal(result.rounded, true)
    assert.equal(summary({ quantity: 5, priceMode: 'total', totalAmount: '100' }).rounded, false)
})
test('rejects excessive, fractional and invalid quantities', () => {
    for (const quantity of ['', 0, -1, 1.5, 101, 1000, NaN, Infinity]) assert.equal(summary({ quantity }).valid, false)
    assert.equal(summary({ quantity: 100 }).valid, true)
    assert.equal(summary({ quantity: 6, maxQuantity: 5 }).valid, false)
})
test('rejects invalid prices and the backend unit price limit', () => {
    for (const price of [-1, '1.005', 'abc', Infinity, '100000000']) assert.equal(summary({ price }).valid, false)
    assert.equal(summary({ price: '99999999.99' }).valid, true)
})
test('requires exact quantity confirmation for bulk creation', () => {
    assert.equal(isQuantityConfirmed(9, ''), true)
    assert.equal(isQuantityConfirmed(10, ''), false)
    assert.equal(isQuantityConfirmed(10, '10'), true)
    assert.equal(isQuantityConfirmed(100, '10'), false)
})
