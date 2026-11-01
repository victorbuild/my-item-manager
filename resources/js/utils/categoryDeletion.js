import { confirmDeletion } from './confirmDeletion.js'

export function confirmCategoryDeletion(api, dialog, category) {
    return confirmDeletion(api, dialog, {
        title: '刪除分類？',
        text: `「${category.name}」只有在沒有關聯產品時才能刪除。刪除後無法復原。`,
        confirmButtonText: '刪除分類',
        url: `/api/categories/${category.id}`,
        errorMessage: '刪除失敗，請確認分類沒有關聯產品，或稍後再試。',
    })
}
