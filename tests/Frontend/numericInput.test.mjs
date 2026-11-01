import test from 'node:test'
import assert from 'node:assert/strict'
import { normalizeNumericText, createNumericInputHandlers } from '../../resources/js/utils/numericInput.js'

const createInput = value => ({
    value, selectionStart: 2, selectionEnd: 2, selectionDirection: 'none',
    setSelectionRange(start, end, direction) { this.selection = [start, end, direction] },
})

test('converts full-width numeric characters without hiding invalid content or rounding', () => {
    assert.equal(normalizeNumericText('１２．５０'), '12.50')
    assert.equal(normalizeNumericText('－２'), '-2')
    assert.equal(normalizeNumericText('　＋２０　'), ' +20 ')
    assert.equal(normalizeNumericText('１，０００元'), '1，000元')
    assert.equal(normalizeNumericText('1.005'), '1.005')
    assert.equal(normalizeNumericText(''), '')
})

test('normalizes typed and pasted text in both the input and saved draft while preserving caret', () => {
    let draft
    const handlers = createNumericInputHandlers(value => { draft = value })
    const input = createInput('１２．５')
    handlers.onInput({ target: input })
    assert.equal(input.value, '12.5')
    assert.equal(draft, '12.5')
    assert.deepEqual(input.selection, [2, 2, 'none'])
})

test('waits for IME composition to finish before replacing text or updating calculations', () => {
    const values = []
    const handlers = createNumericInputHandlers(value => values.push(value))
    const input = createInput('２')
    handlers.onCompositionStart({ target: input })
    handlers.onInput({ target: input, isComposing: true })
    handlers.onInput({ target: input, isComposing: false })
    assert.equal(input.value, '２')
    assert.deepEqual(values, [])
    handlers.onCompositionEnd({ target: input })
    assert.equal(input.value, '2')
    assert.deepEqual(values, ['2'])
    handlers.onInput({ target: input })
    assert.equal(values.at(-1), '2')
})

test('does not change calculated readonly fields', () => {
    const values = []
    const handlers = createNumericInputHandlers(value => values.push(value))
    const input = { ...createInput('２'), readOnly: true }
    handlers.onInput({ target: input })
    assert.deepEqual(values, [])
})

test('applies a quantity limit to the displayed and committed value after normalization', () => {
    let draft
    const handlers = createNumericInputHandlers(value => { draft = value }, value =>
        /^\d+$/.test(value) && Number(value) > 100 ? '100' : value)
    const input = createInput('１０００')
    handlers.onInput({ target: input })
    assert.equal(input.value, '100')
    assert.equal(draft, '100')
    input.value = '２０'
    handlers.onInput({ target: input })
    assert.equal(draft, '20')
})
