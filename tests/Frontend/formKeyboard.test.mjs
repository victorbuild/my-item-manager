import test from 'node:test'
import assert from 'node:assert/strict'
import { preventImplicitSubmit } from '../../resources/js/utils/formKeyboard.js'

const keyEvent = ({ tagName = 'INPUT', combobox = false, key = 'Enter', isComposing = false } = {}) => ({
    key, isComposing,
    target: { tagName, closest: selector => selector === '.multiselect[role="combobox"]' && combobox ? {} : null },
    preventDefault() { this.defaultPrevented = true },
})

test('allows the product and category widgets to receive their Enter keypress', () => {
    for (const field of ['product', 'category']) {
        const event = keyEvent({ combobox: true })
        preventImplicitSubmit(event)
        assert.notEqual(event.defaultPrevented, true, `${field} must retain its keypress selection`)
    }
})
test('still prevents implicit submission from ordinary inputs', () => {
    const event = keyEvent()
    preventImplicitSubmit(event)
    assert.equal(event.defaultPrevented, true)
})
test('preserves textarea line breaks, button activation, IME and arrow navigation', () => {
    for (const options of [{ tagName: 'TEXTAREA' }, { tagName: 'BUTTON' }, { isComposing: true }, { key: 'ArrowDown', combobox: true }]) {
        const event = keyEvent(options)
        preventImplicitSubmit(event)
        assert.notEqual(event.defaultPrevented, true)
    }
})
