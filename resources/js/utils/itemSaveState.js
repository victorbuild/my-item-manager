import { isQuantityConfirmed } from './itemCreationSummary.js'

export const getItemSaveBlockReason = ({ name, purchasedAt, summary, confirmation, images, hasDateDrafts, dateError, productPending }) => {
    if (!String(name ?? '').trim()) return '請填寫物品名稱。'
    if (String(name).length > 255) return '物品名稱最多 255 個字元。'
    if (!purchasedAt) return '請填寫購買日期。'
    if (hasDateDrafts) return '請完成日期輸入，按 Enter 或移開焦點確認。'
    if (dateError) return dateError
    if (!summary.valid) return summary.message
    if (productPending) return '請先完成或取消建立產品。'
    if (images.some(image => image.status === 'error')) return '照片上傳失敗，請重試或移除後再儲存。'
    if (images.some(image => image.status !== 'done' || !image.uuid)) return '請等待所有照片上傳及處理完成。'
    if (!isQuantityConfirmed(summary.quantity, confirmation)) return '請輸入本次建立件數，完成確認後即可儲存。'
    return ''
}
