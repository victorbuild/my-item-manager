import dayjs from 'dayjs'
import utc from 'dayjs/plugin/utc.js'
import { getTodayDate } from './itemDates.js'
import { parseDateText } from './dateTextInput.js'

dayjs.extend(utc)

const FIELDS = [
    { key: 'purchased_at', label: '購買', kind: 'event' },
    { key: 'received_at', label: '到貨', kind: 'event' },
    { key: 'used_at', label: '開始使用', kind: 'event' },
    { key: 'expiration_date', label: '有效期限', kind: 'deadline' },
    { key: 'discarded_at', label: '棄用', kind: 'event' },
]
const EVENT_ORDER = ['purchased_at', 'received_at', 'used_at', 'discarded_at']
const readDate = value => {
    if (typeof value !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(value)) return null
    const parsed = parseDateText(value)
    return parsed ? dayjs(parsed).utc(true).startOf('day') : null
}
const unavailable = (key, label, reason) => ({ key, label, days: null, value: '無法計算', reason })

export const buildItemLifecycle = (item, todayValue = getTodayDate(), warningDays = 30) => {
    const today = readDate(todayValue)
    if (!today) throw new Error('生命週期需要有效的今天日期。')
    const records = FIELDS.map((field, order) => {
        const date = readDate(item[field.key])
        const missing = item[field.key] == null || item[field.key] === ''
        if (!date) return { ...field, order, date: null, reason: missing ? '尚未記錄' : '日期無效，請確認' }
        const offset = date.diff(today, 'day')
        let status = offset > 0 ? '未來事件' : offset === 0 ? '今天發生' : '已發生'
        let tone = offset > 0 ? 'info' : 'brand'
        if (field.kind === 'deadline') {
            status = offset < 0 ? '已過期' : offset === 0 ? '今天到期' : offset <= warningDays ? `${warningDays} 天內到期` : '尚未到期'
            tone = offset < 0 ? 'danger' : offset <= warningDays ? 'warning' : 'info'
        }
        return { ...field, order, date: item[field.key], stamp: date.valueOf(), offset, status, tone,
            relative: offset === 0 ? '今天' : offset > 0 ? `${offset} 天後` : `${-offset} 天前` }
    })
    const byKey = Object.fromEntries(records.map(record => [record.key, record]))
    const warnings = []
    for (let index = 0; index < EVENT_ORDER.length; index++) {
        for (const key of EVENT_ORDER.slice(index + 1)) {
            const earlier = byKey[EVENT_ORDER[index]], later = byKey[key]
            if (earlier.date && later.date && later.stamp < earlier.stamp) warnings.push(`${later.label}日期早於${earlier.label}日期，請確認；資料未被修改。`)
        }
    }
    const missingReason = record => `${record.label}日期${record.reason}`
    const interval = (key, label, startKey, endKey) => {
        const start = byKey[startKey], end = byKey[endKey]
        if (!start.date || !end.date) return unavailable(key, label, [start, end].filter(record => !record.date).map(missingReason).join('；'))
        if (warnings.length) return unavailable(key, label, '事件日期順序異常，請先確認。')
        const days = readDate(end.date).diff(readDate(start.date), 'day')
        return { key, label, days, value: `${days} 天${end.offset > 0 ? '（預計）' : ''}`, reason: null }
    }
    const waiting = interval('waiting', '等待到貨', 'purchased_at', 'received_at')
    const idle = interval('idle', '到貨後閒置', 'received_at', 'used_at')
    const used = byKey.used_at, discarded = byKey.discarded_at
    let usage
    if (!used.date) usage = unavailable('usage', '使用天數', missingReason(used))
    else if (warnings.length) usage = unavailable('usage', '使用天數', '事件日期順序異常，請先確認。')
    else if (used.offset > 0) usage = unavailable('usage', '使用天數', '開始使用日期尚未到，不計為已使用。')
    else if (!discarded.date && discarded.reason !== '尚未記錄') usage = unavailable('usage', '使用天數', missingReason(discarded))
    else {
        const end = discarded.date && discarded.offset <= 0 ? readDate(discarded.date) : today
        const days = end.diff(readDate(used.date), 'day')
        usage = { key: 'usage', label: '使用天數', days,
            value: `${days} 天${!discarded.date ? '（截至今天；未記錄棄用日）' : discarded.offset > 0 ? '（截至今天；棄用日尚未到）' : ''}`, reason: null }
    }
    const expiry = byKey.expiration_date
    const remaining = expiry.date ? { key: 'remaining', label: '有效期限', days: expiry.offset,
        value: expiry.offset === 0 ? '今天到期' : expiry.offset > 0 ? `剩餘 ${expiry.offset} 天` : `已過期 ${-expiry.offset} 天`, reason: null }
        : unavailable('remaining', '有效期限', missingReason(expiry))
    const events = [...records.filter(record => record.date), {
        key: 'today', label: '今天', kind: 'today', order: FIELDS.length, date: todayValue,
        stamp: today.valueOf(), offset: 0, status: '基準日', tone: 'info', relative: '今天',
    }].sort((a, b) => a.stamp - b.stamp || a.order - b.order)
    const start = events[0].stamp, end = events.at(-1).stamp
    const sameDayCounts = new Map()
    const timeline = events.map(event => {
        const lane = sameDayCounts.get(event.date) ?? 0
        sameDayCounts.set(event.date, lane + 1)
        return { ...event, position: end === start ? 0.5 : (event.stamp - start) / (end - start), lane }
    })
    return { events: timeline, missing: records.filter(record => !record.date), warnings,
        metrics: [waiting, idle, usage, remaining], today: todayValue,
        start: events[0].date, end: events.at(-1).date,
        spanDays: dayjs.utc(end).diff(dayjs.utc(start), 'day'), warningDays }
}

export const buildLifecycleStory = (lifecycle, selectedKey = 'today') => {
    const event = key => lifecycle.events.find(entry => entry.key === key)
    const happened = key => event(key) && event(key).offset <= 0
    let key = selectedKey
    if (key === 'today') {
        if (lifecycle.warnings.length) return { key: 'inspect', eventKey: 'today', title: '先確認日期', description: '事件順序有異常，先顯示檢查物品的情境，不推定目前狀態。', tone: 'warning', canAct: false, expired: false }
        key = happened('discarded_at') ? 'discarded_at'
            : event('expiration_date')?.offset < 0 ? 'expiration_date'
                : happened('used_at') ? 'used_at'
                    : happened('received_at') ? 'idle'
                        : happened('purchased_at') ? 'delivery' : 'purchased_at'
    }
    if (key === 'idle') {
        const planned = event('used_at')
        return { key: 'arrival', eventKey: 'received_at', title: '已到貨，待使用',
            description: planned?.offset > 0 ? `已記錄到貨，預計於 ${planned.date} 開始使用，現在先整理包裹。` : '已記錄到貨，尚未記錄開始使用；角色在旁整理包裹。',
            tone: 'brand', canAct: true, expired: false }
    }
    if (key === 'delivery') {
        const bought = Boolean(happened('purchased_at')), arrived = Boolean(happened('received_at')), expected = event('received_at')
        return { key: 'delivery', eventKey: 'received_at', title: arrived ? '運送情境回顧' : '等候到貨',
            description: arrived ? '已記錄到貨，以貨車回顧運送情境。' : bought && expected?.offset > 0 ? `預計 ${expected.date} 到貨，貨車示意等候，不表示已送達。` : bought ? '已記錄購買，尚未記錄到貨；貨車示意等候，不表示已送達。' : '購買日期尚未記錄或尚未到，貨車先停靠，只作情境示意。',
            tone: arrived ? 'brand' : 'info', canAct: (bought || arrived) && !lifecycle.warnings.length, expired: false }
    }
    const record = event(key)
    const phase = { purchased_at: ['shopping', '購買'], received_at: ['arrival', '到貨與開箱'], used_at: ['using', '開始使用'], discarded_at: ['discarded', '棄用'], expiration_date: ['expiry', '檢查有效期限'] }[key] || ['inspect', '檢查物品']
    const missing = lifecycle.missing.find(entry => entry.key === key)
    const suffix = !record ? `（${missing?.reason || '尚未記錄'}）` : record.offset > 0 ? '（預計）' : ''
    const expired = key === 'expiration_date' && record?.offset < 0
    const description = !record ? '這個日期尚未完整記錄，角色僅作情境示意，不表示事件已發生。'
        : record.offset > 0 ? '日期尚未到，角色先等待，不演成已完成的動作。'
            : key === 'discarded_at' ? '已記錄棄用日，角色把物品放入垃圾桶。'
                : expired ? '期限已過，角色拿起物品檢查、露出嫌棄的表情；不代表已棄用。'
                    : key === 'used_at' ? '已記錄開始使用，角色在桌邊使用物品。'
                        : key === 'received_at' ? '已記錄到貨，角色拆開剛收到的包裹。'
                            : key === 'purchased_at' ? '已記錄購買，角色在小店拿到物品。' : '角色檢查物品的有效期限。'
    return { key: phase[0], eventKey: key, title: `${expired ? '發現過期' : phase[1]}${suffix}`, description,
        tone: record?.tone || 'info', canAct: Boolean(record && record.offset <= 0 && !lifecycle.warnings.length), expired }
}

// 使用週期只呈現購買、到貨、使用與棄用，不讓有效期限改變 3D 情境。
export const buildItemUsageCycle = (item, todayValue = getTodayDate()) =>
    buildItemLifecycle({ ...item, expiration_date: null }, todayValue)
