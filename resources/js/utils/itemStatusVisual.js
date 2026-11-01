import { buildItemLifecycle } from './itemLifecycle.js'

// 期限提醒獨立於使用狀態；未到的事件不演成已完成，異常資料不推定情境。
export const getItemStatusVisual = (item, today) => {
    const lifecycle = buildItemLifecycle(item, today)
    if (lifecycle.warnings.length || lifecycle.missing.some(event => event.reason !== '尚未記錄')) return null
    const occurred = key => lifecycle.events.some(event => event.key === key && event.offset <= 0)
    if (occurred('discarded_at')) return 'discarded'
    if (lifecycle.events.some(event => event.key === 'expiration_date' && event.offset < 0)) return 'expired'
    if (occurred('used_at')) return 'using'
    if (occurred('received_at')) return 'idle'
    if (occurred('purchased_at')) return 'waiting'
    return null
}
