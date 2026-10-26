<script setup>
import { computed, onMounted, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from '../../axios'
import AppButton from '../../Components/AppButton.vue'
import ItemCard from '../../Components/ItemCard.vue'
import ItemInfoPanel from '../../Components/ItemInfoPanel.vue'
import SingleSelect from '../../Components/SingleSelect.vue'

const route = useRoute()
const router = useRouter()
const DEFAULT_STATUSES = ['pre_arrival', 'unused', 'in_use']
const statusOptions = [
    { value: 'pre_arrival', label: '未到貨' },
    { value: 'unused', label: '未使用' },
    { value: 'in_use', label: '使用中' },
    { value: 'unused_discarded', label: '未使用就棄用' },
    { value: 'used_discarded', label: '使用後棄用' },
]
const sortOptions = [
    { value: 'default', label: '預設（最新）' },
    { value: 'name_asc', label: '名稱（A-Z）' },
    { value: 'price_asc', label: '價格（低至高）' },
    { value: 'price_desc', label: '價格（高至低）' },
]
const queryText = value => typeof value === 'string' ? value : ''
const filtersFromQuery = query => ({
    search: queryText(query.search),
    category: queryText(query.category_id),
    sort: sortOptions.some(option => option.value === query.sort) ? query.sort : 'default',
    statuses: typeof query.statuses === 'string'
        ? query.statuses.split(',').filter(value => statusOptions.some(option => option.value === value))
        : [...DEFAULT_STATUSES],
})
const search = ref('')
const category = ref('')
const sort = ref('default')
const statuses = ref([...DEFAULT_STATUSES])
const filtersOpen = ref(false)
const items = ref([])
const selectedItem = ref(null)
const categories = ref([])
const pagination = ref({ current_page: 1, last_page: 1, total: 0 })
const categoryOptions = computed(() => [
    { value: '', label: '所有分類' },
    { value: 'none', label: '未分類' },
    ...categories.value.map(entry => ({ value: String(entry.id), label: entry.name })),
])
const loading = ref(false)
const error = ref('')
const categoryError = ref('')
const applied = computed(() => filtersFromQuery(route.query))
const sameStatuses = (values, other) => values.length === other.length && values.every(value => other.includes(value))
const hasCustomFilters = computed(() => Boolean(applied.value.search || applied.value.category || applied.value.sort !== 'default' || !sameStatuses(applied.value.statuses, DEFAULT_STATUSES)))
const summaries = computed(() => {
    const current = applied.value
    const values = []
    if (current.search) values.push({ key: 'search', text: `名稱：${current.search}`, removable: true })
    if (current.category) {
        const name = current.category === 'none' ? '未分類' : categories.value.find(value => String(value.id) === current.category)?.name || '已選分類'
        values.push({ key: 'category_id', text: `分類：${name}`, removable: true })
    }
    const defaultStatuses = sameStatuses(current.statuses, DEFAULT_STATUSES)
    const labels = current.statuses.map(value => statusOptions.find(option => option.value === value)?.label).join('、')
    values.push({ key: 'statuses', text: `狀態：${defaultStatuses ? '目前持有' : labels || '所有狀態'}`, removable: !defaultStatuses })
    if (current.sort !== 'default') values.push({ key: 'sort', text: `排序：${sortOptions.find(option => option.value === current.sort)?.label}`, removable: true })
    return values
})
let requestController
let categoryController
let requestVersion = 0

const fetchCategories = async () => {
    categoryController?.abort()
    categoryController = new AbortController()
    categoryError.value = ''
    try {
        const response = await axios.get('/api/categories', { params: { all: true }, signal: categoryController.signal })
        categories.value = Array.isArray(response.data?.data) ? response.data.data : []
    } catch (failure) {
        if (failure.code !== 'ERR_CANCELED') categoryError.value = '分類載入失敗，仍可使用其他搜尋條件。'
    }
}

const fetchItems = async () => {
    requestController?.abort()
    requestController = new AbortController()
    const version = ++requestVersion
    const filters = applied.value
    const requestedPage = Math.max(1, Number.parseInt(queryText(route.query.page), 10) || 1)
    loading.value = true
    error.value = ''
    try {
        const response = await axios.get('/api/items', {
            params: {
                page: requestedPage,
                search: filters.search || undefined,
                category_id: filters.category || undefined,
                statuses: filters.statuses.length ? filters.statuses.join(',') : undefined,
                sort: filters.sort !== 'default' ? filters.sort : undefined,
                per_page: 20,
            },
            signal: requestController.signal,
        })
        if (version !== requestVersion) return
        items.value = response.data.data
        pagination.value = response.data.meta
        if (!items.value.length && requestedPage > 1 && requestedPage > response.data.meta.last_page) {
            await navigate({ ...route.query, page: String(Math.max(1, response.data.meta.last_page)) })
        }
    } catch (failure) {
        if (failure.code !== 'ERR_CANCELED' && version === requestVersion) error.value = '物品載入失敗，請稍後重試。'
    } finally {
        if (version === requestVersion) loading.value = false
    }
}

const navigate = async query => {
    const destination = { path: '/items', query }
    if (router.resolve(destination).fullPath === route.fullPath) await fetchItems()
    else await router.push(destination)
}
const doSearch = async () => {
    await navigate({
        ...(search.value ? { search: search.value } : {}),
        ...(category.value ? { category_id: category.value } : {}),
        // 空字串區分「所有狀態」與未指定時的預設狀態，API 參數維持原有行為。
        statuses: statuses.value.join(','),
        ...(sort.value !== 'default' ? { sort: sort.value } : {}),
    })
}
const clearFilters = async () => { await navigate({}) }
const removeFilter = async key => {
    const query = { ...route.query }
    delete query[key]
    delete query.page
    await navigate(query)
}
const changePage = async page => {
    if (loading.value || page < 1 || page > pagination.value.last_page) return
    const query = { ...route.query }
    if (page > 1) query.page = String(page)
    else delete query.page
    await navigate(query)
}
watch(() => route.query, () => {
    selectedItem.value = null
    const filters = applied.value
    search.value = filters.search
    category.value = filters.category
    sort.value = filters.sort
    statuses.value = [...filters.statuses]
    fetchItems()
}, { immediate: true })
onMounted(fetchCategories)
onBeforeUnmount(() => {
    requestVersion++
    requestController?.abort()
    categoryController?.abort()
})
</script>

<template>
    <main class="items-page">
        <div class="items-container">
            <header class="page-heading">
                <h1>物品列表</h1>
                <router-link to="/items/create" class="create-link">新增物品</router-link>
            </header>
            <form class="filter-panel" @submit.prevent="doSearch">
                <div class="search-row">
                    <div class="field search-field">
                        <label for="items-search">搜尋名稱</label>
                        <input id="items-search" v-model="search" type="search" placeholder="輸入物品名稱" />
                    </div>
                    <AppButton type="submit" :disabled="loading">{{ loading ? '載入中…' : '搜尋' }}</AppButton>
                    <AppButton class="filter-toggle" variant="secondary" :aria-expanded="filtersOpen" aria-controls="items-filters" @click="filtersOpen = !filtersOpen">
                        {{ filtersOpen ? '收合篩選' : '展開篩選' }}
                    </AppButton>
                </div>
                <div id="items-filters" :class="['filters-grid', { 'is-open': filtersOpen }]">
                    <div class="field">
                        <label id="items-category-label" for="items-category">分類</label>
                        <SingleSelect id="items-category" v-model="category" label="分類" :options="categoryOptions" placeholder="搜尋或選擇分類" />
                    </div>
                    <div class="field">
                        <label id="items-sort-label" for="items-sort">排序</label>
                        <SingleSelect id="items-sort" v-model="sort" label="排序" :options="sortOptions" />
                    </div>
                    <fieldset class="status-field">
                        <legend>狀態（可多選，未勾選表示所有狀態）</legend>
                        <div class="status-options">
                            <label v-for="option in statusOptions" :key="option.value" class="status-option">
                                <input v-model="statuses" type="checkbox" :value="option.value" />{{ option.label }}
                            </label>
                        </div>
                    </fieldset>
                    <p class="filter-help">變更條件後按「搜尋」套用。</p>
                </div>
                <div v-if="categoryError" class="category-error" role="alert">
                    <span>{{ categoryError }}</span><AppButton variant="secondary" @click="fetchCategories">重試分類</AppButton>
                </div>
            </form>
            <div class="results-heading">
                <p role="status" aria-live="polite">{{ loading ? '正在載入物品…' : error ? '目前無法取得結果' : `共 ${pagination.total} 筆結果` }}</p>
                <AppButton v-if="hasCustomFilters" variant="secondary" :disabled="loading" @click="clearFilters">清除篩選</AppButton>
            </div>
            <div class="applied-filters" aria-label="已套用的條件">
                <template v-for="summary in summaries" :key="summary.key">
                    <button v-if="summary.removable" type="button" :disabled="loading" :aria-label="`清除${summary.text}`" @click="removeFilter(summary.key)">{{ summary.text }} · 清除</button>
                    <span v-else>{{ summary.text }}</span>
                </template>
            </div>
            <section v-if="error" class="message-panel" role="alert">
                <h2>暫時無法載入物品</h2><p>{{ error }}</p><AppButton @click="fetchItems">重試</AppButton>
            </section>
            <div v-else-if="loading && !items.length" class="items-grid skeleton-grid" aria-hidden="true">
                <div v-for="index in 6" :key="index" class="skeleton-card">
                    <div class="skeleton-heading">
                        <span class="skeleton-block skeleton-image"></span>
                        <div class="skeleton-title"><span class="skeleton-block"></span><span class="skeleton-block"></span><span class="skeleton-block skeleton-short"></span></div>
                    </div>
                    <span class="skeleton-block"></span>
                    <span class="skeleton-block skeleton-short"></span>
                    <span class="skeleton-block skeleton-link"></span>
                </div>
            </div>
            <section v-else-if="!loading && !items.length" class="message-panel">
                <h2>{{ hasCustomFilters ? '找不到符合條件的物品' : '目前沒有符合預設狀態的物品' }}</h2>
                <p>{{ hasCustomFilters ? '試著調整名稱、分類或狀態。' : '你可以新增物品，或展開篩選查看其他狀態。' }}</p>
                <AppButton v-if="hasCustomFilters" variant="secondary" @click="clearFilters">清除篩選</AppButton>
                <router-link v-else to="/items/create" class="create-link">新增物品</router-link>
            </section>
            <ul v-else class="items-grid" aria-label="物品" :aria-busy="loading" :inert="loading">
                <li v-for="item in items" :key="item.id"><ItemCard :item="item" @open="selectedItem = $event" /></li>
            </ul>
            <nav v-if="!error && pagination.total > 0" class="pagination" aria-label="物品分頁">
                <AppButton variant="secondary" :disabled="loading || pagination.current_page <= 1" @click="changePage(pagination.current_page - 1)">上一頁</AppButton>
                <span>第 {{ pagination.current_page }} 頁／共 {{ pagination.last_page }} 頁</span>
                <AppButton variant="secondary" :disabled="loading || pagination.current_page >= pagination.last_page" @click="changePage(pagination.current_page + 1)">下一頁</AppButton>
            </nav>
        </div>
        <ItemInfoPanel :item="selectedItem" @close="selectedItem = null" />
    </main>
</template>

<style scoped>
.items-page { container: items-list / inline-size; min-height: 100vh; background: var(--canvas); color: var(--text); }
.items-container { width: 100%; max-width: 1200px; margin: 0 auto; padding: 24px 16px 32px; }
.page-heading { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 24px; }
h1 { margin: 0; font-size: 24px; line-height: 1.3; font-weight: 600; }
.create-link { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; padding: 10px 16px; border: 1px solid var(--border-control); border-radius: var(--radius-control); background: var(--surface); color: var(--text); font-size: 14px; font-weight: 500; text-decoration: none; white-space: nowrap; }
.create-link:hover { background: var(--brand-soft); border-color: var(--brand-ink); }
.filter-panel { padding: 16px; background: var(--surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-panel); }
.search-row { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 12px; }
.field { min-width: 0; display: flex; flex-direction: column; gap: 8px; }
.field label, legend { font-size: 14px; line-height: 1.5; font-weight: 500; }
.search-field { flex: 1 1 100%; }
.field input, .field select { width: 100%; min-width: 0; min-height: 44px; padding: 10px 12px; border: 1px solid var(--border-control); border-radius: var(--radius-control); color: var(--text); background: var(--surface); font-size: 16px; line-height: 1.5; }
.field input::placeholder { color: var(--text-muted); opacity: 1; }
.filters-grid { display: none; grid-template-columns: minmax(0, 1fr); gap: 16px; margin-top: 24px; }
.filters-grid.is-open { display: grid; }
.status-field { min-width: 0; margin: 0; padding: 0; border: 0; }
.status-field legend { margin-bottom: 8px; }
.status-options { display: flex; flex-wrap: wrap; gap: 4px 16px; }
.status-option { display: inline-flex; align-items: center; gap: 8px; min-height: 44px; font-size: 14px; cursor: pointer; }
.status-option input { width: 20px; height: 20px; accent-color: var(--brand); }
.filter-help { margin: 0; font-size: 14px; color: var(--text-muted); }
.category-error { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-top: 16px; font-size: 14px; color: var(--danger); }
.results-heading { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; margin-top: 24px; }
.results-heading p { margin: 0; font-size: 14px; color: var(--text-muted); }
.applied-filters { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin: 12px 0 24px; }
.applied-filters button, .applied-filters span { min-width: 0; max-width: 100%; padding: 8px 12px; border: 1px solid var(--border-subtle); border-radius: var(--radius-control); background: var(--brand-soft); color: var(--brand-ink); font-size: 14px; overflow-wrap: anywhere; }
.applied-filters button { min-height: 44px; cursor: pointer; text-align: left; }
.applied-filters button:disabled { cursor: not-allowed; }
.items-grid { list-style: none; padding: 0; margin: 0; display: grid; grid-template-columns: minmax(0, 1fr); gap: 16px; }
.items-grid > li { min-width: 0; }
:global(html:has(.items-page)) { scrollbar-gutter: stable; }
.skeleton-card { min-height: 104px; padding: 16px; display: flex; flex-direction: column; gap: 16px; background: var(--surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-panel); }
.skeleton-heading { display: flex; align-items: center; gap: 12px; }
.skeleton-card > .skeleton-block { display: none; }
.skeleton-block { display: block; height: 16px; border-radius: 4px; background: var(--border-subtle); }
.skeleton-image { width: 64px; height: 64px; flex-shrink: 0; border-radius: var(--radius-control); }
.skeleton-title { flex: 1; min-width: 0; display: grid; gap: 12px; }
.skeleton-short { width: 60%; }
.skeleton-link { width: 76px; margin-top: auto; }
.skeleton-card:nth-child(n + 3) { display: none; }
.message-panel { display: flex; flex-direction: column; align-items: center; gap: 16px; padding: 32px 16px; text-align: center; background: var(--surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-panel); }
.message-panel h2 { margin: 0; font-size: 18px; font-weight: 600; }
.message-panel p { margin: 0; color: var(--text-muted); }
.pagination { display: flex; flex-wrap: wrap; justify-content: center; align-items: center; gap: 12px; margin-top: 24px; }
.pagination span { font-size: 14px; color: var(--text-muted); }
a:focus-visible, button:focus-visible, input:focus-visible, select:focus-visible { outline: 2px solid var(--brand-ink); outline-offset: 2px; }
@container items-list (min-width: 768px) {
    .items-container { padding-top: 32px; }
    h1 { font-size: 28px; }
    .search-field { flex-basis: 0; }
    .filter-toggle { display: none; }
    .filters-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .status-field, .filter-help { grid-column: 1 / -1; }
}
</style>
