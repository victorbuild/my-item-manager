import { hasUnsavedChanges } from './unsavedChanges.js'

const isWriteRequest = config => !['get', 'head', 'options'].includes((config?.method || 'get').toLowerCase())

export const createSessionRequestGuard = recovery => config => {
    if (recovery.blocksRequests.value && isWriteRequest(config)) {
        const failure = new Error('請先確認原帳號的登入狀態，再重試此操作。')
        failure.code = 'SESSION_RECOVERY_REQUIRED'
        failure.authRecoveryHandled = true
        failure.response = { status: 409, data: { message: failure.message } }
        return Promise.reject(failure)
    }
    return config
}

export const createAuthFailureHandler = (router, recovery, hasDraft = hasUnsavedChanges) => async error => {
    const status = error.response?.status
    const preserveDraft = hasDraft() || error.config?.preserveDraftOnAuthFailure === true
    if ((status === 401 || status === 419) && preserveDraft) recovery.retainDraft()
    const returnHome = async () => {
        if (hasDraft() || recovery.preservesDraft.value) {
            recovery.expire()
            return
        }
        recovery.reset({ signedOut: true })
        try { await router.replace('/') } catch { /* 保留原始 API 錯誤。 */ }
    }
    if (status === 401) {
        error.authRecoveryHandled = true
        if (preserveDraft || recovery.preservesDraft.value) recovery.expire()
        else await returnHome()
    } else if (status === 419) {
        error.authRecoveryHandled = true
        const outcome = await recovery.recoverCsrf({ preserveDraft })
        if (outcome === 'signed-out' && !preserveDraft) await returnHome()
    } else if (status === 403 && error.response?.data?.code === 'email_verification_required') {
        try {
            await router.push('/verify-email')
        } catch {
            // 保留原始 API 錯誤，避免被導航錯誤取代。
        }
    }
    return Promise.reject(error)
}
