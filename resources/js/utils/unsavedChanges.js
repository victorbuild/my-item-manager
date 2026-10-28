import { computed, ref, watch } from 'vue'

export const createUnsavedChangesGuard = ({ snapshot, target = window, confirm = message => window.confirm(message), pending = () => false, pendingMessage = '物品正在儲存，離開後仍可能完成建立。確定離開此頁面？' }) => {
    const serialize = () => JSON.stringify(snapshot())
    const baseline = ref(serialize())
    const isDirty = computed(() => serialize() !== baseline.value)
    let attached = false
    const beforeUnload = event => {
        if (!isDirty.value) return
        event.preventDefault()
        event.returnValue = true
    }
    const detach = () => {
        if (!attached) return
        target.removeEventListener('beforeunload', beforeUnload)
        attached = false
    }
    const stop = watch(isDirty, dirty => {
        if (dirty && !attached) {
            target.addEventListener('beforeunload', beforeUnload)
            attached = true
        } else if (!dirty) detach()
    }, { immediate: true, flush: 'sync' })
    return {
        isDirty,
        confirmLeave: () => !isDirty.value || confirm(pending()
            ? pendingMessage
            : '有尚未儲存的變更，離開將放棄目前填寫的內容。確定離開此頁面？'),
        markSaved: () => { baseline.value = serialize() },
        dispose: () => { stop(); detach() },
    }
}
