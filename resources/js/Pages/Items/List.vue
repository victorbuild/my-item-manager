<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from '../../axios'
import Multiselect from '@vueform/multiselect'
import '@vueform/multiselect/themes/default.css'

const route = useRoute()
const router = useRouter()

// 預設的狀態（棄用以外）
const DEFAULT_STATUSES = ['pre_arrival', 'unused', 'in_use']

const statuses = ref([])

const items = ref([])
const pagination = ref({
    current_page: 1,
    last_page: 1,
})

const search = ref(route.query.search || '')

const category = ref(route.query.category_id || '')
const categories = ref([])

const perPage = ref('20')

// Tooltip 相關
const showTooltip = ref(false)
const tooltipItem = ref(null)
const tooltipPosition = ref({ x: 0, y: 0 })


const fetchCategories = async () => {
    // 取得所有分類（不分頁），用於下拉選單
    const res = await axios.get('/api/categories', { params: { all: true } })
    // 處理返回的數據結構
    categories.value = res.data.items || res.data || []
}

const doSearch = () => {
    fetchItems(1)
}

const clearFilters = () => {
    search.value = ''
    category.value = ''
    statuses.value = [...DEFAULT_STATUSES]
    fetchItems(1)
}

const formatPrice = (val) => {
    if (val == null) return '-'
    return Number(val).toLocaleString('zh-TW')
}

// 狀態翻譯和顏色
const getStatusInfo = (status) => {
    const statusMap = {
        'pre_arrival': { label: '📦 未到貨', color: 'bg-orange-100 text-orange-800' },
        'unused': { label: '📚 未使用', color: 'bg-blue-100 text-blue-800' },
        'in_use': { label: '✅ 使用中', color: 'bg-green-100 text-green-800' },
        'unused_discarded': { label: '⚠️ 未使用就棄用', color: 'bg-red-100 text-red-800' },
        'used_discarded': { label: '🗑️ 使用後棄用', color: 'bg-gray-100 text-gray-800' }
    }
    return statusMap[status] || { label: status, color: 'bg-gray-100 text-gray-800' }
}

const fetchItems = async (page = 1) => {
    // 更新 URL 參數
    const query = {
        ...(search.value ? { search: search.value } : {}),
        ...(category.value ? { category_id: category.value } : {}),
        ...(statuses.value.length ? { statuses: statuses.value.join(',') } : {}),
        ...(page > 1 ? { page } : {}),
    }
    
    // 更新瀏覽器 URL
    router.push({
        path: '/items',
        query: query
    })
    
    const res = await axios.get('/api/items', {
        params: {
            page,
            search: search.value || undefined,
            category_id: category.value || undefined,
            statuses: statuses.value.length ? statuses.value.join(',') : undefined,
            per_page: perPage.value,
        },
    })
    items.value = res.data.items
    pagination.value = res.data.meta
}

const confirmDelete = async (id) => {
    if (confirm('確定要刪除這筆資料嗎？')) {
        await axios.delete(`/api/items/${id}`)
        fetchItems(pagination.value.current_page)
    }
}

// 顯示 Tooltip
const showItemTooltip = (item, event) => {
    tooltipItem.value = item
    showTooltip.value = true
    
    // 計算位置
    const rect = event.target.getBoundingClientRect()
    tooltipPosition.value = {
        x: rect.left + rect.width / 2,
        y: rect.top - 10
    }
}

// 隱藏 Tooltip
const hideTooltip = () => {
    showTooltip.value = false
    tooltipItem.value = null
}

onMounted(() => {

    // 初始化篩選狀態
    if (route.query.statuses) {
        statuses.value = route.query.statuses.split(',')
    } else {
        statuses.value = [...DEFAULT_STATUSES]
    }

    fetchCategories()
    fetchItems(Number(route.query.page) || 1)
    
    // 監聽滾動事件，滾動時隱藏 Tooltip
    window.addEventListener('scroll', hideTooltip)
    window.addEventListener('resize', hideTooltip)
})

onUnmounted(() => {
    // 清理事件監聽器
    window.removeEventListener('scroll', hideTooltip)
    window.removeEventListener('resize', hideTooltip)
})
</script>

<template>
    <div class="bg-[#f5f5f5] min-h-screen p-4 max-w-2xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">📦 物品列表</h1>
            <router-link to="/items/create" class="bg-blue-500 text-white px-4 py-2 rounded shadow hover:bg-blue-600">
                新增
            </router-link>
        </div>

        <!-- 搜尋列 -->
        <form @submit.prevent="doSearch" class="mb-6 flex flex-wrap gap-3 items-center">
            <input
                v-model="search"
                type="text"
                placeholder="🔍 搜尋名稱"
                class="flex-1 min-w-[150px] px-4 py-2 border border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-300"
            />

            <select
                v-model="category"
                class="min-w-[120px] px-4 py-2 border border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-300"
            >
                <option value="">📂 所有分類</option>
                <option value="none">🚫 未分類</option>
                <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                    📁 {{ cat.name }}
                </option>
            </select>

            <Multiselect
                v-model="statuses"
                mode="tags"
                :close-on-select="false"
                :searchable="false"
                :options="[
    { value: 'pre_arrival', label: '📦 未到貨' },
    { value: 'unused', label: '📚 未使用' },
    { value: 'in_use', label: '✅ 使用中' },
    { value: 'unused_discarded', label: '⚠️ 未使用就棄用' },
    { value: 'used_discarded', label: '🗑️ 使用後棄用' }
  ]"
                placeholder="📊 選擇狀態（可多選）"
                class="min-w-[200px]"
            />


            <button
                type="submit"
                class="px-5 py-2 bg-blue-500 text-white font-medium rounded-xl shadow hover:bg-blue-600 transition"
            >
                🔍 搜尋
            </button>

            <button
                v-if="search || category || statuses.length !== DEFAULT_STATUSES.length"
                type="button"
                @click="clearFilters"
                class="text-sm text-gray-500 underline ml-2"
            >
                ❌ 清除
            </button>

            <span v-if="pagination.total !== null" class="text-sm text-gray-600 ml-2">
                （符合條件的 {{ pagination.total }} 筆結果）
            </span>
        </form>

        <ul class="space-y-4">
            <li
                v-for="item in items"
                :key="item.id"
                class="bg-white rounded-2xl shadow-md p-4 flex flex-col gap-1 transition hover:shadow-lg"
            >
                <!-- 名稱和資訊 -->
                <div class="flex items-start gap-3">
                    <!-- 主圖 -->
                    <div v-if="item.main_image" class="w-16 h-16 rounded-lg overflow-hidden bg-gray-200 flex-shrink-0">
                        <img 
                            :src="item.main_image.thumb_url" 
                            :alt="item.name"
                            class="w-full h-full object-cover"
                            @error="$event.target.style.display='none'"
                        />
                    </div>
                    <div v-else class="w-16 h-16 rounded-lg bg-gray-200 flex items-center justify-center flex-shrink-0">
                        <span class="text-gray-400 text-xl">📦</span>
                    </div>
                    
                    <!-- 物品資訊 -->
                    <div class="flex-1 min-w-0">
                        <router-link 
                            :to="`/items/${item.short_id}`" 
                            class="font-semibold text-base text-gray-800 hover:text-gray-600 active:text-gray-600 break-words leading-tight cursor-pointer transition-colors"
                            title="點擊查看詳情"
                        >
                            {{ item.name }}
                        </router-link>
                        <div class="text-sm text-gray-500 mt-1">
                            <!-- 狀態標籤和操作按鈕 -->
                            <div class="flex items-center justify-between mt-1">
                                <div class="flex items-center gap-2">
                                    <span 
                                        v-if="item.status" 
                                        :class="['px-2 py-1 rounded-full text-xs font-medium', getStatusInfo(item.status).color]"
                                    >
                                        {{ getStatusInfo(item.status).label }}
                                    </span>
                                    <button 
                                        @mouseenter="showItemTooltip(item, $event)"
                                        @mouseleave="hideTooltip"
                                        class="w-4 h-4 rounded-full bg-gray-200 hover:bg-gray-300 flex items-center justify-center text-gray-600 hover:text-gray-800 transition-colors"
                                        title="查看詳細資訊"
                                    >
                                        <span class="text-xs font-bold">i</span>
                                    </button>
                                </div>
                                <!-- 操作按鈕 -->
                                <div class="flex gap-2">
                                    <router-link 
                                        :to="`/items/${item.short_id}/edit`" 
                                        class="w-8 h-8 rounded-full bg-blue-100 hover:bg-blue-200 flex items-center justify-center text-blue-600 hover:text-blue-800 transition-colors"
                                        title="編輯"
                                    >
                                        <span class="text-sm">✏️</span>
                                    </router-link>
                                    <button 
                                        @click="confirmDelete(item.short_id)" 
                                        class="w-8 h-8 rounded-full bg-red-100 hover:bg-red-200 flex items-center justify-center text-red-500 hover:text-red-700 transition-colors"
                                        title="刪除"
                                    >
                                        <span class="text-sm">🗑️</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </li>
        </ul>

        <!-- 分頁按鈕 -->
        <div class="flex justify-center items-center gap-4 mt-6">
            <button
                @click="fetchItems(pagination.current_page - 1)"
                :disabled="pagination.current_page === 1"
                class="px-4 py-2 bg-gray-200 rounded disabled:opacity-50"
            >
                ← 上一頁
            </button>

            <span class="text-sm">第 {{ pagination.current_page }} 頁 / 共 {{ pagination.last_page }} 頁</span>

            <button
                @click="fetchItems(pagination.current_page + 1)"
                :disabled="pagination.current_page === pagination.last_page"
                class="px-4 py-2 bg-gray-200 rounded disabled:opacity-50"
            >
                下一頁 →
            </button>
        </div>
    </div>

    <!-- Tooltip -->
    <div 
        v-if="showTooltip && tooltipItem" 
        class="fixed z-50 pointer-events-none"
        :style="{ 
            left: tooltipPosition.x + 'px', 
            top: tooltipPosition.y + 'px',
            transform: 'translateX(-50%) translateY(-100%)'
        }"
    >
        <div class="bg-gray-800 text-white text-xs rounded-lg p-3 shadow-lg max-w-xs">
            <div class="whitespace-nowrap">
                💰 金額：{{ formatPrice(tooltipItem.price) }}<br />
                📅 購買日期：{{ tooltipItem.purchased_at || '（未填寫）' }}<br />
                📦 到貨日期：{{ tooltipItem.received_at || '（未填寫）' }}<br />
                🚀 開始使用日期：{{ tooltipItem.used_at || '（未填寫）' }}<br />
                🗑️ 棄用日：{{ tooltipItem.discarded_at || '（未填寫）' }}
            </div>
            <!-- 箭頭 -->
            <div class="absolute top-full left-1/2 transform -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-t-4 border-transparent border-t-gray-800"></div>
        </div>
    </div>
</template>

