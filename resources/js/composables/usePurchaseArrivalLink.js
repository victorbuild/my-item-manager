import { ref, watch } from 'vue'

export const usePurchaseArrivalLink = form => {
    const receivedWithPurchase = ref(false)
    let manualReceivedDate = ''
    watch(receivedWithPurchase, linked => {
        if (linked) {
            manualReceivedDate = form.value.received_at
            form.value.received_at = form.value.purchased_at
        } else {
            form.value.received_at = manualReceivedDate
        }
    }, { flush: 'sync' })
    watch(() => form.value.purchased_at, date => {
        if (receivedWithPurchase.value) form.value.received_at = date
    }, { flush: 'sync' })
    const resetArrivalLink = () => {
        receivedWithPurchase.value = false
        manualReceivedDate = ''
    }
    return { receivedWithPurchase, resetArrivalLink }
}
