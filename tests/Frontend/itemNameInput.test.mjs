import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { parse, compileScript } from '@vue/compiler-sfc'
import { computed, ref, reactive, watchPostEffect, effectScope, nextTick } from 'vue'

const { descriptor } = parse(readFileSync(new URL('../../resources/js/Components/ItemNameInput.vue', import.meta.url), 'utf8'))
const source = compileScript(descriptor, { id: 'name-input-test' }).content.replace(/^import .*\n/gm, '').replace('export default', 'return')
const setup = () => {
    let mounted, cleanup, resized, disconnected = false
    const previousObserver = globalThis.ResizeObserver
    globalThis.ResizeObserver = class {
        constructor(callback) { resized = callback }
        observe() {}
        disconnect() { disconnected = true }
    }
    const bindings = { computed, ref, watchPostEffect, onMounted: callback => { mounted = callback }, onBeforeUnmount: callback => { cleanup = callback } }
    const component = new Function(...Object.keys(bindings), source)(...Object.values(bindings))
    const props = reactive({ modelValue: '' })
    const scope = effectScope()
    let vm
    const updates = []
    scope.run(() => { vm = component.setup(props, { expose() {}, emit: (name, value) => updates.push([name, value]) }) })
    const input = { style: {}, offsetHeight: 44, clientHeight: 42, scrollHeight: 42 }
    vm.textarea.value = input
    mounted()
    return { vm, props, input, updates, resized: value => resized(value), get disconnected() { return disconnected }, dispose: () => { cleanup(); scope.stop(); globalThis.ResizeObserver = previousObserver } }
}

test('grows to show a long name and shrinks after text is deleted or the width changes', async () => {
    const context = setup()
    try {
        context.vm.resizeName()
        assert.equal(context.input.style.height, '44px')
        context.input.scrollHeight = 90
        context.props.modelValue = '很長的物品名稱'.repeat(10)
        await nextTick()
        assert.equal(context.input.style.height, '92px')
        context.input.scrollHeight = 42
        context.resized([{ contentRect: { width: 500 } }])
        assert.equal(context.input.style.height, '44px')
    } finally { context.dispose() }
    assert.equal(context.disconnected, true)
})
test('keeps Enter from submitting or adding a new paragraph, while allowing IME confirmation', () => {
    const context = setup()
    try {
        let prevented = 0
        context.vm.onEnter({ preventDefault: () => prevented++ })
        context.vm.onEnter({ isComposing: true, preventDefault: () => prevented++ })
        context.vm.onEnter({ keyCode: 229, preventDefault: () => prevented++ })
        assert.equal(prevented, 1)
        context.vm.name.value = '長名稱'
        assert.deepEqual(context.updates, [['update:modelValue', '長名稱']])
    } finally { context.dispose() }
})
