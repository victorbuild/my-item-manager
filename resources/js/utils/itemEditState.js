import { getUniqueItemImagePayload, imageUuidKey } from './itemImageSelection.js'
import { getItemCreationSummary } from './itemCreationSummary.js'
import { getDateTextError } from './dateTextInput.js'
import { getDateMin, getDateMax } from './itemDates.js'

export const ITEM_EDIT_DATE_FIELDS = ['purchased_at', 'received_at', 'expiration_date', 'used_at', 'discarded_at']
const textFields = ['name', 'description', 'location']

export const createItemEditForm = item => Object.fromEntries([
    ...textFields.map(field => [field, item[field] ?? '']),
    ['price', item.price == null ? '' : String(item.price)],
    ...ITEM_EDIT_DATE_FIELDS.map(field => [field, item[field] ?? '']),
])

export const getItemEditImagePayload = (photos, originalPhotos) => {
    const originalIds = new Set(originalPhotos.map(photo => imageUuidKey(photo.uuid)))
    const active = getUniqueItemImagePayload(photos).map(photo => ({
        uuid: photo.uuid,
        status: originalIds.has(imageUuidKey(photo.uuid)) ? 'original' : 'new',
    }))
    const activeIds = new Set(active.map(photo => imageUuidKey(photo.uuid)))
    const removed = []
    for (const photo of originalPhotos) {
        const key = imageUuidKey(photo.uuid)
        if (!key || activeIds.has(key)) continue
        activeIds.add(key)
        removed.push({ uuid: photo.uuid, status: 'removed' })
    }
    return [...active, ...removed]
}

export const getItemEditPrice = price => getItemCreationSummary({ quantity: 1, maxQuantity: 1, price, priceMode: 'unit' })

export const getItemEditErrors = (form, dateDrafts = {}, purchaseMax) => {
    const errors = {}
    if (!form.name.trim()) errors.name = '請填寫物品名稱。'
    else if (form.name.length > 255) errors.name = '物品名稱最多 255 個字元。'
    if (form.location.length > 255) errors.location = '位置最多 255 個字元。'
    const price = getItemEditPrice(form.price)
    if (!price.valid) errors.price = price.message
    for (const field of ITEM_EDIT_DATE_FIELDS) {
        if (dateDrafts[field] != null) {
            errors[field] = '請完成日期輸入，按 Enter 或移開焦點確認。'
            continue
        }
        if (field === 'purchased_at' && !form[field]) {
            errors[field] = '請填寫購買日期。'
            continue
        }
        const error = getDateTextError(form[field],
            field === 'expiration_date' ? undefined : getDateMin(form, field),
            field === 'expiration_date' ? '9999-12-31' : field === 'purchased_at' && purchaseMax ? purchaseMax : getDateMax(form, field))
        if (error) errors[field] = error
    }
    return errors
}

export const createItemEditPayload = (form, photos, originalPhotos, product = undefined) => ({
    ...createItemEditForm(form),
    price: getItemEditPrice(form.price).unitPrice,
    images: getItemEditImagePayload(photos, originalPhotos),
    ...(product === undefined ? {} : { product_id: product?.id ?? null }),
})
