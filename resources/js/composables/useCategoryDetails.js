import { computed, onScopeDispose, ref, watch } from 'vue'

export function useCategoryDetails(route, router, api) {
    const page = computed(() => {
        const value = Number(route.query.page)
        return !Array.isArray(route.query.page) && Number.isSafeInteger(value) && value > 0 ? value : 1
    })
    const category = ref(null), stats = ref(null), products = ref([])
    const pagination = ref({ current_page: 1, last_page: 1, total: 0 })
    const loading = ref(false), error = ref('')
    let controller
    const reload = async () => {
        controller?.abort()
        const request = new AbortController()
        controller = request
        loading.value = true
        error.value = ''
        try {
            const response = await api.get(`/api/categories/${route.params.id}`, {
                signal: request.signal, params: { page: page.value, per_page: 10 },
            })
            if (request.signal.aborted) return
            category.value = response.data.items[0]
            stats.value = response.data.stats
            products.value = response.data.products
            pagination.value = response.data.meta
        } catch {
            if (!request.signal.aborted) error.value = '載入分類失敗，請重試。'
        } finally {
            if (!request.signal.aborted) loading.value = false
        }
    }
    const goToPage = value => {
        if (!Number.isSafeInteger(value) || value < 1 || value > pagination.value.last_page || value === page.value) return
        return router.push({ path: route.path, query: { ...route.query, page: value === 1 ? undefined : value } })
    }
    watch([() => route.params.id, page], reload, { immediate: true })
    onScopeDispose(() => controller?.abort())
    return { category, stats, products, pagination, loading, error, reload, goToPage }
}
