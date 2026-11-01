import test from 'node:test'
import assert from 'node:assert/strict'
import { effectScope, nextTick } from 'vue'
import { createRouter, createMemoryHistory } from 'vue-router'
import { useExpiringItems } from '../../resources/js/composables/useExpiringItems.js'

async function setup(url = '/expiring-items') {
    const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/expiring-items', component: {} }] })
    await router.push(url)
    const requests = []
    const api = { get: (url, config) => new Promise((resolve, reject) => requests.push({ config, resolve, reject })) }
    const scope = effectScope()
    const route = { get query() { return router.currentRoute.value.query }, get path() { return router.currentRoute.value.path } }
    const state = scope.run(() => useExpiringItems(route, router, api))
    return { router, requests, scope, state }
}
const response = name => ({ data: { data: [{ name }], meta: { current_page: 1, last_page: 2 } } })
const settle = async () => { await new Promise(resolve => setImmediate(resolve)); await nextTick() }

test('initial URL, range changes and browser back restore the same API parameters', async () => {
    const { router, requests, scope, state } = await setup('/expiring-items?days=90&page=2')
    try {
        assert.deepEqual(requests[0].config.params, { days: 90, page: 2, per_page: 20 })
        await state.selectDays(365)
        assert.equal(state.days.value, 365)
        assert.equal(router.currentRoute.value.query.page, undefined)
        assert.equal(requests[1].config.params.page, 1)
        router.back()
        await settle()
        assert.equal(state.days.value, 90)
        assert.equal(requests.at(-1).config.params.page, 2)
        requests.at(-1).resolve(response('restored'))
        await settle()
        assert.equal(state.items.value[0].name, 'restored')
    } finally { scope.stop() }
})

test('rapid navigation ignores stale success and failure without clearing the latest loading state', async () => {
    const { scope, state, requests } = await setup()
    try {
        await state.selectDays(90)
        await state.selectDays(365)
        assert.ok(requests[0].config.signal.aborted)
        assert.ok(requests[1].config.signal.aborted)
        requests[0].resolve(response('stale'))
        requests[1].reject(new Error('late failure'))
        await settle()
        assert.equal(state.loading.value, true)
        assert.equal(state.error.value, '')
        requests[2].resolve(response('latest'))
        await settle()
        assert.equal(state.items.value[0].name, 'latest')
        assert.equal(state.loading.value, false)
        await state.selectDays(365)
        assert.equal(requests.length, 3)
    } finally { scope.stop() }
})

test('pagination is URL-driven and leaving the page prevents pending results from updating state', async () => {
    const { router, scope, state, requests } = await setup()
    await state.goToPage(2)
    assert.equal(router.currentRoute.value.query.page, '2')
    assert.equal(requests[1].config.params.days, 30)
    scope.stop()
    assert.ok(requests[1].config.signal.aborted)
    requests[1].resolve(response('after leaving'))
    await settle()
    assert.deepEqual(state.items.value, [])
})

test('a current request failure is shown and a subsequent range selection recovers', async () => {
    const { scope, state, requests } = await setup()
    try {
        requests[0].reject(new Error('offline'))
        await settle()
        assert.ok(state.error.value)
        assert.equal(state.loading.value, false)
        await state.selectDays(7)
        assert.equal(state.error.value, '')
        requests[1].resolve(response('recovered'))
        await settle()
        assert.equal(state.items.value[0].name, 'recovered')
    } finally { scope.stop() }
})
