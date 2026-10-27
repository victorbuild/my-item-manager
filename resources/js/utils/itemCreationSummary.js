const MAX_PRICE_CENTS = 9999999999

const toCents = value => {
    if (value === '' || value == null) return null
    const text = String(value).trim()
    if (!/^\d+(?:\.\d{1,2})?$/.test(text)) return NaN
    const [whole, fraction = ''] = text.split('.')
    const cents = Number(whole) * 100 + Number(fraction.padEnd(2, '0'))
    return Number.isSafeInteger(cents) ? cents : NaN
}

export const getItemCreationSummary = ({ quantity, maxQuantity, price, totalAmount, priceMode }) => {
    const count = Number(quantity)
    if (!Number.isSafeInteger(count) || count < 1 || count > maxQuantity) {
        return { valid: false, field: 'quantity', message: `數量請填寫 1～${maxQuantity} 的整數。` }
    }
    const amount = toCents(priceMode === 'total' ? totalAmount : price)
    if (Number.isNaN(amount)) {
        return { valid: false, field: 'price', message: '金額請填寫非負數，最多兩位小數。' }
    }
    const unitCents = amount == null ? null : priceMode === 'total' ? Math.round(amount / count) : amount
    if (unitCents != null && unitCents > MAX_PRICE_CENTS) {
        return { valid: false, field: 'price', message: '單價不可超過 99,999,999.99。' }
    }
    const totalCents = unitCents == null ? null : unitCents * count
    if (totalCents != null && !Number.isSafeInteger(totalCents)) {
        return { valid: false, field: 'price', message: '金額過大，請調整數量或金額。' }
    }
    return {
        valid: true,
        quantity: count,
        unitPrice: unitCents == null ? null : unitCents / 100,
        totalPrice: totalCents == null ? null : totalCents / 100,
        enteredTotal: priceMode === 'total' && amount != null ? amount / 100 : null,
        rounded: priceMode === 'total' && amount != null && totalCents !== amount,
    }
}

export const requiresQuantityConfirmation = quantity => quantity >= 10

export const isQuantityConfirmed = (quantity, confirmation) => !requiresQuantityConfirmation(quantity)
    || String(confirmation).trim() === String(quantity)
