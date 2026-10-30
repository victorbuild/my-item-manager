import dayjs from 'dayjs'
import { parseDateText } from './dateTextInput.js'

export const ITEM_SHOW_DATES = [
    { key: 'purchased_at', label: '購買日期' },
    { key: 'received_at', label: '到貨日期' },
    { key: 'used_at', label: '開始使用日期' },
    { key: 'expiration_date', label: '有效期限' },
    { key: 'discarded_at', label: '棄用日期' },
]
const valid = value => typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value) && !!parseDateText(value)
const elapsed = (from, to, inclusive = false) => valid(from) && valid(to) && to >= from
    ? dayjs(to).diff(dayjs(from), 'day') + Number(inclusive) : null

export const getItemNextAction = (item, today) => {
    if (item.discarded_at) return item.discarded_at > today
        ? { title: '已安排棄用', description: `預計於 ${item.discarded_at} 棄用，可在日期紀錄調整。`, label: '補寫棄用備註', field: 'note' }
        : { title: '已棄用', description: '使用紀錄與回憶仍會保留。', label: item.discard_note ? '修改棄用備註' : '補寫棄用備註', field: 'note' }
    if (item.used_at) return item.used_at > today
        ? { title: '已安排使用', description: `預計於 ${item.used_at} 開始使用，可在日期紀錄調整。`, label: '記錄棄用', field: 'discarded_at' }
        : { title: '使用中', description: '不再使用時，記下棄用日期與想留下的話。', label: '記錄棄用', field: 'discarded_at' }
    if (item.received_at && item.received_at <= today) return {
        title: '尚未使用', description: '準備開始使用了嗎？記下日期，之後就能查看使用天數。', label: '開始使用', field: 'used_at',
    }
    return { title: item.received_at ? '等待到貨' : '尚未記錄到貨', description: item.received_at ? `預計 ${item.received_at} 到貨。收到後可確認日期。` : '已收到物品的話，可以補記到貨日期。', label: '記錄到貨', field: 'received_at' }
}

export const getItemShowMetrics = (item, today) => {
    const waiting = item.received_at <= today ? elapsed(item.purchased_at, item.received_at) : null
    const useStarted = valid(item.used_at) && item.used_at <= today
    const disposed = valid(item.discarded_at) && item.discarded_at <= today
    const idleEnd = useStarted ? item.used_at : disposed ? item.discarded_at : today
    const idleDatesValid = (!item.used_at || valid(item.used_at)) && (!item.discarded_at || valid(item.discarded_at))
        && !(item.used_at && item.discarded_at && item.discarded_at < item.used_at)
    const idle = item.received_at <= today && idleDatesValid ? elapsed(item.received_at, idleEnd) : null
    const idleOngoing = idle != null && !useStarted && !disposed
    const used = item.used_at && item.used_at <= today
        ? elapsed(item.used_at, item.discarded_at && item.discarded_at <= today ? item.discarded_at : today, true) : null
    const price = item.price == null || item.price === '' ? null : Number(item.price)
    const expiration = valid(item.expiration_date) ? dayjs(item.expiration_date).diff(dayjs(today), 'day') : null
    return {
        waiting, idle, idleOngoing, used,
        cost: used > 0 && Number.isFinite(price) && price >= 0 ? (price / used).toFixed(2) : null,
        expiration,
        expirationText: expiration == null ? '' : expiration < 0 ? `已過期 ${-expiration} 天` : expiration === 0 ? '今天到期' : `距到期 ${expiration} 天`,
    }
}
