import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { parse, compileScript } from '@vue/compiler-sfc'
import { computed, ref, watch, reactive, effectScope } from 'vue'
const { descriptor } = parse(readFileSync(new URL('../../resources/js/Components/AppHeader.vue', import.meta.url), 'utf8'))
const source = compileScript(descriptor, { id: 'header' }).content.replace(/^import .*\n/gm, '').replace('export default', 'return')
const run = async (verify, { block = false, post = async () => {}, initialPath = '/items/a/edit', unverified = false } = {}) => {
    const scope = effectScope(), route = reactive({ path: initialPath }), calls = [], accountLoads = []
    const user = ref({ id: 1, name: 'victor' })
    const account = { user, isLoggedIn: computed(() => !!user.value), loading: ref(false), error: ref(''), load(options) { accountLoads.push(options) }, clear() { calls.push('clear'); user.value = null } }
    const router = { options: { history: { state: {} } }, async push(path) { calls.push(path); if (block || (unverified && path === '/')) return { type: 4 }; route.path = path }, back() {} }
    const bindings = { computed, ref, watch, useRoute: () => route, useRouter: () => router, useCurrentUser: () => account,
        axios: { post: async url => { calls.push(url); return post() } }, AppIcon: {}, ChevronDown: {}, LogOut: {},
        ...Object.fromEntries(['Root', 'Trigger', 'Portal', 'Content', 'Label', 'Separator', 'Item'].map(key => [`DropdownMenu${key}`, {}])) }
    const component = new Function(...Object.keys(bindings), source)(...Object.values(bindings))
    let vm
    scope.run(() => { vm = component.setup({}, { expose() {} }) })
    try { await verify({ vm, calls, route, account, accountLoads }) } finally { scope.stop() }
}
test('cancelling an unsaved route leave never sends logout or clears the account', async () => {
    await run(async ({ vm, calls, account }) => {
        await vm.logout()
        assert.deepEqual(calls, ['/'])
        assert.equal(account.user.value.id, 1)
        assert.equal(vm.signingOut.value, false)
    }, { block: true })
})
test('logout waits for navigation approval and server success before clearing account and opening login', async () => {
    let release
    await run(async ({ vm, calls }) => {
        const pending = vm.logout()
        await Promise.resolve()
        await vm.logout()
        assert.deepEqual(calls, ['/', '/logout'])
        release()
        await pending
        assert.deepEqual(calls, ['/', '/logout', 'clear', '/login'])
        assert.equal(vm.signingOut.value, false)
    }, { post: () => new Promise(resolve => { release = resolve }) })
})
test('failed logout keeps the account and provides a retry message', async () => {
    await run(async ({ vm, calls, account }) => {
        await vm.logout()
        assert.deepEqual(calls, ['/', '/logout'])
        assert.equal(account.user.value.id, 1)
        assert.ok(vm.logoutError.value)
    }, { post: async () => { throw new Error('offline') } })
})

test('unverified users can log out without being redirected back by the home verification guard', async () => {
    await run(async ({ vm, calls, account, route }) => {
        account.user.value.email_verified_at = null
        await vm.logout()
        assert.deepEqual(calls, ['/login', '/logout', 'clear', '/login'])
        assert.equal(account.user.value, null)
        assert.equal(route.path, '/login')
        assert.equal(vm.logoutError.value, '')
    }, { initialPath: '/verify-email', unverified: true })
})

test('logout navigation does not start an authenticated account request before ending the session', async () => {
    let release
    await run(async ({ vm, accountLoads, account }) => {
        account.user.value.email_verified_at = null
        const initialLoads = accountLoads.length
        const pending = vm.logout()
        await Promise.resolve()
        await Promise.resolve()
        assert.equal(accountLoads.length, initialLoads)
        release()
        await pending
        await Promise.resolve()
        assert.equal(account.user.value, null)
        assert.equal(accountLoads.length, initialLoads)
    }, { initialPath: '/verify-email', unverified: true, post: () => new Promise(resolve => { release = resolve }) })
})
