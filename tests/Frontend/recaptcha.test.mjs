import test from 'node:test'
import assert from 'node:assert/strict'
import { getRecaptchaToken, loadRecaptchaScript } from '../../resources/js/utils/recaptcha.js'

test('ready verification reuses the service and sends the correct action', async () => {
    const saved = globalThis.window
    const calls = []
    globalThis.window = { grecaptcha: { ready: callback => callback(), execute: async (key, options) => { calls.push({ key, options }); return 'token' } } }
    try {
        assert.equal(await getRecaptchaToken('public-key', 'login'), 'token')
        assert.deepEqual(calls, [{ key: 'public-key', options: { action: 'login' } }])
    } finally { globalThis.window = saved }
})
test('verification script failure cleans up and the next attempt can reload', async () => {
    const savedWindow = globalThis.window, savedDocument = globalThis.document
    const scripts = []
    globalThis.window = {}
    globalThis.document = {
        getElementById: () => null,
        createElement: () => {
            const listeners = new Map()
            const script = { addEventListener: (event, callback) => listeners.set(event, callback), removeEventListener: event => listeners.delete(event), remove() { this.removed = true }, listeners }
            scripts.push(script); return script
        },
        head: { appendChild() {} },
    }
    try {
        const first = loadRecaptchaScript('public-key')
        assert.equal(loadRecaptchaScript('public-key'), first)
        scripts[0].listeners.get('error')()
        await assert.rejects(first, /驗證服務/)
        assert.equal(scripts[0].removed, true)
        const second = loadRecaptchaScript('public-key')
        assert.equal(scripts.length, 2)
        scripts[1].listeners.get('load')()
        await second
    } finally { globalThis.window = savedWindow; globalThis.document = savedDocument }
})
