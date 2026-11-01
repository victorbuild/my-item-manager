import test from 'node:test'
import assert from 'node:assert/strict'
import { effectScope, ref } from 'vue'
import { useItemPricing } from '../../resources/js/composables/useItemPricing.js'

const withPricing = callback => {
    const scope = effectScope()
    scope.run(() => {
        const form = ref({ quantity: 5, price: '' })
        callback({ form, ...useItemPricing(form, 100) })
    })
    scope.stop()
}

test('switching editable amount preserves the current equation in both directions', () => withPricing(({ form, creationSummary, totalAmount, switchPriceMode, setPriceAmount }) => {
    setPriceAmount('20')
    switchPriceMode('total')
    assert.equal(totalAmount.value, '100.00')
    assert.equal(creationSummary.value.unitPrice, 20)
    switchPriceMode('unit')
    assert.equal(form.value.price, '20.00')
    assert.equal(creationSummary.value.totalPrice, 100)
}))
test('switching without edits preserves the original total despite per-item rounding', () => withPricing(({ form, creationSummary, displayedTotal, totalAmount, switchPriceMode, setPriceAmount }) => {
    form.value.quantity = 3
    switchPriceMode('total')
    setPriceAmount('100')
    for (let count = 0; count < 3; count++) { switchPriceMode('unit'); switchPriceMode('total') }
    assert.equal(form.value.price, '33.33')
    assert.equal(totalAmount.value, '100.00')
    assert.equal(displayedTotal.value, 100)
    assert.equal(creationSummary.value.totalPrice, 99.99)
    assert.equal(creationSummary.value.rounded, true)
}))
test('changing quantity recalculates using the currently editable amount', () => withPricing(({ form, creationSummary, switchPriceMode, setPriceAmount }) => {
    setPriceAmount('20')
    switchPriceMode('total')
    form.value.quantity = 4
    assert.equal(creationSummary.value.unitPrice, 25)
    assert.equal(creationSummary.value.totalPrice, 100)
    switchPriceMode('unit')
    form.value.quantity = 2
    assert.equal(creationSummary.value.unitPrice, 25)
    assert.equal(creationSummary.value.totalPrice, 50)
}))
test('editing the new active amount replaces the previous calculation basis', () => withPricing(({ form, creationSummary, switchPriceMode, setPriceAmount }) => {
    form.value.quantity = 3
    switchPriceMode('total')
    setPriceAmount('100')
    switchPriceMode('unit')
    setPriceAmount('40')
    assert.equal(creationSummary.value.totalPrice, 120)
    assert.equal(creationSummary.value.rounded, false)
}))
test('preserves zero and empty amounts across switches and resets the next item', () => withPricing(({ form, creationSummary, totalAmount, switchPriceMode, setPriceAmount, resetPricing }) => {
    switchPriceMode('total')
    assert.equal(totalAmount.value, '')
    setPriceAmount('0')
    switchPriceMode('unit')
    assert.equal(form.value.price, '0.00')
    assert.equal(creationSummary.value.totalPrice, 0)
    form.value = { quantity: 1, price: '' }
    resetPricing()
    assert.equal(creationSummary.value.unitPrice, null)
    assert.equal(totalAmount.value, '')
}))
test('does not overwrite invalid drafts when switching modes', () => withPricing(({ form, creationSummary, switchPriceMode, setPriceAmount }) => {
    setPriceAmount('1.005')
    switchPriceMode('total')
    assert.equal(form.value.price, '1.005')
    assert.equal(creationSummary.value.valid, false)
    setPriceAmount('100')
    assert.equal(creationSummary.value.valid, true)
}))
