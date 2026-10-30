import test from 'node:test'
import assert from 'node:assert/strict'
import { buildItemUsageTimeline } from '../../resources/js/utils/itemUsageTimeline.js'
const today = '2026-10-30'
const stages = item => Object.fromEntries(buildItemUsageTimeline(item, today).map(stage => [stage.key, stage]))

test('completed intervals sit beside their dates and use includes its first day', () => {
    const result = stages({ purchased_at: '2026-10-01', received_at: '2026-10-03', used_at: '2026-10-08' })
    assert.equal(result.purchased_at.interval.label, '等待到貨 2 天')
    assert.equal(result.received_at.interval.label, '到貨後 5 天開始使用')
    assert.equal(result.used_at.interval.label, '已使用 23 天')
    assert.equal(result.used_at.current, true)
    assert.equal(result.discarded_at.date, null)
})
test('waiting accumulates only while arrival has not happened and separates a planned arrival', () => {
    const result = stages({ purchased_at: '2026-10-20', received_at: '2026-11-01' })
    assert.equal(result.purchased_at.interval.label, '購買至今 10 天')
    assert.equal(result.purchased_at.interval.detail, '預計 2 天後到貨')
    assert.equal(result.received_at.future, true)
    assert.equal(result.received_at.completed, false)
    assert.equal(result.received_at.interval.detail, '到貨日期尚未到，不計算到貨後天數')
})
test('planned use keeps accumulating idle days and states how far away use is', () => {
    const result = stages({ received_at: '2026-10-20', used_at: '2026-11-01' })
    assert.equal(result.received_at.interval.label, '到貨後 10 天')
    assert.equal(result.received_at.interval.detail, '預計 2 天後開始使用')
    assert.equal(result.used_at.future, true)
    assert.equal(result.used_at.completed, false)
    assert.equal(result.received_at.current, true)
})
test('discard before use stops idle days without fabricating a use date', () => {
    const result = stages({ received_at: '2026-10-20', discarded_at: '2026-10-25' })
    assert.equal(result.received_at.interval.label, '到貨後 5 天')
    assert.equal(result.received_at.interval.ongoing, false)
    assert.match(result.received_at.interval.detail, /棄用/)
    assert.equal(result.used_at.date, null)
    assert.equal(result.discarded_at.current, true)
})
test('missing dates and reversed dates never invent completed durations or reorder editable fields', () => {
    const item = { purchased_at: '2026-10-30', received_at: '2026-10-02' }
    const before = { ...item }
    const result = buildItemUsageTimeline(item, today)
    assert.deepEqual(result.map(stage => stage.key), ['purchased_at', 'received_at', 'used_at', 'discarded_at'])
    assert.equal(result[0].interval.label, '暫不計算天數')
    assert.equal(result.some(stage => stage.current), false)
    assert.deepEqual(item, before)
    assert.equal(stages({}).purchased_at.date, null)
    assert.equal(stages({ used_at: today }).purchased_at.interval.label, '購買與到貨間隔')
})
test('same day arrival shows zero waiting while used days stay inclusive and stop on disposal', () => {
    const result = stages({ purchased_at: '2026-10-20', received_at: '2026-10-20', used_at: '2026-10-20', discarded_at: '2026-10-20' })
    assert.equal(result.purchased_at.interval.label, '等待到貨 0 天')
    assert.equal(result.received_at.interval.label, '到貨後 0 天開始使用')
    assert.equal(result.used_at.interval.label, '共使用 1 天')
    assert.equal(result.used_at.interval.ongoing, false)
})

test('missing arrival means unrecorded, not evidence of waiting for a delivery', () => {
    const result = stages({ purchased_at: '2026-10-20' })
    assert.equal(result.purchased_at.interval.label, '購買至今 10 天')
    assert.equal(result.purchased_at.interval.detail, '尚未記錄到貨日期')
    assert.equal(result.received_at.date, null)
    const used = stages({ purchased_at: '2026-10-20', used_at: '2026-10-21' })
    assert.equal(used.purchased_at.interval.detail, '尚未記錄到貨日期')
    assert.equal(used.used_at.interval.label, '已使用 10 天')
})
