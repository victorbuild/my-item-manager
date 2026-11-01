import test from 'node:test'
import assert from 'node:assert/strict'
import { parseDateText, formatDateText, getDateTextError, shiftDateTextYear } from '../../resources/js/utils/dateTextInput.js'

test('accepts explicit full dates with separators or eight digits', () => {
    for (const value of ['2026-10-27', '2026 10 27', '2026/10/27', '20261027']) {
        assert.equal(formatDateText(parseDateText(value)), '2026-10-27')
    }
    assert.equal(formatDateText(parseDateText('0001-1-1')), '0001-01-01')
})
test('rejects invalid dates without rolling them into another month', () => {
    for (const value of ['2026-02-29', '2026-04-31', '2026-13-01', '0000-01-01', '2026-10', '10/27/2026', 'abc']) {
        assert.equal(parseDateText(value), null)
        assert.ok(getDateTextError(value))
    }
    assert.equal(formatDateText(parseDateText('2024-02-29')), '2024-02-29')
})
test('checks typed dates against both inclusive bounds', () => {
    assert.equal(getDateTextError('20261027', '2026-10-27', '2026-10-27'), '')
    assert.ok(getDateTextError('20261026', '2026-10-27'))
    assert.ok(getDateTextError('20261028', undefined, '2026-10-27'))
    assert.equal(getDateTextError(''), '')
})
test('supports repeated year changes, leap days and bounds', () => {
    let value = '2026-10-01'
    for (let index = 0; index < 5; index++) value = shiftDateTextYear(value, 1)
    assert.equal(value, '2031-10-01')
    assert.equal(shiftDateTextYear('2024-02-29', 1), '2025-02-28')
    assert.equal(shiftDateTextYear('9999-01-01', 1), null)
    assert.equal(shiftDateTextYear('0001-01-01', -1), null)
    assert.equal(shiftDateTextYear('2026-10-01', 1, undefined, '2026-11-27'), null)
})
