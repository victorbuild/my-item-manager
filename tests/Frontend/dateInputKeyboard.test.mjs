import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { parse, compileScript } from '@vue/compiler-sfc'
import { computed, ref, watch, watchPostEffect, nextTick, reactive, effectScope } from 'vue'
import { parseDateText, formatDateText, getDateTextError, shiftDateTextYear } from '../../resources/js/utils/dateTextInput.js'

const { descriptor } = parse(readFileSync(new URL('../../resources/js/Components/DateInput.vue', import.meta.url), 'utf8'))
const source = compileScript(descriptor, { id: 'date-keyboard-test' }).content
    .replace(/^import .*\n/gm, '')
    .replace('export default', 'return')
const bindings = { computed, ref, watch, watchPostEffect, nextTick, useId: () => 'test', parseDateText, formatDateText, getDateTextError, shiftDateTextYear, VueDatePicker: {}, YearPicker: {}, zhTW: {} }
const component = new Function(...Object.keys(bindings), source)(...Object.values(bindings))

const withDateInput = async (value, verify) => {
    const scope = effectScope()
    const props = reactive({ modelValue: '2026-10-01', min: undefined, max: '2026-11-27', disabled: false, invalid: false })
    const emitted = []
    let vm
    scope.run(() => {
        vm = component.setup(props, { expose: () => {}, emit: (event, value) => emitted.push([event, value]) })
    })
    let closed = 0
    vm.datePicker.value = { closeMenu: () => closed++ }
    const input = { tagName: 'INPUT', value, setCustomValidity(message) { this.error = message }, reportValidity() { this.reported = true } }
    const event = { target: input, key: 'Enter', preventDefault() { this.prevented = true }, stopPropagation() { this.stopped = true } }
    try {
        await vm.onYearKey(event)
        verify({ event, input, emitted, closed, props })
    } finally { scope.stop() }
}

test('Enter applies the date and cancels implicit form submission', async () => {
    await withDateInput('2026 10 27', ({ event, input, emitted, closed }) => {
        assert.equal(event.prevented, true)
        assert.equal(event.stopped, true)
        assert.equal(input.value, '2026-10-27')
        assert.deepEqual(emitted, [['draft-change', null], ['update:modelValue', '2026-10-27']])
        assert.equal(closed, 1)
    })
})
test('Enter on an invalid date stays in the field and never emits a stored value', async () => {
    for (const value of ['2026-02-30', '2027-01-01']) {
        await withDateInput(value, ({ event, input, emitted, closed, props }) => {
            assert.equal(event.prevented, true)
            assert.equal(input.reported, true)
            assert.ok(input.error)
            assert.deepEqual(emitted, [])
            assert.equal(closed, 0)
            assert.equal(props.modelValue, '2026-10-01')
        })
    }
})
