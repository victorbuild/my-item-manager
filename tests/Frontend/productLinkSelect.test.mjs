import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { parse, compileScript } from '@vue/compiler-sfc'
import { computed, reactive, ref, effectScope } from 'vue'

const { descriptor } = parse(readFileSync(new URL('../../resources/js/Components/ProductLinkSelect.vue', import.meta.url), 'utf8'))
const source = compileScript(descriptor, { id: 'product-link-test' }).content.replace(/^import .*\n/gm, '').replace('export default', 'return')
const tick = () => new Promise(resolve => setImmediate(resolve))
const setup = () => {
    const requests = [], emitted = []
    let mounted, cleanup, timer
    const bindings = {
        computed, ref, useId: () => 'test', onMounted: fn => { mounted = fn }, onBeforeUnmount: fn => { cleanup = fn },
        Multiselect: {}, AppButton: {}, ProductCreateModal: {},
        axios: { get: (url, config) => new Promise((resolve, reject) => requests.push({ url, config, resolve, reject })) },
        setTimeout: fn => { timer = fn; return 1 }, clearTimeout: () => { timer = null },
    }
    const component = new Function(...Object.keys(bindings), source)(...Object.values(bindings))
    const props = reactive({ modelValue: { id: 1, name: '原產品' }, id: 'product', errorMessage: '' })
    const scope = effectScope()
    let vm
    scope.run(() => { vm = component.setup(props, { expose() {}, emit: (...values) => emitted.push(values) }) })
    mounted()
    return { vm, requests, emitted, props, runTimer: () => timer?.(), dispose: () => { cleanup(); scope.stop() } }
}

test('shows the saved product without emitting changes, and only explicitly emits relink or unlink', async () => {
    const context = setup()
    try {
        context.requests[0].resolve({ data: { data: [{ id: 2, name: '第二個產品' }] } })
        await tick()
        assert.deepEqual(context.vm.options.value.map(product => product.id), [1, 2])
        assert.deepEqual(context.emitted, [])
        context.vm.selection.value = { id: 2, name: '第二個產品' }
        context.vm.selection.value = null
        assert.deepEqual(context.emitted, [['update:modelValue', { id: 2, name: '第二個產品' }], ['update:modelValue', null]])
    } finally { context.dispose() }
})
test('a previous search cannot overwrite results while the next search is waiting for debounce', async () => {
    const context = setup()
    try {
        context.vm.onSearch('A')
        context.runTimer()
        context.vm.onSearch('B')
        context.requests[1].resolve({ data: { data: [{ id: 5, name: '過期搜尋' }] } })
        await tick()
        assert.deepEqual(context.vm.results.value, [])
        context.runTimer()
        context.requests[2].resolve({ data: { data: [{ id: 6, name: 'B' }] } })
        await tick()
        assert.deepEqual(context.vm.options.value.filter(product => !product.isNew).map(product => product.id), [6])
        assert.equal(context.requests[2].config.params.q, 'B')
    } finally { context.dispose() }
    assert.equal(context.requests[2].config.signal.aborted, true)
})
test('failed searches expose a retryable error while retaining the selected product', async () => {
    const context = setup()
    try {
        context.requests[0].reject(new Error('offline'))
        await tick()
        assert.ok(context.vm.searchError.value)
        assert.equal(context.vm.loading.value, false)
        assert.equal(context.vm.selection.value.id, 1)
        context.vm.searchProducts('')
        context.requests[1].resolve({ data: { data: [] } })
        await tick()
        assert.equal(context.vm.searchError.value, '')
    } finally { context.dispose() }
})

test('searching does not insert a create option; the separate link preserves the current product', async () => {
    const context = setup()
    try {
        context.vm.query.value = '新產品'
        context.requests[0].resolve({ data: { data: [] } })
        await tick()
        assert.deepEqual(context.vm.options.value, [])
        context.vm.openCreate(context.vm.query.value)
        assert.equal(context.vm.showCreate.value, true)
        assert.equal(context.vm.createName.value, '新產品')
        assert.equal(context.emitted.some(([event]) => event === 'update:modelValue'), false)
        context.vm.setCreateOpen(false)
        assert.equal(context.props.modelValue.id, 1)
    } finally { context.dispose() }
})
test('a successful creation selects the new product, while cancellation preserves the old link', () => {
    const context = setup()
    try {
        context.vm.openCreate('新產品')
        context.vm.onCreated({ id: 9, name: '新產品' })
        assert.equal(context.vm.showCreate.value, false)
        assert.ok(context.emitted.some(([event, value]) => event === 'update:modelValue' && value.id === 9))
        assert.ok(context.emitted.some(([event, value]) => event === 'creation-pending' && value === false))
    } finally { context.dispose() }
})
