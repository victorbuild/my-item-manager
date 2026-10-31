import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { parse, compileScript } from '@vue/compiler-sfc'
import { ref } from 'vue'

const { descriptor } = parse(readFileSync(new URL('../../resources/js/Pages/Categories/Form.vue', import.meta.url), 'utf8'))
const source = compileScript(descriptor, { id: 'category-form-test' }).content.replace(/^import .*\n/gm, '').replace('export default', 'return')
function setup(id, fail = false) {
    let mounted
    const calls = [], dialogs = [], destinations = []
    const bindings = {
        ref, onMounted: fn => { mounted = fn }, AppButton: {},
        useRoute: () => ({ params: { id } }),
        useRouter: () => ({ push: url => destinations.push(url) }),
        Swal: { fire: async options => { dialogs.push(options) } },
        axios: {
            get: async () => ({ data: { items: [{ name: '原分類' }] } }),
            post: async (url, payload) => { calls.push({ method: 'post', url, payload }); if (fail) throw { response: { data: { errors: { name: ['分類名稱重複'] } } } } },
            put: async (url, payload) => { calls.push({ method: 'put', url, payload }) },
        },
    }
    const component = new Function(...Object.keys(bindings), source)(...Object.values(bindings))
    const vm = component.setup({}, { expose() {} })
    return { vm, calls, dialogs, destinations, mounted: () => mounted() }
}

test('category creation retains its endpoint, payload and success destination', async () => {
    const state = setup()
    await state.mounted()
    state.vm.form.value.name = '新分類'
    await state.vm.submitForm()
    assert.deepEqual(state.calls, [{ method: 'post', url: '/api/categories', payload: { name: '新分類' } }])
    assert.equal(state.dialogs[0].icon, 'success')
    assert.deepEqual(state.destinations, ['/categories'])
})
test('category editing loads and updates the same category', async () => {
    const state = setup('15')
    await state.mounted()
    assert.equal(state.vm.form.value.name, '原分類')
    state.vm.form.value.name = '更新分類'
    await state.vm.submitForm()
    assert.deepEqual(state.calls, [{ method: 'put', url: '/api/categories/15', payload: { name: '更新分類' } }])
    assert.deepEqual(state.destinations, ['/categories/15'])
})
test('category validation failure preserves the entered name and reports the existing error', async () => {
    const state = setup(undefined, true)
    await state.mounted()
    state.vm.form.value.name = '新分類'
    await state.vm.submitForm()
    assert.equal(state.vm.form.value.name, '新分類')
    assert.equal(state.dialogs[0].text, '分類名稱重複')
    assert.deepEqual(state.destinations, [])
})
