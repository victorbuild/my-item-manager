import { computed, ref, watch } from 'vue'
import { getItemCreationSummary } from '../utils/itemCreationSummary.js'

export const useItemPricing = (form, maxQuantity) => {
    const priceMode = ref('unit')
    const calculationBasis = ref('unit')
    const totalAmount = ref('')
    const creationSummary = computed(() => getItemCreationSummary({
        quantity: form.value.quantity,
        maxQuantity,
        price: form.value.price,
        totalAmount: totalAmount.value,
        priceMode: calculationBasis.value,
    }))
    const displayedTotal = computed(() => creationSummary.value.valid
        ? creationSummary.value.enteredTotal ?? creationSummary.value.totalPrice
        : null)
    const switchPriceMode = mode => {
        if (!['unit', 'total'].includes(mode) || mode === priceMode.value) return
        const summary = creationSummary.value
        if (summary.valid) {
            form.value.price = summary.unitPrice == null ? '' : summary.unitPrice.toFixed(2)
            const total = summary.enteredTotal ?? summary.totalPrice
            totalAmount.value = total == null ? '' : total.toFixed(2)
        }
        priceMode.value = mode
    }
    const setPriceAmount = value => {
        if (priceMode.value === 'unit') form.value.price = value
        else totalAmount.value = value
        calculationBasis.value = priceMode.value
    }
    // 切換只換編輯權限；實際修改數量時，才依目前選擇的金額重新計算。
    watch(() => form.value.quantity, () => { calculationBasis.value = priceMode.value }, { flush: 'sync' })
    const resetPricing = () => {
        priceMode.value = 'unit'
        calculationBasis.value = 'unit'
        totalAmount.value = ''
    }
    return { priceMode, calculationBasis, totalAmount, creationSummary, displayedTotal, switchPriceMode, setPriceAmount, resetPricing }
}
