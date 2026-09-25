const DNG_MIME_TYPES = new Set(['image/dng', 'image/x-adobe-dng', 'image/x-dng'])

const TIFF_TYPE_SIZES = {
    1: 1, // BYTE
    2: 1, // ASCII
    3: 2, // SHORT
    4: 4, // LONG
    7: 1, // UNDEFINED
    9: 4, // SLONG
    13: 4, // IFD
}

export const isDngFile = (file) => {
    const extension = file.name.split('.').pop()?.toLowerCase()
    return extension === 'dng' || DNG_MIME_TYPES.has(file.type.toLowerCase())
}

export const isSupportedImageFile = (file) => file.type.startsWith('image/') || isDngFile(file)

const readEntryValues = (view, littleEndian, type, count, valueOffset, entryOffset) => {
    const typeSize = TIFF_TYPE_SIZES[type]
    if (!typeSize || count < 1 || count > 1024) return []

    const byteLength = typeSize * count
    const dataOffset = byteLength <= 4 ? entryOffset + 8 : valueOffset
    if (dataOffset < 0 || dataOffset + byteLength > view.byteLength) return []

    const values = []
    for (let index = 0; index < count; index++) {
        const offset = dataOffset + (index * typeSize)
        if (type === 3) values.push(view.getUint16(offset, littleEndian))
        else if (type === 4 || type === 13) values.push(view.getUint32(offset, littleEndian))
        else if (type === 9) values.push(view.getInt32(offset, littleEndian))
        else values.push(view.getUint8(offset))
    }

    return values
}

/**
 * Extract the largest JPEG preview referenced by a classic TIFF/DNG IFD.
 * RAW pixels are never decoded or uploaded.
 */
export const extractDngJpegPreview = (arrayBuffer) => {
    const view = new DataView(arrayBuffer)
    if (view.byteLength < 8) throw new Error('DNG 檔案內容不完整')

    const byteOrder = String.fromCharCode(view.getUint8(0), view.getUint8(1))
    const littleEndian = byteOrder === 'II'
    if (!littleEndian && byteOrder !== 'MM') throw new Error('不是有效的 DNG/TIFF 檔案')
    if (view.getUint16(2, littleEndian) !== 42) throw new Error('目前不支援此 DNG 版本')

    const pendingIfds = [view.getUint32(4, littleEndian)]
    const visitedIfds = new Set()
    const candidates = []

    while (pendingIfds.length && visitedIfds.size < 64) {
        const ifdOffset = pendingIfds.shift()
        if (!ifdOffset || visitedIfds.has(ifdOffset) || ifdOffset + 2 > view.byteLength) continue
        visitedIfds.add(ifdOffset)

        const entryCount = view.getUint16(ifdOffset, littleEndian)
        if (entryCount > 4096 || ifdOffset + 2 + (entryCount * 12) + 4 > view.byteLength) continue

        const tags = new Map()
        for (let index = 0; index < entryCount; index++) {
            const entryOffset = ifdOffset + 2 + (index * 12)
            const tag = view.getUint16(entryOffset, littleEndian)
            const type = view.getUint16(entryOffset + 2, littleEndian)
            const count = view.getUint32(entryOffset + 4, littleEndian)
            const valueOffset = view.getUint32(entryOffset + 8, littleEndian)
            const values = readEntryValues(view, littleEndian, type, count, valueOffset, entryOffset)
            if (values.length) tags.set(tag, values)
        }

        const width = tags.get(0x0100)?.[0] ?? 0
        const height = tags.get(0x0101)?.[0] ?? 0
        const jpegOffset = tags.get(0x0201)?.[0]
        const jpegLength = tags.get(0x0202)?.[0]

        if (jpegOffset && jpegLength && jpegOffset + jpegLength <= view.byteLength) {
            const isJpeg = view.getUint8(jpegOffset) === 0xff && view.getUint8(jpegOffset + 1) === 0xd8
            if (isJpeg) candidates.push({ offset: jpegOffset, length: jpegLength, width, height })
        }

        // Some DNG previews are stored as one JPEG-compressed TIFF strip.
        const compression = tags.get(0x0103)?.[0]
        const stripOffsets = tags.get(0x0111) ?? []
        const stripByteCounts = tags.get(0x0117) ?? []
        if (compression === 7 && stripOffsets.length === 1 && stripByteCounts.length === 1) {
            const offset = stripOffsets[0]
            const length = stripByteCounts[0]
            const isJpeg = offset + length <= view.byteLength
                && view.getUint8(offset) === 0xff
                && view.getUint8(offset + 1) === 0xd8
            if (isJpeg) candidates.push({ offset, length, width, height })
        }

        for (const tag of [0x014a, 0x8769]) {
            for (const childIfd of tags.get(tag) ?? []) pendingIfds.push(childIfd)
        }

        pendingIfds.push(view.getUint32(ifdOffset + 2 + (entryCount * 12), littleEndian))
    }

    if (!candidates.length) throw new Error('這張 DNG 沒有可用的 JPEG 預覽圖')

    candidates.sort((a, b) => ((b.width * b.height) - (a.width * a.height)) || (b.length - a.length))
    const best = candidates[0]
    return arrayBuffer.slice(best.offset, best.offset + best.length)
}

export const prepareImageForUpload = async (file) => {
    if (!isDngFile(file)) return file

    const jpegBuffer = extractDngJpegPreview(await file.arrayBuffer())
    const basename = file.name.replace(/\.dng$/i, '') || 'image'

    return new File([jpegBuffer], `${basename}.jpg`, {
        type: 'image/jpeg',
        lastModified: file.lastModified,
    })
}
