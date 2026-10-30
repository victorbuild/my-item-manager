import { computed, ref } from 'vue'

export const createCurrentUserState = api => {
    const user = ref(null), loading = ref(false), error = ref('')
    const isLoggedIn = computed(() => user.value !== null)
    let loaded = false, pending, controller, generation = 0
    const load = ({ force = false } = {}) => {
        if (pending && !force) return pending
        if (loaded && !force) return Promise.resolve(user.value)
        controller?.abort()
        controller = new AbortController()
        const signal = controller.signal, token = ++generation
        loading.value = true; error.value = ''
        pending = (async () => {
            try {
                const response = await api.get('/api/user', { signal })
                if (token === generation) { user.value = response.data.data; loaded = true }
            } catch (failure) {
                if (token !== generation || signal.aborted) return
                if (failure.response?.status === 401) { user.value = null; loaded = true }
                else { error.value = '帳號載入失敗，請重試。'; loaded = false }
            } finally {
                if (token === generation) { loading.value = false; pending = undefined }
            }
            return user.value
        })()
        return pending
    }
    const clear = () => {
        generation++; controller?.abort(); pending = undefined; loaded = true
        user.value = null; loading.value = false; error.value = ''
    }
    return { user, isLoggedIn, loading, error, load, clear }
}
