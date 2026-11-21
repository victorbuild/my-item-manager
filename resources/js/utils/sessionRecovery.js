import { computed, ref } from 'vue'

export const createSessionRecovery = (api, account) => {
    const state = ref('idle'), dialogOpen = ref(false), message = ref('')
    const preservesDraft = ref(false)
    const retainDraft = () => { preservesDraft.value = true }
    const requiresLogin = computed(() => ['expired', 'account-mismatch'].includes(state.value))
    const blocksRequests = computed(() => ['expired', 'account-mismatch', 'checking', 'check-failed'].includes(state.value))
    let expectedUserId, pending, notified = false, generation = 0
    const reset = ({ signedOut = false } = {}) => {
        generation++
        pending = undefined
        expectedUserId = undefined
        notified = false
        preservesDraft.value = false
        dialogOpen.value = false
        state.value = 'idle'
        message.value = ''
        if (signedOut) account.clear()
    }
    const showExpired = () => {
        if (expectedUserId === undefined) expectedUserId = account.user.value?.id
        state.value = 'expired'
        message.value = '這次操作尚未完成。請在新分頁登入，再回到此頁確認登入狀態。重新整理或關閉此頁可能遺失尚未儲存的內容。'
        account.clear()
        if (!notified) { dialogOpen.value = true; notified = true }
    }
    const expire = () => {
        // 新的過期回應必須淘汰尚未完成的恢復結果。
        generation++
        pending = undefined
        retainDraft()
        showExpired()
    }
    const check = ({ preserveDraft = true } = {}) => {
        if (preserveDraft) retainDraft()
        if (pending) return pending
        const previousState = state.value
        const token = generation
        state.value = 'checking'
        pending = (async () => {
            try {
                const { data } = await api.get('/api/user')
                if (token !== generation) return
                const user = data.data
                if (expectedUserId !== undefined && user.id !== expectedUserId) {
                    state.value = 'account-mismatch'
                    message.value = '目前登入的是另一個帳號。請重新登入原帳號，才能繼續處理此頁內容。'
                    dialogOpen.value = true
                    return
                }
                await api.get('/sanctum/csrf-cookie')
                if (token !== generation) return
                account.restore(user)
                state.value = 'ready'
                message.value = '登入與安全驗證已確認，請確認內容後再次操作；系統不會自動重送。'
                dialogOpen.value = false
                expectedUserId = undefined
                notified = false
                preservesDraft.value = false
            } catch (failure) {
                if (token !== generation) return
                if (failure.response?.status === 401) {
                    if (preservesDraft.value) showExpired()
                    else return 'signed-out'
                } else {
                    state.value = ['expired', 'account-mismatch'].includes(previousState) ? previousState : 'check-failed'
                    message.value = '暫時無法確認登入或更新安全驗證。內容仍留在此頁，請稍後再試。'
                }
            } finally { if (token === generation) pending = undefined }
        })()
        return pending
    }
    const recoverCsrf = options => {
        if (expectedUserId === undefined) expectedUserId = account.user.value?.id
        return check(options)
    }
    return { state, dialogOpen, message, requiresLogin, blocksRequests, preservesDraft, retainDraft, expire, check, recoverCsrf, reset }
}
