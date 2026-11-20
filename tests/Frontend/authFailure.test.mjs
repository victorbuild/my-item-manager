import test from 'node:test'
import assert from 'node:assert/strict'
import { createAuthFailureHandler } from '../../resources/js/utils/authFailure.js'
import { createCurrentUserState } from '../../resources/js/utils/currentUser.js'

for (const status of [401, 419]) {
    test(`${status} clears the account and guides the user to sign in without retrying the operation`, async () => {
        const account = createCurrentUserState({ get: async () => ({ data: { data: { id: 1 } } }) })
        await account.load()
        const destinations = []
        const handler = createAuthFailureHandler({ replace: target => destinations.push(target) }, account)
        const failure = { response: { status } }

        await assert.rejects(handler(failure), error => error === failure)

        assert.equal(account.isLoggedIn.value, false)
        assert.deepEqual(destinations, [{ path: '/login', query: { reason: 'session-expired' } }])
    })
}

test('permission, validation and network errors do not clear the account or redirect', async () => {
    for (const failure of [{ response: { status: 403 } }, { response: { status: 422 } }, new Error('offline')]) {
        const handler = createAuthFailureHandler({ replace: () => assert.fail('unexpected redirect'), push: () => assert.fail('unexpected redirect') }, { clear: () => assert.fail('unexpected sign out') })
        await assert.rejects(handler(failure), error => error === failure)
    }
})

test('unverified accounts are still directed to email verification', async () => {
    const destinations = []
    const handler = createAuthFailureHandler({ push: target => destinations.push(target) }, { clear: () => assert.fail('unexpected sign out') })
    const failure = { response: { status: 403, data: { code: 'email_verification_required' } } }
    await assert.rejects(handler(failure), error => error === failure)
    assert.deepEqual(destinations, ['/verify-email'])
})
