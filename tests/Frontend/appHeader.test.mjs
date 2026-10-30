import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { parse, compileScript } from '@vue/compiler-sfc'
import { computed, ref, watch, reactive, effectScope } from 'vue'
const { descriptor } = parse(readFileSync(new URL('../../resources/js/Components/AppHeader.vue', import.meta.url), 'utf8'))
const source = compileScript(descriptor, { id: 'header' }).content.replace(/^import .*\n/gm, '').replace('export default', 'return')
const run = async (verify, { block = false, post = async () => {} } = {}) => {
    const scope = effectScope(), route = reactive({ path: '/items/a/edit' }), calls = []
    const user = ref({ id: 1, name: 'victor' })
    const account = { user, isLoggedIn: computed(() => !!user.value), loading: ref(false), error: ref(''), load() {}, clear() { calls.push('clear'); user.value = null } }
    const router = { options: { history: { state: {} } }, async push(path) { calls.push(path); if (block) return { type: 4 }; route.path = path }, back() {} }
    const bindings = { computed, ref, watch, useRoute: () => route, useRouter: () => router, useCurrentUser: () => account,
        axios: { post: async url => { calls.push(url); return post() } }, AppIcon: {}, ChevronDown: {}, LogOut: {},
        ...Object.fromEntries(['Root', 'Trigger', 'Portal', 'Content', 'Label', 'Separator', 'Item'].map(key => [`DropdownMenu${key}`, {}])) }
    const component = new Function(...Object.keys(bindings), source)(...Object.values(bindings))
    let vm
    scope.run(() => { vm = component.setup({}, { expose() {} }) })
    try { await verify({ vm, calls, route, account }) } finally { scope.stop() }
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
