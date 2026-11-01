// 僅轉換數字輸入所需的全型字元，不刪除無效內容或改變數值。
export const normalizeNumericText = value => String(value).replace(/[０-９．＋－　]/g, character =>
    character === '　' ? ' ' : String.fromCharCode(character.charCodeAt(0) - 0xfee0))

export const createNumericInputHandlers = (commit, transform = value => value) => {
    const composing = new WeakSet()
    const onInput = event => {
        const input = event.target
        if (input.readOnly || input.disabled || event.isComposing || composing.has(input)) return
        const value = transform(normalizeNumericText(input.value))
        if (value !== input.value) {
            const start = input.selectionStart
            const end = input.selectionEnd
            const direction = input.selectionDirection
            input.value = value
            if (start != null) input.setSelectionRange(start, end, direction)
        }
        commit(value)
    }
    const onCompositionStart = event => { composing.add(event.target) }
    const onCompositionEnd = event => {
        composing.delete(event.target)
        onInput({ target: event.target })
    }
    return { onInput, onCompositionStart, onCompositionEnd }
}
