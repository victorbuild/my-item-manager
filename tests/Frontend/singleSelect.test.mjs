import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { parse, compileScript } from '@vue/compiler-sfc'
import { computed, ref, reactive } from 'vue'
const { descriptor } = parse(readFileSync(new URL('../../resources/js/Components/SingleSelect.vue', import.meta.url), 'utf8'))
const source = compileScript(descriptor, { id: 'single-select' }).content.replace(/^import .*\n/gm, '').replace('export default', 'return')
const component = new Function('computed', 'ref', 'Multiselect', source)(computed, ref, {})

test('create action is last and never writes a synthetic category ID into the selected value', () => {
    const emitted = []
    const props = reactive({ modelValue: '1', options: [{ value: '1', label: '原分類' }], createLabel: '新增分類「模型」' })
    const vm = component.setup(props, { expose() {}, emit: (...values) => emitted.push(values) })
    assert.deepEqual(vm.displayOptions.value[0], props.options[0])
    assert.equal(vm.displayOptions.value.at(-1).label, '新增分類「模型」')
    vm.selectOption(vm.CREATE_OPTION)
    assert.deepEqual(emitted, [['create']])
    assert.equal(props.modelValue, '1')
    assert.equal(vm.pickerVersion.value, 1)
})
test('ordinary single selection retains its existing behavior without enabling category creation', () => {
    const emitted = []
    const props = { modelValue: '', options: [{ value: '3', label: '分類' }], createLabel: '' }
    const vm = component.setup(props, { expose() {}, emit: (...values) => emitted.push(values) })
    assert.deepEqual(vm.displayOptions.value, props.options)
    vm.selectOption('3')
    assert.deepEqual(emitted, [['update:modelValue', '3']])
})
