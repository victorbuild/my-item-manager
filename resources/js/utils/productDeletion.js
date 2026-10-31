import { confirmDeletion } from './confirmDeletion.js'

export function confirmProductDeletion(api, dialog, product) {
    return confirmDeletion(api, dialog, {
        title: '刪除產品？',
        text: `「${product.name}」只有在沒有關聯物品時才能刪除。刪除後無法復原。`,
        confirmButtonText: '刪除產品',
        url: `/api/products/${product.short_id}`,
        errorMessage: '刪除失敗，請確認產品沒有關聯物品，或稍後再試。',
    })
}
