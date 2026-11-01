import test from 'node:test'
import assert from 'node:assert/strict'
import { effectScope, nextTick } from 'vue'
import { createRouter, createMemoryHistory } from 'vue-router'
import { useCategoryDetails } from '../../resources/js/composables/useCategoryDetails.js'
const settle = async () => { await new Promise(resolve => setImmediate(resolve)); await nextTick() }
const result = (name, page) => ({ data: { items: [{ name: '分類' }], stats: { products_count: 21 }, products: [{ name }], meta: { current_page: page, last_page: 3 } } })

test('category detail pagination restores results on back and ignores older page responses', async () => {
    const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/categories/:id', component: {} }] })
    await router.push('/categories/5?page=2')
    const requests = []
    const route = { get params() { return router.currentRoute.value.params }, get query() { return router.currentRoute.value.query }, get path() { return router.currentRoute.value.path } }
    const scope = effectScope()
    const state = scope.run(() => useCategoryDetails(route, router, { get: (url, config) => new Promise(resolve => requests.push({ url, config, resolve })) }))
    try {
        assert.equal(requests[0].url, '/api/categories/5')
        assert.deepEqual(requests[0].config.params, { page: 2, per_page: 10 })
        requests[0].resolve(result('second', 2)); await settle()
        await state.goToPage(3)
        router.back(); await settle()
        assert.ok(requests[1].config.signal.aborted)
        assert.equal(requests[2].config.params.page, 2)
        requests[2].resolve(result('restored', 2)); await settle()
        requests[1].resolve(result('stale third', 3)); await settle()
        assert.equal(state.products.value[0].name, 'restored')
        assert.equal(state.pagination.value.current_page, 2)
        assert.equal(state.loading.value, false)
    } finally { scope.stop() }
})
