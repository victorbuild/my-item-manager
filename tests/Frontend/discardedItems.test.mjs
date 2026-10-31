import test from 'node:test'
import assert from 'node:assert/strict'
import { effectScope, nextTick } from 'vue'
import { createRouter, createMemoryHistory } from 'vue-router'
import { useDiscardedItems } from '../../resources/js/composables/useDiscardedItems.js'

const settle = async () => { await new Promise(resolve => setImmediate(resolve)); await nextTick() }
const result = (page, name) => ({ data: { data: [{ name }], meta: { current_page: page, last_page: 5 } } })
async function setup() {
    const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/discarded', component: {} }] })
    await router.push('/discarded?search=coat&category_id=3&per_page=20')
    const requests = []
    const api = { get: (url, config) => new Promise((resolve, reject) => requests.push({ url, config, resolve, reject })) }
    const route = { get query() { return router.currentRoute.value.query }, get path() { return router.currentRoute.value.path } }
    const scope = effectScope()
    const state = scope.run(() => useDiscardedItems(route, router, api))
    return { router, requests, scope, state }
}

test('discarded pagination and browser back restore URL filters and list contents', async () => {
    const { router, requests, scope, state } = await setup()
    try {
        assert.equal(requests[0].url, '/api/items')
        assert.deepEqual(requests[0].config.params, { page: 1, search: 'coat', category_id: '3', statuses: 'unused_discarded,used_discarded', sort: 'discarded', per_page: 20 })
        requests[0].resolve(result(1, 'first'))
        await settle()
        await state.goToPage(2)
        requests[1].resolve(result(2, 'second'))
        await settle()
        assert.equal(state.currentPage.value, 2)
        assert.equal(state.items.value[0].name, 'second')
        router.back()
        await settle()
        assert.equal(state.currentPage.value, 1)
        assert.equal(requests[2].config.params.search, 'coat')
        requests[2].resolve(result(1, 'first restored'))
        await settle()
        assert.equal(state.items.value[0].name, 'first restored')
        router.forward()
        await settle()
        assert.equal(requests[3].config.params.page, 2)
    } finally { scope.stop() }
})

test('rapid page changes ignore older results and repeated current-page clicks', async () => {
    const { requests, scope, state } = await setup()
    try {
        await state.goToPage(2)
        await state.goToPage(3)
        requests[0].resolve(result(1, 'stale'))
        requests[1].reject(new Error('stale error'))
        await settle()
        assert.ok(requests[1].config.signal.aborted)
        assert.equal(state.loading.value, true)
        assert.equal(state.error.value, '')
        requests[2].resolve(result(3, 'latest'))
        await settle()
        assert.equal(state.items.value[0].name, 'latest')
        await state.goToPage(3)
        await state.goToPage(0)
        await state.goToPage(6)
        assert.equal(requests.length, 3)
    } finally { scope.stop() }
})

test('leaving aborts pending requests and current errors remain visible', async () => {
    const { requests, scope, state } = await setup()
    requests[0].reject(new Error('offline'))
    await settle()
    assert.ok(state.error.value)
    assert.equal(state.loading.value, false)
    await state.goToPage(2)
    scope.stop()
    assert.ok(requests[1].config.signal.aborted)
    requests[1].resolve(result(2, 'after leaving'))
    await settle()
    assert.deepEqual(state.items.value, [])
})
