export const parseDateText = value => {
    const text = String(value ?? '').trim()
    const parts = /^\d{8}$/.test(text)
        ? [text.slice(0, 4), text.slice(4, 6), text.slice(6, 8)]
        : text.match(/^(\d{4})[-/.\s]+(\d{1,2})[-/.\s]+(\d{1,2})$/)?.slice(1)
    if (!parts) return null
    const [year, month, day] = parts.map(Number)
    if (year < 1 || year > 9999 || month < 1 || month > 12 || day < 1 || day > 31) return null
    const date = new Date(0)
    date.setFullYear(year, month - 1, day)
    date.setHours(0, 0, 0, 0)
    return date.getFullYear() === year && date.getMonth() === month - 1 && date.getDate() === day ? date : null
}

export const formatDateText = date => `${String(date.getFullYear()).padStart(4, '0')}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`

export const getDateTextError = (value, min, max) => {
    if (!String(value ?? '').trim()) return ''
    const date = parseDateText(value)
    if (!date) return '請輸入有效日期，例如 2026-10-27 或 20261027。'
    const normalized = formatDateText(date)
    if (min && normalized < min) return `日期不可早於 ${min}。`
    if (max && normalized > max) return `日期不可晚於 ${max}。`
    return ''
}

export const shiftDateTextYear = (value, step, min, max) => {
    const date = parseDateText(value)
    if (!date) return null
    const year = date.getFullYear() + step
    if (year < 1 || year > 9999) return null
    const month = date.getMonth()
    date.setFullYear(year)
    if (date.getMonth() !== month) date.setDate(0)
    const result = formatDateText(date)
    return getDateTextError(result, min, max) ? null : result
}
