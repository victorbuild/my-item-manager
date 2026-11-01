export const preventImplicitSubmit = event => {
    if (event.key !== 'Enter' || event.target.tagName !== 'INPUT' || event.isComposing || event.keyCode === 229) return
    // vue-multiselect 在 keypress 選取並取消預設送出，不可提前取消其 keydown。
    if (event.target.closest?.('.multiselect[role="combobox"]')) return
    event.preventDefault()
}
