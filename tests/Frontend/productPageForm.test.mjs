import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { parse, compileScript } from '@vue/compiler-sfc'
import { computed, ref, watch, reactive, effectScope, nextTick } from 'vue'
const { descriptor } = parse(readFileSync(new URL('../../resources/js/Pages/Products/Form.vue', import.meta.url), 'utf8'))
const source = compileScript(descriptor, { id: 'product-page-test' }).content.replace(/^import .*\n/gm, '').replace('export default', 'return')
const settle = async () => { await new Promise(resolve => setImmediate(resolve)); await nextTick() }

test('switching edited products reloads the new product and discards stale initial data', async () => {
    const route = reactive({ params: { id: 'A' } }), requests = []
    let cleanup
    const bindings = { computed, ref, watch, onBeforeUnmount: fn => { cleanup = fn }, useRoute: () => route, useRouter: () => ({ push() {} }), ProductForm: {}, axios: { get: (url, config) => new Promise(resolve => requests.push({ url, config, resolve })) } }
    const component = new Function(...Object.keys(bindings), source)(...Object.values(bindings))
    const scope = effectScope()
    const vm = scope.run(() => component.setup({}, { expose() {} }))
    try {
        requests[0].resolve({ data: { data: { name: 'A' } } }); await settle()
        assert.equal(vm.initialProduct.value.name, 'A')
        route.params.id = 'B'; await nextTick()
        assert.equal(requests[1].url, '/api/products/B')
        assert.equal(vm.initialProduct.value, null)
        assert.equal(vm.loading.value, true)
        route.params.id = 'C'; await nextTick()
        assert.ok(requests[1].config.signal.aborted)
        requests[1].resolve({ data: { data: { name: 'B' } } }); await settle()
        assert.equal(vm.initialProduct.value, null)
        requests[2].resolve({ data: { data: { name: 'C' } } }); await settle()
        assert.equal(vm.initialProduct.value.name, 'C')
        route.params.id = undefined; await nextTick()
        assert.equal(vm.initialProduct.value, null)
        assert.equal(vm.loading.value, false)
        assert.equal(vm.isEdit.value, false)
    } finally { cleanup(); scope.stop() }
})
