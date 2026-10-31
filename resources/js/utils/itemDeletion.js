import { confirmDeletion } from './confirmDeletion.js'

export function confirmItemDeletion(api, dialog, item, isActive = () => true) {
    return confirmDeletion(api, dialog, {
        title: '刪除物品？',
        text: `確定永久刪除「${item.name}」？刪除後無法復原。如果只是不再使用，記錄棄用可以保留日期與回憶。`,
        confirmButtonText: '刪除物品',
        url: `/api/items/${item.short_id}`,
        errorMessage: '刪除失敗，請稍後重試。',
        isActive,
    })
}
