import test from 'node:test'
import assert from 'node:assert/strict'
import { ref } from 'vue'
import { useItemPhotoUploads } from '../../resources/js/composables/useItemPhotoUploads.js'

const file = () => { const blob = new Blob(['photo'], { type: 'image/jpeg' }); blob.name = 'photo.jpg'; return blob }
const tick = () => new Promise(resolve => setImmediate(resolve))
const response = uuid => ({ data: { data: { uuid } } })
const setup = post => {
    const photos = ref([])
    const revoked = []
    let id = 0
    const queue = useItemPhotoUploads(photos, { client: { post }, prepare: async image => image, urls: { createObjectURL: () => `blob:${id++}`, revokeObjectURL: url => revoked.push(url) } })
    return { photos, revoked, ...queue }
}

test('processes uploads sequentially, distinguishes server processing, and preserves saved original rows', async () => {
    let release, calls = 0
    const vm = setup(async (_, data, config) => {
        const id = ++calls
        config.onUploadProgress({ loaded: 5, total: 5 })
        if (id === 1) await new Promise(resolve => { release = resolve })
        return response(`new-${id}`)
    })
    vm.photos.value = [{ id: 'original', uuid: 'existing', status: 'done' }]
    await vm.addFiles([file(), file()])
    assert.equal(calls, 1)
    assert.equal(vm.photos.value[1].status, 'processing')
    assert.equal(vm.photos.value[2].status, 'waiting')
    release()
    await tick()
    assert.deepEqual(vm.photos.value.map(photo => photo.uuid), ['existing', 'new-1', 'new-2'])
    vm.dispose()
    assert.equal(vm.revoked.length, 2)
})
test('can retry a failed upload and remove a pending upload without its response restoring the photo', async () => {
    let fail = true, release
    const vm = setup(async () => {
        if (fail) throw new Error('offline')
        return new Promise(resolve => { release = resolve })
    })
    await vm.addFiles([file()])
    await tick()
    const photo = vm.photos.value[0]
    assert.equal(photo.status, 'error')
    assert.ok(photo.file)
    fail = false
    vm.retryImage(photo)
    vm.removeImage(0)
    release(response('late'))
    await tick()
    assert.equal(vm.photos.value.length, 0)
    assert.deepEqual(vm.revoked, ['blob:0'])
    vm.dispose()
    assert.deepEqual(vm.revoked, ['blob:0'])
})
test('removing a photo during conversion does not create a preview or enqueue a request', async () => {
    const photos = ref([])
    let release, calls = 0
    const vm = useItemPhotoUploads(photos, { client: { post: () => { calls++ } }, prepare: () => new Promise(resolve => { release = resolve }) })
    const pending = vm.addFiles([file()])
    vm.removeImage(0)
    release(file())
    await pending
    assert.equal(calls, 0)
    assert.deepEqual(photos.value, [])
    vm.dispose()
})
test('does not duplicate a UUID added from media while an upload is pending', async () => {
    let release
    const vm = setup(() => new Promise(resolve => { release = resolve }))
    await vm.addFiles([file()])
    vm.photos.value.push({ id: 'media', uuid: 'SAME', status: 'done' })
    release(response('same'))
    await tick()
    assert.equal(vm.photos.value.length, 1)
    assert.equal(vm.photos.value[0].id, 'media')
    assert.ok(vm.imageFeedback.value)
    vm.dispose()
})
test('conversion failure remains visible as an error and disposal cancels active requests', async () => {
    const photos = ref([])
    const conversion = useItemPhotoUploads(photos, { prepare: async () => { throw new Error('Unsupported image') } })
    await conversion.addFiles([file()])
    assert.equal(photos.value[0].status, 'error')
    assert.equal(photos.value[0].errorMessage, 'Unsupported image')
    conversion.dispose()
    let signal
    const vm = setup((_, data, config) => { signal = config.signal; return new Promise(() => {}) })
    await vm.addFiles([file()])
    vm.dispose()
    assert.equal(signal.aborted, true)
    assert.deepEqual(vm.revoked, ['blob:0'])
})
