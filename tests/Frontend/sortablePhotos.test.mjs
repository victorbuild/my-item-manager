import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { ref } from 'vue'
import { orderPhotosByIds } from '../../resources/js/utils/photoOrder.js'

const source = readFileSync(new URL('../../resources/js/composables/useSortablePhotos.js', import.meta.url), 'utf8')
    .replace(/^import .*\n/gm, '').replace('export const', 'const')
const setup = () => {
    let mount, unmount, options, destroyed = false
    const photos = ref([{ id: 1, uuid: 'one', progress: 20 }, { id: 2, uuid: 'two', progress: 100 }, { id: 3, uuid: 'three', progress: 80 }])
    const previews = []
    const tiles = photos.value.map(photo => ({ dataset: { photoId: String(photo.id) }, classList: { contains: name => name === 'photo-tile' } }))
    const anchor = { classList: { contains: () => false } }
    const root = { children: [...tiles, anchor], querySelector: () => anchor, insertBefore(tile, before) {
        this.children.splice(this.children.indexOf(tile), 1)
        this.children.splice(this.children.indexOf(before), 0, tile)
    } }
    const sortable = { toArray: () => root.children.filter(node => node !== anchor).map(node => node.dataset.photoId), destroy: () => { destroyed = true } }
    const factory = new Function('ref', 'onMounted', 'onBeforeUnmount', 'Sortable', 'orderPhotosByIds', 'window', `${source}; return useSortablePhotos`)(
        ref, callback => { mount = callback }, callback => { unmount = callback }, { create: (_, config) => { options = config; return sortable } }, orderPhotosByIds, { matchMedia: () => ({ matches: true }) })
    const api = factory(photos, photo => previews.push(photo.id))
    api.photoGrid.value = root
    mount()
    return { ...api, photos, previews, root, tiles, anchor, options, unmount, get destroyed() { return destroyed } }
}

test('sorting preserves image identity and upload state, tolerating missing or duplicated DOM IDs', () => {
    const photos = [{ id: 1, uuid: 'a', progress: 20 }, { id: 2, uuid: 'b' }, { id: 3, uuid: 'c' }]
    const ordered = orderPhotosByIds(photos, ['2', '2', 'missing', '1'])
    assert.deepEqual(ordered.map(photo => photo.id), [2, 1, 3])
    assert.equal(ordered[1], photos[0])
    assert.equal(ordered[1].progress, 20)
})

test('commits the visual order while restoring DOM for Vue keyed rendering', () => {
    const vm = setup()
    const second = vm.photos.value[1]
    vm.root.children = [vm.tiles[1], vm.tiles[0], vm.tiles[2], vm.anchor]
    vm.options.onStart()
    vm.options.onEnd({ item: vm.tiles[1] })
    assert.deepEqual(vm.photos.value.map(photo => photo.id), [2, 1, 3])
    assert.equal(vm.photos.value[0], second)
    assert.deepEqual(vm.root.children.slice(0, 3), vm.tiles)
    assert.match(vm.reorderNotice.value, /1/)
    vm.onPreviewClick(second, { detail: 1 })
    assert.deepEqual(vm.previews, [])
    assert.equal(vm.options.animation, 0)
    vm.unmount()
    assert.equal(vm.destroyed, true)
})

test('an ordinary desktop click still opens preview, while touch long-press does not', () => {
    const vm = setup()
    const first = vm.photos.value[0]
    vm.options.onChoose({ originalEvent: { type: 'mousedown' } })
    vm.options.onUnchoose()
    vm.onPreviewClick(first, { detail: 1 })
    assert.deepEqual(vm.previews, [1])
    vm.options.onChoose({ originalEvent: { type: 'touchstart' } })
    vm.options.onUnchoose()
    vm.onPreviewClick(first, { detail: 1 })
    assert.deepEqual(vm.previews, [1])
    vm.onPreviewClick(first, { detail: 0 })
    assert.deepEqual(vm.previews, [1, 1])
})

test('keyboard users can reorder without a visible drag handle', () => {
    const vm = setup()
    const first = vm.photos.value[0]
    vm.movePhotoWithKeyboard(first, { key: 'ArrowRight', preventDefault() {} })
    assert.deepEqual(vm.photos.value.map(photo => photo.id), [2, 1, 3])
    vm.movePhotoWithKeyboard(first, { key: 'ArrowLeft', preventDefault() {} })
    vm.movePhotoWithKeyboard(first, { key: 'ArrowLeft', preventDefault() {} })
    assert.deepEqual(vm.photos.value.map(photo => photo.id), [1, 2, 3])
})
