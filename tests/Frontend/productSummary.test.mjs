import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { parse, compileScript } from '@vue/compiler-sfc'
import { computed, reactive, ref, watch, effectScope } from 'vue'

const { descriptor } = parse(readFileSync(new URL('../../resources/js/Components/ProductSummary.vue', import.meta.url), 'utf8'))
const source = compileScript(descriptor, { id: 'product-summary-test' }).content.replace(/^import .*\n/gm, '').replace('export default', 'return')
const tick = () => new Promise(resolve => setImmediate(resolve))
const setup = product => {
    const requests = []
    let cleanup
    const bindings = { computed, ref, watch, onBeforeUnmount: fn => { cleanup = fn }, axios: { get: (url, config) => new Promise((resolve, reject) => requests.push({ url, config, resolve, reject })) } }
    const component = new Function(...Object.keys(bindings), source)(...Object.values(bindings))
    const props = reactive({ product })
    const scope = effectScope()
    let vm
    scope.run(() => { vm = component.setup(props, { expose() {} }) })
    return { vm, props, requests, dispose: () => { cleanup(); scope.stop() } }
}

test('loads full product fields and current owned counts without mutating the selected product', async () => {
    const product = { id: 1, short_id: 'one', name: '長產品名稱' }
    const context = setup(product)
    try {
        assert.equal(context.vm.ownedCount.value, null)
        context.requests[0].resolve({ data: { data: { ...product, brand: '品牌', model: '型號', stats: { pre_arrival: 1, unused: 2, in_use: 3, unused_discarded: 8, used_discarded: 9 } } } })
        await tick()
        assert.equal(context.vm.details.value.brand, '品牌')
        assert.equal(context.vm.ownedCount.value, 6)
        assert.equal(product.brand, undefined)
    } finally { context.dispose() }
})
test('switching products prevents previous responses from showing stale names or details', async () => {
    const context = setup({ id: 1, short_id: 'one', name: '原產品' })
    try {
        context.props.product = { id: 2, short_id: 'two', name: '新產品' }
        await tick()
        context.requests[1].resolve({ data: { data: { name: '新產品', brand: '新品牌' } } })
        await tick()
        context.requests[0].resolve({ data: { data: { name: '舊資料', brand: '舊品牌' } } })
        await tick()
        assert.equal(context.vm.details.value.name, '新產品')
        assert.equal(context.vm.details.value.brand, '新品牌')
    } finally { context.dispose() }
    assert.equal(context.requests[1].config.signal.aborted, true)
})
test('keeps available product information on failure without presenting unknown counts as zero', async () => {
    const context = setup({ id: 1, short_id: 'one', name: '原產品', brand: '品牌' })
    try {
        context.requests[0].reject(new Error('offline'))
        await tick()
        assert.ok(context.vm.detailError.value)
        assert.equal(context.vm.details.value.name, '原產品')
        assert.equal(context.vm.details.value.brand, '品牌')
        assert.equal(context.vm.ownedCount.value, null)
    } finally { context.dispose() }
})
