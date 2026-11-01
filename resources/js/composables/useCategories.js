import { computed, onScopeDispose, ref, watch } from 'vue'

export function useCategories(route, router, api) {
    const query = computed(() => typeof route.query.q === 'string' ? route.query.q : '')
    const page = computed(() => {
        const value = Number(route.query.page)
        return !Array.isArray(route.query.page) && Number.isSafeInteger(value) && value > 0 ? value : 1
    })
    const search = ref(query.value)
    const categories = ref([])
    const pagination = ref({ current_page: 1, last_page: 1, per_page: 10, total: 0 })
    const loading = ref(false)
    const error = ref('')
    let controller

    const navigate = (q, nextPage) => {
        if (q === query.value && nextPage === page.value) return
        return router.push({ path: route.path, query: {
            ...route.query,
            q: q || undefined,
            page: nextPage === 1 ? undefined : nextPage,
        } })
    }
    const submitSearch = () => navigate(search.value, 1)
    const clearSearch = () => {
        search.value = ''
        return navigate('', 1)
    }
    const goToPage = value => {
        if (!Number.isSafeInteger(value) || value < 1 || value > pagination.value.last_page) return
        return navigate(query.value, value)
    }
    // 重新載入（例如刪除後）只讀網址，不新增瀏覽紀錄，也不套用未送出的搜尋草稿。
    const reload = async () => {
        controller?.abort()
        const request = new AbortController()
        controller = request
        loading.value = true
        error.value = ''
        try {
            const response = await api.get('/api/categories', {
                signal: request.signal,
                params: { page: page.value, q: query.value || undefined, per_page: 10 },
            })
            if (request.signal.aborted) return
            const lastPage = response.data.meta?.last_page
            if (Number.isSafeInteger(lastPage) && lastPage > 0 && page.value > lastPage) {
                await router.replace({ path: route.path, query: { ...route.query, page: lastPage === 1 ? undefined : lastPage } })
                return
            }
            categories.value = Array.isArray(response.data.data) ? response.data.data : []
            pagination.value = response.data.meta
        } catch (cause) {
            if (!request.signal.aborted) error.value = '無法取得分類資料，請稍後再試。'
        } finally {
            if (!request.signal.aborted) loading.value = false
        }
    }

    watch([query, page], () => {
        search.value = query.value
        reload()
    }, { immediate: true })
    onScopeDispose(() => controller?.abort())

    return { search, appliedSearch: query, categories, pagination, loading, error, submitSearch, clearSearch, goToPage, reload }
}
