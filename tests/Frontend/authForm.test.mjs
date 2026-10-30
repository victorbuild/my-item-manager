import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { computed, nextTick, reactive, ref } from 'vue'
const source = readFileSync(new URL('../../resources/js/composables/useAuthForm.js', import.meta.url), 'utf8')
    .replace(/^import .*\n/gm, '').replace('export const useAuthForm =', 'return ')
    .replace('import.meta.env.VITE_RECAPTCHA_SITE_KEY', "'test-public-site-key'")
const run = async (mode, verify, { post = async () => {}, storageFails = false, captchaEnabled = false, captcha = async () => 'verification-token' } = {}) => {
    const savedDocument = globalThis.document, savedStorage = globalThis.localStorage
    const calls = [], cleanups = []
    globalThis.document = { querySelector: () => ({ content: String(captchaEnabled) }) }
    globalThis.localStorage = { setItem() { if (storageFails) throw new Error('blocked') } }
    const useAuthForm = new Function('computed', 'nextTick', 'onMounted', 'onBeforeUnmount', 'reactive', 'ref', 'useRouter', 'axios', 'getRecaptchaToken', 'loadRecaptchaScript', source)(
        computed, nextTick, () => {}, callback => cleanups.push(callback), reactive, ref, () => ({ push: async path => calls.push({ path }) }),
        { get: async url => calls.push({ url }), post: async (url, payload) => { calls.push({ url, payload }); return post() } },
        async (key, action) => { calls.push({ action }); return captcha() }, async () => {})
    const vm = useAuthForm(mode)
    try { await verify({ vm, calls, unmount: () => cleanups.forEach(callback => callback()) }) }
    finally { globalThis.document = savedDocument; globalThis.localStorage = savedStorage }
}
test('login keeps the existing CSRF and credential request flow without sending registration fields', async () => {
    await run('login', async ({ vm, calls }) => {
        Object.assign(vm.form, { email: ' member@example.test ', password: 'test-password' })
        await vm.submit()
        assert.deepEqual(calls, [{ url: '/sanctum/csrf-cookie' }, { url: '/login', payload: { email: 'member@example.test', password: 'test-password', recaptcha_token: null } }, { path: '/' }])
        assert.equal(vm.form.password, '')
    }, { storageFails: true })
})
test('register sends its own verification action and confirmation to the existing endpoint', async () => {
    await run('register', async ({ vm, calls }) => {
        Object.assign(vm.form, { name: ' 會員 ', email: 'member@example.test', password: 'test-password', password_confirmation: 'test-password' })
        await vm.submit()
        assert.deepEqual(calls[0], { action: 'register' })
        assert.deepEqual(calls[2], { url: '/register', payload: { name: '會員', email: 'member@example.test', password: 'test-password', password_confirmation: 'test-password', recaptcha_token: 'verification-token' } })
        assert.equal(vm.form.password_confirmation, '')
    }, { captchaEnabled: true })
})
test('failed authentication preserves fields and focuses the error summary instead of an alert', async () => {
    await run('login', async ({ vm, calls }) => {
        let focused = false
        vm.errorSummary.value = { focus() { focused = true } }
        Object.assign(vm.form, { email: 'member@example.test', password: 'retry-password' })
        await vm.submit()
        assert.equal(vm.form.email, 'member@example.test')
        assert.equal(vm.form.password, 'retry-password')
        assert.equal(vm.errors.value.email, '登入資料不正確')
        assert.equal(focused, true)
        assert.equal(calls.some(call => call.path), false)
        assert.equal(vm.submitting.value, false)
    }, { post: async () => { throw { response: { data: { message: '請確認登入資料', errors: { email: ['登入資料不正確'] } } } } } })
})
test('authentication does not send duplicate submissions while a request is pending', async () => {
    let release
    await run('login', async ({ vm, calls }) => {
        const pending = vm.submit()
        await Promise.resolve()
        await vm.submit()
        assert.equal(calls.filter(call => call.url === '/login').length, 1)
        release()
        await pending
    }, { post: () => new Promise(resolve => { release = resolve }) })
})
test('verification failure never proceeds to credential submission and allows retry', async () => {
    await run('register', async ({ vm, calls }) => {
        await vm.submit()
        assert.deepEqual(calls, [{ action: 'register' }])
        assert.match(vm.errorMessage.value, /驗證/)
        assert.equal(vm.submitting.value, false)
    }, { captchaEnabled: true, captcha: async () => { throw new Error('驗證失敗，請再試一次。') } })
})

test('leaving the authentication page prevents a late successful response from redirecting it', async () => {
    let release
    await run('login', async ({ vm, calls, unmount }) => {
        const pending = vm.submit()
        await Promise.resolve()
        unmount()
        release()
        await pending
        assert.equal(calls.some(call => call.path), false)
    }, { post: () => new Promise(resolve => { release = resolve }) })
})
