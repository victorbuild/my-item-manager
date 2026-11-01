import dayjs from 'dayjs'
import { buildItemUsageCycle } from './itemLifecycle.js'
import { getItemShowMetrics } from './itemShowState.js'

export const buildItemUsageTimeline = (item, today) => {
    const cycle = buildItemUsageCycle(item, today)
    const metrics = getItemShowMetrics(item, today)
    const recorded = new Map(cycle.events.map(event => [event.key, event]))
    const occurred = key => recorded.has(key) && recorded.get(key).offset <= 0
    const days = (from, to) => dayjs(to).diff(dayjs(from), 'day')
    const pending = key => recorded.get(key)?.offset > 0 ? `預計 ${recorded.get(key).offset} 天後${key === 'received_at' ? '到貨' : '開始使用'}` : ''
    let waiting = { label: '購買與到貨間隔', detail: '需有有效的購買與到貨日期' }
    if (occurred('purchased_at') && occurred('received_at')) waiting = { label: `等待到貨 ${metrics.waiting} 天` }
    else if (occurred('purchased_at') && !cycle.missing.some(event => event.key === 'received_at' && event.reason !== '尚未記錄')) {
        waiting = { label: `購買至今 ${days(item.purchased_at, today)} 天`, detail: pending('received_at') || '尚未記錄到貨日期', ongoing: true }
    }
    let idle = { label: '到貨與使用間隔', detail: '尚未記錄到貨日期' }
    if (metrics.idle != null) idle = { label: occurred('used_at') ? `到貨後 ${metrics.idle} 天開始使用` : `到貨後 ${metrics.idle} 天`, detail: metrics.idleOngoing ? pending('used_at') || '截至今天，尚未記錄開始使用' : !occurred('used_at') && occurred('discarded_at') ? '計至棄用日；未記錄開始使用' : '', ongoing: metrics.idleOngoing }
    else if (recorded.get('received_at')?.offset > 0) idle.detail = '到貨日期尚未到，不計算到貨後天數'
    let usage = { label: '使用天數', detail: occurred('discarded_at') && !item.used_at ? '未記錄開始使用日期' : pending('used_at') || '尚未記錄開始使用日期' }
    if (metrics.used != null) usage = { label: `${occurred('discarded_at') ? '共使用' : '已使用'} ${metrics.used} 天`, detail: occurred('discarded_at') ? '計至棄用日' : '截至今天', ongoing: !occurred('discarded_at') }
    const intervals = [waiting, idle, usage]
    if (cycle.warnings.length) intervals.forEach((_, index) => { intervals[index] = { label: '暫不計算天數', detail: '請先確認日期順序' } })
    const keys = ['purchased_at', 'received_at', 'used_at', 'discarded_at']
    const current = [...keys].reverse().find(occurred)
    return keys.map((key, index) => ({
        key, label: ['購買', '到貨', '開始使用', '棄用'][index], date: item[key] || null,
        future: recorded.get(key)?.offset > 0,
        completed: occurred(key), current: !cycle.warnings.length && current === key,
        interval: intervals[index] || null,
    }))
}
