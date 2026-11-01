import { computed, ref, watch } from 'vue'

export function useDiscardedItems(route, router, api) {
    const positiveInteger = (value, fallback) => {
        const number = Number(value)
        return !Array.isArray(value) && Number.isSafeInteger(number) && number > 0 ? number : fallback
    }
    const textQuery = value => typeof value === 'string' ? value : ''
    const currentPage = computed(() => positiveInteger(route.query.page, 1))
    const search = computed(() => textQuery(route.query.search))
    const category = computed(() => textQuery(route.query.category_id))
    const perPage = computed(() => positiveInteger(route.query.per_page, 10))
    const items = ref([])
    const pagination = ref(null)
    const loading = ref(false)
    const error = ref('')

    const goToPage = page => {
        if (!Number.isSafeInteger(page) || page < 1 || page === currentPage.value) return
        if (pagination.value && page > pagination.value.last_page) return
        return router.push({ path: route.path, query: { ...route.query, page: page === 1 ? undefined : page } })
    }

    // 返回、前進與直接進入網址皆使用相同資料載入流程。
    watch([currentPage, search, category, perPage], async ([page, query, categoryId, pageSize], previous, onCleanup) => {
        const controller = new AbortController()
        onCleanup(() => controller.abort())
        loading.value = true
        error.value = ''
        try {
            const response = await api.get('/api/items', {
                signal: controller.signal,
                params: {
                    page,
                    search: query || undefined,
                    category_id: categoryId || undefined,
                    statuses: 'unused_discarded,used_discarded',
                    sort: 'discarded',
                    per_page: pageSize,
                },
            })
            if (controller.signal.aborted) return
            items.value = response.data.data
            pagination.value = response.data.meta
        } catch (cause) {
            if (!controller.signal.aborted) error.value = '載入資料失敗，請重新整理頁面後再試。'
        } finally {
            if (!controller.signal.aborted) loading.value = false
        }
    }, { immediate: true })

    return { items, pagination, currentPage, loading, error, goToPage }
}
