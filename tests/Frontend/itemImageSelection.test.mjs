import test from 'node:test'
import assert from 'node:assert/strict'
import { getAvailableItemMedia, getNewItemMedia, getUniqueItemImagePayload } from '../../resources/js/utils/itemImageSelection.js'
const a = '550e8400-e29b-41d4-a716-446655440000'
const b = '550e8400-e29b-41d4-a716-446655440001'

test('excludes an uploaded but not-yet-attached photo from this form media choices', () => {
    const media = [{ uuid: a }, { uuid: b }]
    assert.deepEqual(getAvailableItemMedia(media, [{ uuid: a }]), [{ uuid: b }])
    assert.deepEqual(getAvailableItemMedia(media, []), media)
})
test('handles duplicate selections and an upload completing while the library is open', () => {
    const media = [{ uuid: a }, { uuid: a.toUpperCase() }, { uuid: b }]
    assert.deepEqual(getNewItemMedia(media, [{ uuid: a }], [a, b, b]), [{ uuid: b }])
})
test('only sends unique completed UUIDs while retaining the chosen order', () => {
    const images = [{ uuid: b, status: 'done' }, { uuid: a, status: 'done' }, { uuid: a.toUpperCase(), status: 'done' }, { status: 'uploading' }]
    assert.deepEqual(getUniqueItemImagePayload(images), [{ uuid: b, status: 'new' }, { uuid: a, status: 'new' }])
})
test('does not prohibit the same media on a separate item or form', () => {
    assert.equal(getNewItemMedia([{ uuid: a }], [], [a]).length, 1)
    assert.equal(getNewItemMedia([{ uuid: a }], [], [a]).length, 1)
})
