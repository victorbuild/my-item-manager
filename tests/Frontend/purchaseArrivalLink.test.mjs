import test from 'node:test'
import assert from 'node:assert/strict'
import { effectScope, ref } from 'vue'
import { usePurchaseArrivalLink } from '../../resources/js/composables/usePurchaseArrivalLink.js'

test('only links arrival when explicitly selected, and keeps following purchase date', () => {
    const scope = effectScope()
    scope.run(() => {
        const form = ref({ purchased_at: '2026-10-20', received_at: '' })
        const { receivedWithPurchase } = usePurchaseArrivalLink(form)
        form.value.purchased_at = '2026-10-21'
        assert.equal(form.value.received_at, '')
        receivedWithPurchase.value = true
        assert.equal(form.value.received_at, '2026-10-21')
        form.value.purchased_at = '2026-10-22'
        assert.equal(form.value.received_at, '2026-10-22')
    })
    scope.stop()
})
test('restores the previously entered arrival date when unlinked', () => {
    const scope = effectScope()
    scope.run(() => {
        const form = ref({ purchased_at: '2026-10-20', received_at: '2026-10-25' })
        const { receivedWithPurchase } = usePurchaseArrivalLink(form)
        receivedWithPurchase.value = true
        receivedWithPurchase.value = false
        assert.equal(form.value.received_at, '2026-10-25')
    })
    scope.stop()
})
test('continuing to add another item resets the link without restoring old data', () => {
    const scope = effectScope()
    scope.run(() => {
        const form = ref({ purchased_at: '2026-10-20', received_at: '2026-10-25' })
        const { receivedWithPurchase, resetArrivalLink } = usePurchaseArrivalLink(form)
        receivedWithPurchase.value = true
        resetArrivalLink()
        form.value = { purchased_at: '2026-10-27', received_at: '' }
        assert.equal(receivedWithPurchase.value, false)
        assert.equal(form.value.received_at, '')
    })
    scope.stop()
})
