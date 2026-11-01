import { dialogClasses } from './dialogClasses.js'

// 共用刪除確認：資料只放入 text，請求失敗時保留彈窗供重試。
export async function confirmDeletion(api, dialog, { title, text, confirmButtonText, url, errorMessage, isActive = () => true }) {
    const result = await dialog.fire({
        title,
        text,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText,
        cancelButtonText: '取消',
        focusCancel: true,
        customClass: { ...dialogClasses, confirmButton: 'app-dialog-confirm app-dialog-confirm-danger' },
        showLoaderOnConfirm: true,
        allowOutsideClick: () => !dialog.isLoading(),
        allowEscapeKey: () => !dialog.isLoading(),
        preConfirm: async () => {
            if (!isActive()) return false
            try {
                await api.delete(url)
                return isActive()
            } catch {
                if (isActive()) dialog.showValidationMessage(errorMessage)
                return false
            }
        },
    })
    return result.isConfirmed && result.value === true
}
