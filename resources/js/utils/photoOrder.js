// 依 DOM 的排序結果重排資料，保留上傳進度、UUID 與原本的物件參照。
export const orderPhotosByIds = (photos, ids) => {
    const remaining = new Map(photos.map(photo => [String(photo.id), photo]))
    const ordered = []
    for (const id of ids) {
        const key = String(id)
        if (!remaining.has(key)) continue
        ordered.push(remaining.get(key))
        remaining.delete(key)
    }
    return [...ordered, ...remaining.values()]
}
