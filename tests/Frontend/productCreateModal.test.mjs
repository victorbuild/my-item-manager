import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { parse, compileScript } from '@vue/compiler-sfc'
import { computed, nextTick, reactive, ref, watch, effectScope } from 'vue'
import { preventImplicitSubmit } from '../../resources/js/utils/formKeyboard.js'

const { descriptor } = parse(readFileSync(new URL('../../resources/js/Components/ProductCreateModal.vue', import.meta.url), 'utf8'))
const source = compileScript(descriptor, { id: 'product-create-test' }).content.replace(/^import .*\n/gm, '').replace('export default', 'return')
const tick = () => new Promise(resolve => setImmediate(resolve))
const setup = post => {
    const calls = [], emitted = []
    let cleanup
    const bindings = {
        computed, nextTick, ref, watch, useId: () => 'test', onBeforeUnmount: fn => { cleanup = fn }, Html5Qrcode: class {},
        AppModal: {}, AppButton: {}, ItemNameInput: {}, SingleSelect: {}, preventImplicitSubmit,
        axios: { get: async () => ({ data: { data: [{ id: 1, name: '分類' }] } }), post: async (url, payload) => { calls.push({ url, payload }); return post(payload, url) } },
    }
    const component = new Function(...Object.keys(bindings), source)(...Object.values(bindings))
    const props = reactive({ modelValue: true, initialName: '新產品' })
    const scope = effectScope()
    let vm
    scope.run(() => { vm = component.setup(props, { expose() {}, emit: (...values) => emitted.push(values) }) })
    return { vm, calls, emitted, dispose: () => { cleanup(); scope.stop() } }
}

test('creates a product once and emits selection only after the server returns its ID', async () => {
    let release
    const context = setup(() => new Promise(resolve => { release = resolve }))
    try {
        await tick()
        assert.equal(context.vm.product.value.name, '新產品')
        context.vm.product.value.category_id = '1'
        const saving = context.vm.createProduct()
        await context.vm.createProduct()
        assert.equal(context.calls.length, 1)
        assert.equal(context.calls[0].url, '/api/products')
        assert.equal(context.calls[0].payload.category_id, 1)
        assert.equal(context.emitted.some(([event]) => event === 'created'), false)
        release({ data: { data: { id: 25, name: '新產品' } } })
        await saving
        assert.ok(context.emitted.some(([event, product]) => event === 'created' && product.id === 25))
        assert.ok(context.emitted.some(([event, open]) => event === 'update:modelValue' && open === false))
        assert.ok(context.calls.every(call => !call.url.startsWith('/api/items')))
    } finally { context.dispose() }
})
test('creation failure preserves the entered name and keeps the modal open for retry', async () => {
    const context = setup(async () => { throw { response: { status: 422, data: { message: '驗證失敗', errors: { name: ['產品名稱錯誤'] } } } } })
    try {
        context.vm.product.value.name = '輸入的新產品'
        await context.vm.createProduct()
        assert.equal(context.vm.product.value.name, '輸入的新產品')
        assert.equal(context.vm.formErrors.value.name, '產品名稱錯誤')
        assert.equal(context.vm.isCreating.value, false)
        assert.equal(context.emitted.some(([event]) => event === 'created' || event === 'update:modelValue'), false)
    } finally { context.dispose() }
})
test('blank names cannot submit and cancelling emits no product association changes', async () => {
    const context = setup(async () => { throw new Error('must not submit') })
    try {
        context.vm.product.value.name = '  '
        await context.vm.createProduct()
        assert.equal(context.calls.length, 0)
        await context.vm.close()
        assert.ok(context.emitted.some(([event, open]) => event === 'update:modelValue' && open === false))
        assert.equal(context.emitted.some(([event]) => event === 'created'), false)
    } finally { context.dispose() }
})

test('creating a category selects it before product creation and cannot submit the product mid-step', async () => {
    const context = setup(async (payload, url) => {
        assert.equal(url, '/api/categories')
        return { data: { items: [{ id: 8, name: payload.name }] } }
    })
    try {
        context.vm.categoryCreateOpen.value = true
        context.vm.categoryName.value = '新分類'
        assert.equal(context.vm.canCreate.value, false)
        await context.vm.createCategory()
        assert.equal(context.vm.product.value.category_id, '8')
        assert.equal(context.vm.categoryCreateOpen.value, false)
        assert.equal(context.vm.canCreate.value, true)
    } finally { context.dispose() }
})
