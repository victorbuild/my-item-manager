import test from 'node:test'
import assert from 'node:assert/strict'
import { createAuthFailureHandler, createSessionRequestGuard } from '../../resources/js/utils/authFailure.js'
import axios from 'axios'
import { createCurrentUserState } from '../../resources/js/utils/currentUser.js'
import { createSessionRecovery } from '../../resources/js/utils/sessionRecovery.js'
import { createMemoryHistory, createRouter } from 'vue-router'
import { createUnsavedChangesGuard } from '../../resources/js/utils/unsavedChanges.js'
import { ref } from 'vue'

const setup = async get => {
    const account = createCurrentUserState({ get: async () => ({ data: { data: { id: 1 } } }) })
    await account.load()
    const calls = []
    const recovery = createSessionRecovery({ get: async url => { calls.push(url); return get(url) } }, account)
    const router = { replace: () => assert.fail('must preserve the current page'), push: () => assert.fail('unexpected navigation') }
    return { account, recovery, calls, handler: createAuthFailureHandler(router, recovery, () => true) }
}

test('401 leaves the editor intact and opens one recovery dialog without invoking leave guards', async () => {
    const router = createRouter({ history: createMemoryHistory(), routes: ['/items/1/edit', '/login'].map(path => ({ path, component: {} })) })
    await router.push('/items/1/edit')
    router.beforeEach(() => assert.fail('session expiry must not leave the editor'))
    const { recovery, account } = await setup(() => assert.fail('401 must not retry'))
    const failure = { response: { status: 401 } }
    const handler = createAuthFailureHandler(router, recovery, () => true)
    await assert.rejects(handler(failure), error => error === failure)
    assert.equal(router.currentRoute.value.path, '/items/1/edit')
    assert.equal(account.isLoggedIn.value, false)
    assert.equal(recovery.dialogOpen.value, true)
    assert.equal(recovery.blocksRequests.value, true)
    recovery.dialogOpen.value = false
    await assert.rejects(handler(failure), error => error === failure)
    assert.equal(recovery.dialogOpen.value, false)
    assert.equal(recovery.requiresLogin.value, true)
})

test('an expired media list read returns home without showing a recovery dialog', async () => {
    const router = createRouter({ history: createMemoryHistory(), routes: ['/', '/media'].map(path => ({ path, component: {} })) })
    await router.push('/media')
    const { recovery, account } = await setup(() => assert.fail('must not retry'))
    const failure = { config: { method: 'get' }, response: { status: 401 } }
    await assert.rejects(createAuthFailureHandler(router, recovery, () => false)(failure), error => error === failure)
    assert.equal(router.currentRoute.value.path, '/')
    assert.equal(recovery.state.value, 'idle')
    assert.equal(recovery.dialogOpen.value, false)
    assert.equal(account.user.value, null)
})

test('a save failure preserves the editor even without a registered dirty guard', async () => {
    const { recovery } = await setup(() => assert.fail('must not retry'))
    const router = { replace: () => assert.fail('must not discard editing work') }
    const failure = { config: { method: 'patch', preserveDraftOnAuthFailure: true }, response: { status: 401 } }
    await assert.rejects(createAuthFailureHandler(router, recovery, () => false)(failure), error => error === failure)
    assert.equal(recovery.state.value, 'expired')
    assert.equal(recovery.dialogOpen.value, true)
})

test('deleting from a list without a draft returns home instead of opening editing recovery', async () => {
    const { recovery } = await setup(() => assert.fail('must not retry'))
    const destinations = []
    const failure = { config: { method: 'delete' }, response: { status: 401 } }
    await assert.rejects(createAuthFailureHandler({ replace: path => destinations.push(path) }, recovery, () => false)(failure))
    assert.deepEqual(destinations, ['/'])
    assert.equal(recovery.dialogOpen.value, false)
    assert.equal(failure.authRecoveryHandled, true)
})

test('concurrent read and save expiry always preserves the editing request', async () => {
    let rejectLookup
    const { recovery } = await setup(() => new Promise((resolve, reject) => { rejectLookup = reject }))
    const handler = createAuthFailureHandler({ replace: () => assert.fail('must preserve the save') }, recovery, () => false)
    const read = handler({ config: { method: 'get' }, response: { status: 419 } }).catch(() => {})
    const save = handler({ config: { method: 'put', preserveDraftOnAuthFailure: true }, response: { status: 419 } }).catch(() => {})
    rejectLookup({ response: { status: 401 } })
    await Promise.all([read, save])
    assert.equal(recovery.state.value, 'expired')
    assert.equal(recovery.dialogOpen.value, true)
    assert.equal(recovery.blocksRequests.value, true)
})

test('a newer 401 invalidates a recovery that is still refreshing CSRF', async () => {
    let finishCsrf, startedCsrf
    const csrfStarted = new Promise(resolve => { startedCsrf = resolve })
    const { recovery, account } = await setup(url => {
        if (url === '/api/user') return { data: { data: { id: 1 } } }
        startedCsrf()
        return new Promise(resolve => { finishCsrf = resolve })
    })
    const oldCheck = recovery.check()
    await csrfStarted
    recovery.expire()
    finishCsrf({})
    await oldCheck
    assert.equal(recovery.state.value, 'expired')
    assert.equal(recovery.blocksRequests.value, true)
    assert.equal(account.user.value, null)
})

test('a background read failure also preserves an active unsaved draft', async () => {
    const draft = ref('original')
    const guard = createUnsavedChangesGuard({ snapshot: () => draft.value, target: new EventTarget() })
    draft.value = 'edited'
    try {
        const { recovery } = await setup(() => assert.fail('must not retry'))
        const router = { replace: () => assert.fail('must preserve the draft') }
        await assert.rejects(createAuthFailureHandler(router, recovery)({ config: { method: 'get' }, response: { status: 401 } }))
        assert.equal(draft.value, 'edited')
        assert.equal(recovery.dialogOpen.value, true)
    } finally { guard.dispose() }
})

test('a read with invalid CSRF and expired authentication returns home', async () => {
    const { recovery } = await setup(async () => { throw { response: { status: 401 } } })
    const destinations = []
    const handler = createAuthFailureHandler({ replace: path => destinations.push(path) }, recovery, () => false)
    await assert.rejects(handler({ config: { method: 'get' }, response: { status: 419 } }))
    assert.deepEqual(destinations, ['/'])
    assert.equal(recovery.state.value, 'idle')
    assert.equal(recovery.dialogOpen.value, false)
})

test('419 with an authenticated session refreshes CSRF without signing out or resending the operation', async () => {
    const { handler, recovery, calls, account } = await setup(async () => ({ data: { data: { id: 1 } } }))
    const failure = { response: { status: 419 } }
    await assert.rejects(handler(failure), error => error === failure)
    assert.deepEqual(calls, ['/api/user', '/sanctum/csrf-cookie'])
    assert.equal(account.user.value.id, 1)
    assert.equal(recovery.state.value, 'ready')
    assert.equal(recovery.dialogOpen.value, false)
    assert.equal(recovery.blocksRequests.value, false)
})

test('419 requires reauthentication only when the account lookup confirms 401', async () => {
    const { handler, recovery, account, calls } = await setup(async () => { throw { response: { status: 401 } } })
    const failure = { response: { status: 419 } }
    await assert.rejects(handler(failure), error => error === failure)
    assert.equal(account.user.value, null)
    assert.equal(recovery.state.value, 'expired')
    assert.equal(recovery.dialogOpen.value, true)
    assert.deepEqual(calls, ['/api/user'])
})

test('a network failure during CSRF recovery keeps the account and allows a manual check', async () => {
    const { handler, recovery, account } = await setup(async () => { throw new Error('offline') })
    await assert.rejects(handler({ response: { status: 419 } }))
    assert.equal(account.user.value.id, 1)
    assert.equal(recovery.state.value, 'check-failed')
    assert.equal(recovery.requiresLogin.value, false)
    assert.equal(recovery.blocksRequests.value, true)
})

test('a different account cannot resume the original draft; the original account can recover', async () => {
    let id = 2
    const { handler, recovery, account, calls } = await setup(async () => ({ data: { data: { id } } }))
    await assert.rejects(handler({ response: { status: 401 } }))
    await recovery.check()
    assert.equal(recovery.state.value, 'account-mismatch')
    assert.equal(recovery.blocksRequests.value, true)
    assert.equal(account.user.value, null)
    assert.deepEqual(calls, ['/api/user'])
    let submissions = 0
    const client = axios.create({ adapter: async config => { submissions++; return { status: 200, data: {}, config } } })
    client.interceptors.request.use(createSessionRequestGuard(recovery))
    await assert.rejects(client.post('/api/items', { name: '原帳號草稿' }), error => error.code === 'SESSION_RECOVERY_REQUIRED')
    assert.equal(submissions, 0)
    id = 1
    await recovery.check()
    assert.equal(account.user.value.id, 1)
    assert.equal(recovery.state.value, 'ready')
    assert.equal(recovery.blocksRequests.value, false)
    assert.equal(recovery.dialogOpen.value, false)
    assert.equal(submissions, 0)
    await client.post('/api/items', { name: '原帳號草稿' })
    assert.equal(submissions, 1)
})

test('checking an expired session again does not reopen a dismissed dialog', async () => {
    const { recovery } = await setup(async () => { throw { response: { status: 401 } } })
    recovery.expire()
    recovery.dialogOpen.value = false
    await recovery.check()
    assert.equal(recovery.state.value, 'expired')
    assert.equal(recovery.dialogOpen.value, false)
})

test('concurrent checks share one request and a failed check keeps expired requests blocked', async () => {
    let reject
    const { recovery } = await setup(() => new Promise((resolve, fail) => { reject = fail }))
    recovery.expire()
    const first = recovery.check(), second = recovery.check()
    assert.equal(first, second)
    assert.equal(recovery.blocksRequests.value, true)
    reject(new Error('offline'))
    await first
    assert.equal(recovery.state.value, 'expired')
    assert.equal(recovery.blocksRequests.value, true)
})

test('permission, validation and network errors do not start session recovery', async () => {
    for (const failure of [{ response: { status: 403 } }, { response: { status: 422 } }, new Error('offline')]) {
        const { handler, recovery, calls } = await setup(() => assert.fail('unexpected lookup'))
        await assert.rejects(handler(failure), error => error === failure)
        assert.equal(recovery.state.value, 'idle')
        assert.deepEqual(calls, [])
    }
})

test('unverified accounts are still directed to email verification', async () => {
    const destinations = []
    const handler = createAuthFailureHandler({ push: target => destinations.push(target) }, {})
    const failure = { response: { status: 403, data: { code: 'email_verification_required' } } }
    await assert.rejects(handler(failure), error => error === failure)
    assert.deepEqual(destinations, ['/verify-email'])
})
