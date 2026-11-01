import { computed, ref, watch } from 'vue'

// 已套用的範圍與頁碼只由網址決定，返回導覽和直接開啟網址共用相同流程。
export function useExpiringItems(route, router, api) {
    const positiveInteger = (value, fallback) => {
        const number = Number(value)
        return !Array.isArray(value) && Number.isSafeInteger(number) && number > 0 ? number : fallback
    }
    const days = computed(() => positiveInteger(route.query.days, 30))
    const page = computed(() => positiveInteger(route.query.page, 1))
    const items = ref([])
    const pagination = ref({ current_page: 1, last_page: 1 })
    const loading = ref(false)
    const error = ref('')
    const debugInfo = ref(null)
    const rangeStatistics = ref({})

    const navigate = (nextDays, nextPage) => {
        if (nextDays === days.value && nextPage === page.value) return
        return router.push({
            path: route.path,
            query: {
                ...route.query,
                days: nextDays === 30 ? undefined : nextDays,
                page: nextPage === 1 ? undefined : nextPage,
            },
        })
    }
    const selectDays = value => navigate(value, 1)
    const goToPage = value => navigate(days.value, value)

    watch([days, page], async ([currentDays, currentPage], previous, onCleanup) => {
        const controller = new AbortController()
        onCleanup(() => controller.abort())
        loading.value = true
        error.value = ''
        try {
            const response = await api.get('/api/items/expiring-soon', {
                signal: controller.signal,
                params: { days: currentDays, page: currentPage, per_page: 20 },
            })
            if (controller.signal.aborted) return
            items.value = response.data.data
            pagination.value = response.data.meta
            rangeStatistics.value = response.data.range_statistics || {}
            debugInfo.value = response.data.total_all_with_expiration_date !== undefined
                ? { total_all_with_expiration_date: response.data.total_all_with_expiration_date }
                : null
        } catch (cause) {
            if (controller.signal.aborted) return
            error.value = '載入資料失敗，請重新整理頁面後再試。'
        } finally {
            if (!controller.signal.aborted) loading.value = false
        }
    }, { immediate: true })

    return { days, items, pagination, loading, error, debugInfo, rangeStatistics, selectDays, goToPage }
}
