import test from 'node:test'
import assert from 'node:assert/strict'
import { getItemNextAction, getItemShowMetrics } from '../../resources/js/utils/itemShowState.js'
const today = '2026-10-29'
test('next action follows recorded events without inventing dates or equating expiry with discard', () => {
    assert.equal(getItemNextAction({}, today).field, 'received_at')
    assert.equal(getItemNextAction({ received_at: today, expiration_date: '2020-01-01' }, today).field, 'used_at')
    assert.equal(getItemNextAction({ used_at: today }, today).field, 'discarded_at')
    assert.equal(getItemNextAction({ discarded_at: today }, today).field, 'note')
})
test('future events are described as planned and future usage has no elapsed days', () => {
    assert.equal(getItemNextAction({ received_at: '2026-11-01' }, today).title, '等待到貨')
    assert.equal(getItemNextAction({ used_at: '2026-11-01' }, today).title, '已安排使用')
    assert.equal(getItemNextAction({ discarded_at: '2026-11-01' }, today).title, '已安排棄用')
    assert.equal(getItemShowMetrics({ used_at: '2026-11-01', price: 20 }, today).used, null)
    assert.equal(getItemShowMetrics({ purchased_at: '2026-10-01', received_at: '2026-11-01' }, today).waiting, null)
    assert.equal(getItemShowMetrics({ received_at: '2026-10-01', used_at: '2026-11-01' }, today).idle, 28)
})
test('cost includes first use day, stops at disposal and preserves zero price', () => {
    const result = getItemShowMetrics({ purchased_at: '2026-10-01', received_at: '2026-10-03', used_at: '2026-10-04', discarded_at: '2026-10-05', price: 90 }, today)
    assert.equal(result.waiting, 2)
    assert.equal(result.idle, 1)
    assert.equal(result.used, 2)
    assert.equal(result.cost, '45.00')
    assert.equal(getItemShowMetrics({ used_at: today, price: 0 }, today).cost, '0.00')
    assert.equal(getItemShowMetrics({ used_at: today, price: null }, today).cost, null)
})
test('missing or inverted dates are not represented as zero or negative duration', () => {
    assert.equal(getItemShowMetrics({}, today).waiting, null)
    assert.equal(getItemShowMetrics({ purchased_at: today, received_at: '2026-10-01' }, today).waiting, null)
    assert.equal(getItemShowMetrics({ used_at: today, discarded_at: '2026-10-01' }, today).used, null)
})
test('expiration remains a separate deadline', () => {
    assert.equal(getItemShowMetrics({ expiration_date: '2026-10-28' }, today).expirationText, '已過期 1 天')
    assert.equal(getItemShowMetrics({ expiration_date: today }, today).expirationText, '今天到期')
    assert.equal(getItemShowMetrics({ expiration_date: '2026-11-01' }, today).expirationText, '距到期 3 天')
})

test('unused items accumulate idle days until today or an actual disposal', () => {
    const pending = getItemShowMetrics({ received_at: '2026-10-01' }, today)
    assert.equal(pending.idle, 28)
    assert.equal(pending.idleOngoing, true)
    const disposed = getItemShowMetrics({ received_at: '2026-10-01', discarded_at: '2026-10-05' }, today)
    assert.equal(disposed.idle, 4)
    assert.equal(disposed.idleOngoing, false)
    assert.equal(getItemShowMetrics({ received_at: '2026-10-01', discarded_at: '2026-11-01' }, today).idle, 28)
    assert.equal(getItemShowMetrics({ received_at: '2026-11-01' }, today).idle, null)
    assert.equal(getItemShowMetrics({ received_at: '2026-10-10', discarded_at: '2026-10-05' }, today).idle, null)
    assert.equal(getItemShowMetrics({ received_at: '2026-10-01', used_at: 'invalid' }, today).idle, null)
})

test('missing arrival date is described as unrecorded instead of an outstanding delivery', () => {
    const action = getItemNextAction({ purchased_at: '2026-10-01' }, today)
    assert.equal(action.title, '尚未記錄到貨')
    assert.equal(action.label, '記錄到貨')
    assert.doesNotMatch(action.description, /尚未收到|等待到貨/)
})
