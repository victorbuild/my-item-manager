import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { parse, compileScript } from '@vue/compiler-sfc'
import { computed, nextTick, reactive, ref, watch, effectScope } from 'vue'
import { preventImplicitSubmit } from '../../resources/js/utils/formKeyboard.js'

const { descriptor } = parse(readFileSync(new URL('../../resources/js/Components/ProductForm.vue', import.meta.url), 'utf8'))
const source = compileScript(descriptor, { id: 'product-create-test' }).content.replace(/^import .*\n/gm, '').replace('export default', 'return')
const tick = () => new Promise(resolve => setImmediate(resolve))
const setup = (post, get = async () => ({ data: { data: [{ id: 1, name: '分類' }] } }), overrides = {}) => {
    const calls = [], emitted = [], getCalls = []
    let cleanup
    const bindings = {
        computed, nextTick, ref, watch, useId: () => 'test', onBeforeUnmount: fn => { cleanup = fn }, Html5Qrcode: class {},
        AppModal: {}, AppButton: {}, ItemNameInput: {}, SingleSelect: {}, preventImplicitSubmit,
        axios: { put: async (url, payload) => { calls.push({ url, payload, method: 'put' }); return post(payload, url) }, get: async (url, config) => { getCalls.push({ url, config }); return get(url, config) }, post: async (url, payload) => { calls.push({ url, payload }); return post(payload, url) } },
    }
    const component = new Function(...Object.keys(bindings), source)(...Object.values(bindings))
    const props = reactive({ modelValue: true, initialName: '新產品', ...overrides })
    const scope = effectScope()
    let vm
    scope.run(() => { vm = component.setup(props, { expose() {}, emit: (...values) => emitted.push(values) }) })
    return { vm, props, calls, emitted, getCalls, dispose: () => { cleanup(); scope.stop() } }
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

test('category search waits for typing and debounces to the latest server query', async () => {
    const context = setup(async () => {})
    try {
        await tick()
        assert.equal(context.getCalls.length, 0)
        context.vm.searchCategories('家')
        context.vm.searchCategories('家電')
        assert.equal(context.getCalls.length, 0)
        await new Promise(resolve => setTimeout(resolve, 280))
        assert.equal(context.getCalls.length, 1)
        assert.equal(context.getCalls[0].url, '/api/categories')
        assert.deepEqual(context.getCalls[0].config.params, { q: '家電', per_page: 20 })
        assert.equal(context.vm.categoryLoading.value, false)
    } finally { context.dispose() }
})
test('server search can select a category outside the first page and preserves it across searches', async () => {
    const context = setup(async () => {}, async (url, { params }) => ({ data: { data: params.q === '家電' ? [{ id: 99, name: '家電' }] : [{ id: 2, name: '書籍' }] } }))
    try {
        context.vm.categoryQuery.value = '家電'
        await context.vm.loadCategories()
        context.vm.product.value.category_id = '99'
        context.vm.categoryQuery.value = '書籍'
        await context.vm.loadCategories()
        assert.equal(context.vm.product.value.category_id, '99')
        assert.ok(context.vm.categoryOptions.value.some(option => option.value === '99' && option.label === '家電'))
        assert.ok(context.vm.categoryOptions.value.some(option => option.value === '2'))
        context.vm.searchCategories('')
        assert.equal(context.vm.categoryOptions.value.length, 1)
        assert.equal(context.vm.product.value.category_id, '99')
    } finally { context.dispose() }
})
test('late category responses cannot overwrite a newer query or a reopened modal', async () => {
    const pending = []
    const context = setup(async () => {}, () => new Promise(resolve => pending.push(resolve)))
    try {
        const first = context.vm.loadCategories('舊搜尋')
        const second = context.vm.loadCategories('新搜尋')
        assert.equal(context.getCalls[0].config.signal.aborted, true)
        pending[1]({ data: { data: [{ id: 2, name: '新分類' }] } })
        await second
        pending[0]({ data: { data: [{ id: 1, name: '過期結果' }] } })
        await first
        assert.deepEqual(context.vm.categories.value, [{ id: 2, name: '新分類' }])
        const closing = context.vm.loadCategories('關閉前')
        context.props.modelValue = false
        await nextTick()
        context.props.modelValue = true
        await nextTick()
        pending[2]({ data: { data: [{ id: 3, name: '上一個彈窗的結果' }] } })
        await closing
        assert.deepEqual(context.vm.categories.value, [])
    } finally { context.dispose() }
})
test('failed category search supports retry and closing cancels queued requests', async () => {
    let fail = true
    const context = setup(async () => {}, async () => {
        if (fail) throw new Error('offline')
        return { data: { data: [{ id: 7, name: '重試成功' }] } }
    })
    try {
        context.vm.categoryQuery.value = '分類'
        await context.vm.loadCategories()
        assert.ok(context.vm.categoryError.value)
        assert.equal(context.vm.categoryLoading.value, false)
        fail = false
        await context.vm.loadCategories()
        assert.equal(context.vm.categoryError.value, '')
        assert.equal(context.vm.categories.value[0].id, 7)
        context.vm.searchCategories('下一次')
        context.props.modelValue = false
        await nextTick()
        await new Promise(resolve => setTimeout(resolve, 280))
        assert.equal(context.getCalls.length, 2)
    } finally { context.dispose() }
})

test('create-category action appears only after successful search without an exact match', async () => {
    let fail = false
    const context = setup(async () => {}, async (url, { params }) => {
        if (fail) throw new Error('offline')
        return { data: { data: params.q === '書籍' ? [{ id: 5, name: '書籍' }] : [] } }
    })
    try {
        context.vm.categoryQuery.value = '收藏'
        assert.equal(context.vm.canOfferCategoryCreation.value, false)
        await context.vm.loadCategories()
        assert.equal(context.vm.canOfferCategoryCreation.value, true)
        context.vm.categoryLoading.value = true
        assert.equal(context.vm.canOfferCategoryCreation.value, false)
        context.vm.categoryLoading.value = false
        context.vm.categoryQuery.value = '書籍'
        await context.vm.loadCategories()
        assert.equal(context.vm.canOfferCategoryCreation.value, false)
        context.vm.categoryQuery.value = '新分類'
        fail = true
        await context.vm.loadCategories()
        assert.equal(context.vm.canOfferCategoryCreation.value, false)
    } finally { context.dispose() }
})
test('choosing create pre-fills confirmation and selects the category only after server success', async () => {
    let release
    const context = setup(() => new Promise(resolve => { release = resolve }), async () => ({ data: { data: [] } }))
    try {
        context.vm.categories.value = [{ id: 1, name: '原分類' }]
        context.vm.product.value.category_id = '1'
        context.vm.categoryQuery.value = '模型收藏'
        await context.vm.loadCategories()
        await context.vm.openCategoryCreate()
        assert.equal(context.vm.categoryName.value, '模型收藏')
        assert.equal(context.vm.categoryCreateOpen.value, true)
        assert.equal(context.vm.product.value.category_id, '1')
        assert.equal(context.calls.length, 0)
        const pending = context.vm.createCategory()
        await context.vm.createCategory()
        assert.equal(context.calls.length, 1)
        assert.deepEqual(context.calls[0], { url: '/api/categories', payload: { name: '模型收藏' } })
        assert.equal(context.vm.product.value.category_id, '1')
        release({ data: { items: [{ id: 88, name: '模型收藏' }] } })
        await pending
        assert.equal(context.vm.product.value.category_id, '88')
        assert.equal(context.vm.selectedCategory.value.name, '模型收藏')
        assert.equal(context.vm.categoryCreateOpen.value, false)
    } finally { context.dispose() }
})
test('failed category creation preserves confirmation and does not replace the selected category', async () => {
    const context = setup(async () => { throw { response: { data: { errors: { name: ['分類名稱已存在'] } } } } }, async () => ({ data: { data: [] } }))
    try {
        context.vm.categories.value = [{ id: 1, name: '原分類' }]
        context.vm.product.value.category_id = '1'
        context.vm.categoryQuery.value = '模型'
        await context.vm.loadCategories()
        await context.vm.openCategoryCreate()
        await context.vm.createCategory()
        assert.equal(context.vm.categoryCreateOpen.value, true)
        assert.equal(context.vm.categoryName.value, '模型')
        assert.equal(context.vm.categoryCreateError.value, '分類名稱已存在')
        assert.equal(context.vm.product.value.category_id, '1')
    } finally { context.dispose() }
})

test('shared editor preserves the existing category and updates only the chosen product', async () => {
    const context = setup(async payload => ({ data: { data: payload } }), undefined, {
        mode: 'edit', productId: 'existing-product',
        initialProduct: { name: '原產品', brand: '品牌', category: { id: 42, name: '既有分類' }, model: '型號', spec: '規格', barcode: '1234' },
    })
    try {
        assert.equal(context.vm.product.value.category_id, '42')
        assert.ok(context.vm.categoryOptions.value.some(option => option.label === '既有分類'))
        context.vm.product.value.name = '更新的產品'
        await context.vm.createProduct()
        assert.equal(context.calls[0].method, 'put')
        assert.equal(context.calls[0].url, '/api/products/existing-product')
        assert.equal(context.calls[0].payload.category_id, 42)
        assert.equal(context.calls[0].payload.barcode, '1234')
        assert.ok(context.emitted.some(([event]) => event === 'saved'))
        assert.equal(context.emitted.some(([event]) => event === 'created'), false)
    } finally { context.dispose() }
})

test('shared edit failure retains inputs and permits retry without emitting saved', async () => {
    const context = setup(async () => { throw { response: { status: 422, data: { errors: { name: ['名稱錯誤'] } } } } }, undefined, {
        mode: 'edit', productId: 'existing-product', initialProduct: { name: '原產品' },
    })
    try {
        context.vm.product.value.name = '保留此內容'
        await context.vm.createProduct()
        assert.equal(context.vm.product.value.name, '保留此內容')
        assert.equal(context.vm.formErrors.value.name, '名稱錯誤')
        assert.equal(context.vm.canCreate.value, true)
        assert.equal(context.emitted.some(([event]) => event === 'saved'), false)
    } finally { context.dispose() }
})
