import test from 'node:test'
import assert from 'node:assert/strict'
import { effectScope, nextTick } from 'vue'
import { createRouter, createMemoryHistory } from 'vue-router'
import { useProducts } from '../../resources/js/composables/useProducts.js'
const settle = async () => { await new Promise(resolve => setImmediate(resolve)); await nextTick() }
const result = name => ({ data: { data: [{ name }], meta: { current_page: 1, last_page: 4, total: 40 } } })
async function setup(url = '/products') {
    const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/products', component: {} }] })
    await router.push(url)
    const requests = []
    const api = { get: (url, config) => new Promise((resolve, reject) => requests.push({ url, config, resolve, reject })) }
    const route = { get query() { return router.currentRoute.value.query }, get path() { return router.currentRoute.value.path } }
    const scope = effectScope()
    const state = scope.run(() => useProducts(route, router, api))
    return { router, requests, scope, state }
}

test('submitted search, pagination and clearing are restored by browser back and forward', async () => {
    const { router, requests, scope, state } = await setup()
    try {
        requests[0].resolve(result('all'))
        await settle()
        state.search.value = 'tea'
        await state.submitSearch()
        requests[1].resolve(result('tea'))
        await settle()
        await state.goToPage(2)
        assert.deepEqual(requests[2].config.params, { page: 2, q: 'tea' })
        await state.clearSearch()
        assert.deepEqual(requests[3].config.params, { page: 1, q: '' })
        router.back(); await settle()
        assert.equal(state.search.value, 'tea')
        assert.deepEqual(requests[4].config.params, { page: 2, q: 'tea' })
        router.back(); await settle()
        assert.deepEqual(requests[5].config.params, { page: 1, q: 'tea' })
        router.back(); await settle()
        assert.equal(state.search.value, '')
        router.forward(); await settle()
        requests.at(-1).resolve(result('restored tea'))
        await settle()
        assert.equal(state.products.value[0].name, 'restored tea')
    } finally { scope.stop() }
})

test('initial URL is respected and drafts do not affect pagination or reload history', async () => {
    const { router, requests, scope, state } = await setup('/products?q=book&page=3')
    try {
        assert.deepEqual(requests[0].config.params, { page: 3, q: 'book' })
        requests[0].resolve(result('book')); await settle()
        state.search.value = 'unsent'
        await nextTick()
        assert.equal(requests.length, 1)
        await state.goToPage(4)
        assert.deepEqual(requests[1].config.params, { page: 4, q: 'book' })
        const before = router.currentRoute.value.fullPath
        state.search.value = 'another draft'
        const reloaded = state.reload()
        assert.deepEqual(requests[2].config.params, { page: 4, q: 'book' })
        requests[2].resolve(result('refreshed')); await reloaded
        assert.equal(router.currentRoute.value.fullPath, before)
        state.search.value = 'book'
        await state.goToPage(4)
        assert.equal(requests.length, 3)
    } finally { scope.stop() }
})

test('rapid searches reject stale responses and errors; leaving cancels pending work', async () => {
    const { requests, scope, state } = await setup()
    state.search.value = 'tea'; await state.submitSearch()
    state.search.value = 'book'; await state.submitSearch()
    assert.ok(requests[0].config.signal.aborted)
    requests[0].resolve(result('stale'))
    requests[1].reject(new Error('old failure')); await settle()
    assert.equal(state.loading.value, true)
    assert.equal(state.error.value, '')
    requests[2].resolve(result('latest')); await settle()
    assert.equal(state.products.value[0].name, 'latest')
    const pending = state.reload()
    scope.stop()
    requests[3].resolve(result('after leaving')); await pending
    assert.ok(requests[3].config.signal.aborted)
    assert.equal(state.products.value[0].name, 'latest')
})

test('empty search uses the applied query rather than an unsent draft and can be cleared', async () => {
    const { requests, scope, state, router } = await setup('/products?q=missing')
    try {
        requests[0].resolve({ data: { data: [], meta: { current_page: 1, last_page: 1, total: 0 } } })
        await settle()
        state.search.value = 'unsent draft'
        assert.equal(state.appliedSearch.value, 'missing')
        assert.deepEqual(state.products.value, [])
        await state.clearSearch()
        assert.equal(state.appliedSearch.value, '')
        assert.equal(router.currentRoute.value.query.q, undefined)
        assert.deepEqual(requests[1].config.params, { page: 1, q: '' })
    } finally { scope.stop() }
})


test('deleting the last result on a trailing page returns to a valid page instead of a false empty state', async () => {
    const { router, requests, scope, state } = await setup('/products?q=tea&page=2')
    try {
        requests[0].resolve(result('last result')); await settle()
        const refresh = state.reload()
        requests[1].resolve({ data: { data: [], meta: { current_page: 2, last_page: 1, total: 10 } } })
        await refresh; await settle()
        assert.equal(router.currentRoute.value.query.page, undefined)
        assert.equal(router.currentRoute.value.query.q, 'tea')
        assert.equal(requests[2].config.params.page, 1)
        requests[2].resolve(result('remaining result')); await settle()
        assert.equal(state.products.value[0].name, 'remaining result')
        assert.equal(state.loading.value, false)
    } finally { scope.stop() }
})
