import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { ref } from 'vue'
import { imageUuidKey } from '../../resources/js/utils/itemImageSelection.js'

const source = readFileSync(new URL('../../resources/js/Pages/Items/Create.vue', import.meta.url), 'utf8')
const queueSource = source.slice(source.indexOf('const startUploadQueue ='), source.indexOf('// 媒體櫃相關函數'))
const createQueue = post => {
    const uploadList = ref([])
    const imageFeedback = ref('')
    const controllers = new Map()
    const queue = new Function('uploadList', 'imageFeedback', 'uploadControllers', 'axios', 'imageUuidKey', `let pageActive = true; let uploadQueueRunning = false; ${queueSource}; return { startUploadQueue, retryImage }`)(uploadList, imageFeedback, controllers, { post }, imageUuidKey)
    const add = id => { uploadList.value.push({ id, status: 'waiting', file: new Blob(['image']), preview: '', progress: 0 }); return uploadList.value.at(-1) }
    return { ...queue, uploadList, imageFeedback, controllers, add }
}
const response = uuid => ({ data: { data: { uuid } } })

test('starts only one upload runner and distinguishes transmission from processing', async () => {
    let release
    let calls = 0
    const queue = createQueue(async (_, body, config) => {
        calls++
        config.onUploadProgress({ loaded: 1, total: 1 })
        if (calls === 1) await new Promise(resolve => { release = resolve })
        return response(`image-${calls}`)
    })
    const first = queue.add(1)
    queue.add(2)
    const running = queue.startUploadQueue()
    await queue.startUploadQueue()
    assert.equal(calls, 1)
    assert.equal(first.status, 'processing')
    release()
    await running
    assert.equal(calls, 2)
    assert.ok(queue.uploadList.value.every(image => image.status === 'done' && image.file === null))
    assert.equal(queue.controllers.size, 0)
})

test('retains a failed file and allows retry without selecting it again', async () => {
    let fail = true
    const queue = createQueue(async () => {
        if (fail) throw new Error('network')
        return response('photo')
    })
    const image = queue.add(1)
    await queue.startUploadQueue()
    assert.equal(image.status, 'error')
    assert.ok(image.file instanceof Blob)
    fail = false
    queue.retryImage(image)
    await new Promise(resolve => setImmediate(resolve))
    assert.equal(image.status, 'done')
    assert.equal(image.uuid, 'photo')
})

test('does not restore a removed photo after its upload response arrives', async () => {
    let release
    const queue = createQueue(() => new Promise(resolve => { release = resolve }))
    queue.add(1)
    const running = queue.startUploadQueue()
    queue.uploadList.value = []
    release(response('photo'))
    await running
    assert.deepEqual(queue.uploadList.value, [])
})

test('deduplicates a photo selected from media while its upload response is pending', async () => {
    let release
    const queue = createQueue(() => new Promise(resolve => { release = resolve }))
    queue.add(1)
    const running = queue.startUploadQueue()
    queue.uploadList.value.push({ id: 2, uuid: 'PHOTO', status: 'done' })
    release(response('photo'))
    await running
    assert.equal(queue.uploadList.value.length, 1)
    assert.equal(queue.uploadList.value[0].uuid, 'photo')
    assert.ok(queue.imageFeedback.value)
})
