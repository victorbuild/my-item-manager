import test from 'node:test'
import assert from 'node:assert/strict'
import { createCurrentUserState } from '../../resources/js/utils/currentUser.js'

test('header and home share one pending account lookup and cache a resolved account', async () => {
    let resolve, calls = 0
    const state = createCurrentUserState({ get: () => { calls++; return new Promise(done => { resolve = done }) } })
    const first = state.load(), second = state.load()
    assert.equal(first, second)
    assert.equal(calls, 1)
    resolve({ data: { data: { id: 1, name: 'victor' } } })
    await first
    assert.equal(state.isLoggedIn.value, true)
    await state.load()
    assert.equal(calls, 1)
})
test('guest response stays on the public page and a refresh after login loads the account', async () => {
    let guest = true
    const state = createCurrentUserState({ get: async () => {
        if (guest) throw { response: { status: 401 } }
        return { data: { data: { id: 2, name: '會員' } } }
    } })
    await state.load()
    assert.equal(state.isLoggedIn.value, false)
    assert.equal(state.error.value, '')
    guest = false
    await state.load({ force: true })
    assert.equal(state.user.value.id, 2)
})
test('network failure is retryable instead of pretending the user is signed out', async () => {
    let fail = true
    const state = createCurrentUserState({ get: async () => {
        if (fail) throw new Error('offline')
        return { data: { data: { id: 1 } } }
    } })
    await state.load()
    assert.ok(state.error.value)
    fail = false
    await state.load()
    assert.equal(state.user.value.id, 1)
    assert.equal(state.error.value, '')
})
test('late account responses cannot restore a signed-out user or replace a refreshed account', async () => {
    const requests = []
    const state = createCurrentUserState({ get: (url, config) => new Promise(resolve => requests.push({ config, resolve })) })
    const first = state.load()
    const refreshed = state.load({ force: true })
    assert.equal(requests[0].config.signal.aborted, true)
    requests[1].resolve({ data: { data: { id: 2 } } })
    await refreshed
    requests[0].resolve({ data: { data: { id: 1 } } })
    await first
    assert.equal(state.user.value.id, 2)
    const final = state.load({ force: true })
    state.clear()
    requests[2].resolve({ data: { data: { id: 2 } } })
    await final
    assert.equal(state.user.value, null)
    assert.equal(state.isLoggedIn.value, false)
})
