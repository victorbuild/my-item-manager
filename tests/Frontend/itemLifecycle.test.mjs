import test from 'node:test'
import assert from 'node:assert/strict'
import { buildItemLifecycle } from '../../resources/js/utils/itemLifecycle.js'
const today = '2026-10-27'
const metric = (result, key) => result.metrics.find(entry => entry.key === key)

test('sorts the independent expiration marker by date, including after discard', () => {
    const result = buildItemLifecycle({ purchased_at: '2026-10-01', received_at: '2026-10-03', used_at: '2026-10-05', discarded_at: '2026-10-10', expiration_date: '2026-11-01' }, today)
    assert.deepEqual(result.events.map(event => event.key), ['purchased_at', 'received_at', 'used_at', 'discarded_at', 'today', 'expiration_date'])
    assert.equal(result.warnings.length, 0)
    assert.equal(metric(result, 'waiting').days, 2)
    assert.equal(metric(result, 'idle').days, 2)
    assert.equal(metric(result, 'usage').days, 5)
    assert.equal(metric(result, 'remaining').days, 5)
})
test('does not infer discard from an expired deadline or missing dates', () => {
    const result = buildItemLifecycle({ used_at: '2026-10-20', expiration_date: '2026-10-25' }, today)
    assert.equal(metric(result, 'usage').days, 7)
    assert.equal(metric(result, 'remaining').value, '已過期 2 天')
    assert.ok(result.missing.find(event => event.key === 'discarded_at'))
    assert.match(metric(result, 'waiting').reason, /尚未記錄/)
})
test('does not count future usage or future discard as elapsed use', () => {
    assert.equal(metric(buildItemLifecycle({ used_at: '2026-11-01' }, today), 'usage').days, null)
    const result = buildItemLifecycle({ used_at: '2026-10-20', discarded_at: '2026-11-01' }, today)
    assert.equal(metric(result, 'usage').days, 7)
    assert.match(metric(result, 'usage').value, /截至今天/)
})
test('reports reversed dates even when intermediate events are missing, without mutation', () => {
    const item = { purchased_at: '2026-10-25', used_at: '2026-10-20', expiration_date: '2026-10-01' }
    const original = structuredClone(item)
    const result = buildItemLifecycle(item, today)
    assert.equal(result.warnings.length, 1)
    assert.equal(metric(result, 'usage').days, null)
    assert.equal(metric(result, 'remaining').days, -26)
    assert.deepEqual(item, original)
})
test('gives same-day events the same time position and separate lanes', () => {
    const result = buildItemLifecycle({ purchased_at: today, received_at: today, used_at: today, expiration_date: today, discarded_at: today }, today)
    assert.equal(result.events.length, 6)
    assert.deepEqual(result.events.map(event => event.lane), [0, 1, 2, 3, 4, 5])
    assert.ok(result.events.every(event => event.position === 0.5))
    assert.equal(metric(result, 'waiting').days, 0)
    assert.equal(metric(result, 'remaining').value, '今天到期')
})
test('uses actual day intervals for positions and handles leap days', () => {
    const result = buildItemLifecycle({ purchased_at: '2024-02-28', received_at: '2024-02-29' }, '2024-03-01')
    assert.equal(result.events[1].position, 0.5)
    assert.equal(result.spanDays, 2)
    assert.equal(metric(result, 'waiting').days, 1)
})
test('rejects invalid dates and renders only today for a wholly missing item', () => {
    const result = buildItemLifecycle({ purchased_at: '2026-02-30' }, today)
    assert.equal(result.events.length, 1)
    assert.equal(result.missing.length, 5)
    assert.match(result.missing[0].reason, /日期無效/)
    assert.equal(metric(result, 'waiting').days, null)
})
test('distinguishes the stated near-expiry window from distant future deadlines', () => {
    assert.equal(buildItemLifecycle({ expiration_date: '2026-11-26' }, today).events.at(-1).tone, 'warning')
    assert.equal(buildItemLifecycle({ expiration_date: '2026-11-27' }, today).events.at(-1).tone, 'info')
})

test('keeps expiry independent when it predates every event', () => {
    const result = buildItemLifecycle({ purchased_at: '2026-10-20', received_at: '2026-10-21', expiration_date: '2026-10-01' }, today)
    assert.equal(result.events[0].key, 'expiration_date')
    assert.equal(result.warnings.length, 0)
    assert.equal(metric(result, 'waiting').days, 1)
})
test('marks future intervals as projected and preserves actual calendar-day spans across DST', () => {
    const projected = buildItemLifecycle({ purchased_at: '2026-10-26', received_at: '2026-11-01', used_at: '2026-11-02' }, today)
    assert.match(metric(projected, 'waiting').value, /預計/)
    assert.equal(metric(projected, 'usage').days, null)
    const dst = buildItemLifecycle({ purchased_at: '2026-03-07', received_at: '2026-03-09' }, '2026-03-08')
    assert.equal(dst.spanDays, 2)
    assert.equal(metric(dst, 'waiting').days, 2)
    assert.equal(dst.events[1].position, 0.5)
})

test('story shows expired inspection without inferring disposal', async () => {
    const { buildLifecycleStory } = await import('../../resources/js/utils/itemLifecycle.js')
    const story = buildLifecycleStory(buildItemLifecycle({ used_at: '2026-10-20', expiration_date: '2026-10-25' }, today))
    assert.equal(story.key, 'expiry')
    assert.equal(story.expired, true)
    assert.match(story.description, /不代表已棄用/)
})
test('story only performs disposal when a past disposal date is recorded', async () => {
    const { buildLifecycleStory } = await import('../../resources/js/utils/itemLifecycle.js')
    for (const discarded_at of ['', '2026-11-01']) {
        const story = buildLifecycleStory(buildItemLifecycle({ discarded_at }, today), 'discarded_at')
        assert.equal(story.canAct, false)
    }
    assert.equal(buildLifecycleStory(buildItemLifecycle({ discarded_at: '2026-10-20' }, today)).key, 'discarded')
})
test('story distinguishes waiting, future use and abnormal records', async () => {
    const { buildLifecycleStory } = await import('../../resources/js/utils/itemLifecycle.js')
    assert.equal(buildLifecycleStory(buildItemLifecycle({ purchased_at: '2026-10-20' }, today)).key, 'delivery')
    assert.equal(buildLifecycleStory(buildItemLifecycle({}, today), 'delivery').canAct, false)
    assert.equal(buildLifecycleStory(buildItemLifecycle({ used_at: '2026-11-01' }, today), 'used_at').canAct, false)
    assert.equal(buildLifecycleStory(buildItemLifecycle({ purchased_at: '2026-10-25', used_at: '2026-10-20' }, today)).key, 'inspect')
})

test('current scene uses saved dates to show pending arrival or unused items', async () => {
    const { buildLifecycleStory } = await import('../../resources/js/utils/itemLifecycle.js')
    const pending = buildLifecycleStory(buildItemLifecycle({ purchased_at: '2026-10-20', received_at: '2026-11-01' }, today))
    assert.equal(pending.key, 'delivery')
    assert.match(pending.description, /2026-11-01/)
    const idle = buildLifecycleStory(buildItemLifecycle({ received_at: '2026-10-21', used_at: '2026-11-01' }, today))
    assert.equal(idle.title, '已到貨，待使用')
    assert.match(idle.description, /預計/)
})

test('usage cycle excludes expiration without changing the original saved item', async () => {
    const { buildItemUsageCycle, buildLifecycleStory } = await import('../../resources/js/utils/itemLifecycle.js')
    const item = { purchased_at: '2026-10-01', received_at: '2026-10-02', used_at: '2026-10-03', expiration_date: '2026-10-04' }
    const cycle = buildItemUsageCycle(item, '2026-10-29')
    assert.equal(cycle.events.some(event => event.key === 'expiration_date'), false)
    assert.equal(buildLifecycleStory(cycle).key, 'using')
    assert.equal(item.expiration_date, '2026-10-04')
})
