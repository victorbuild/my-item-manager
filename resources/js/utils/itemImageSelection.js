export const imageUuidKey = value => typeof value === 'string' ? value.toLowerCase() : ''

export const getAvailableItemMedia = (media, images) => {
    const added = new Set(images.map(image => imageUuidKey(image.uuid)).filter(Boolean))
    const seen = new Set()
    return media.filter(image => {
        const key = imageUuidKey(image.uuid)
        if (!key || added.has(key) || seen.has(key)) return false
        seen.add(key)
        return true
    })
}

export const getNewItemMedia = (media, images, selected) => {
    const wanted = new Set(selected.map(imageUuidKey))
    return getAvailableItemMedia(media, images).filter(image => wanted.has(imageUuidKey(image.uuid)))
}

export const getUniqueItemImagePayload = images => {
    const seen = new Set()
    return images.filter(image => {
        const key = imageUuidKey(image.uuid)
        if (image.status !== 'done' || !key || seen.has(key)) return false
        seen.add(key)
        return true
    }).map(image => ({ uuid: image.uuid, status: image.statusFromApi || 'new' }))
}
