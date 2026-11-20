export const createAuthFailureHandler = (router, account) => error => {
    const status = error.response?.status
    if (status === 401 || status === 419) {
        account.clear()
        router.replace({ path: '/login', query: { reason: 'session-expired' } })
    } else if (status === 403 && error.response?.data?.code === 'email_verification_required') {
        router.push('/verify-email')
    }
    return Promise.reject(error)
}
