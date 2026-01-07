<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from '../../axios'

const route = useRoute()
const router = useRouter()

const items = ref([])
const pagination = ref({
    current_page: 1,
    last_page: 1,
})

const days = ref(route.query.days ? parseInt(route.query.days) : 30)
const loading = ref(false)
const debugInfo = ref(null)
const rangeStatistics = ref({})

// 日期範圍選項
const dateRangeOptions = [
    { days: 7, label: '本週', icon: '📅' },
    { days: 30, label: '本月', icon: '🗓️' },
    { days: 90, label: '三個月', icon: '📊' },
    { days: 180, label: '半年', icon: '📈' },
    { days: 365, label: '一年', icon: '📉' },
    { days: 1095, label: '三年', icon: '📚' },
]

// 計算範圍標籤
const getRangeLabel = (dayValue) => {
    const option = dateRangeOptions.find(opt => opt.days === dayValue)
    return option ? `${option.icon} ${option.label}` : `${dayValue} 天`
}

// 取得範圍的統計數量
const getRangeCount = (dayValue) => {
    return rangeStatistics.value[dayValue] || 0
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

// 計算距離過期還有幾天
const getDaysUntilExpiration = (expirationDate) => {
    if (!expirationDate) return null
    const today = new Date()
    today.setHours(0, 0, 0, 0)
    const expDate = new Date(expirationDate)
    expDate.setHours(0, 0, 0, 0)
    const diffTime = expDate - today
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))
    return diffDays
}

// 取得過期警告顏色
const getExpirationColor = (daysUntil) => {
    if (daysUntil === null) return 'text-gray-600'
    if (daysUntil < 0) return 'text-red-600 font-bold'
    if (daysUntil <= 3) return 'text-red-500 font-semibold'
    if (daysUntil <= 7) return 'text-orange-500 font-semibold'
    return 'text-yellow-600'
}

// 取得過期警告標籤
const getExpirationLabel = (daysUntil) => {
    if (daysUntil === null) return ''
    if (daysUntil < 0) return '⚠️ 已過期'
    if (daysUntil === 0) return '⚠️ 今天過期'
    if (daysUntil === 1) return '⚠️ 明天過期'
    if (daysUntil <= 3) return `⚠️ ${daysUntil} 天後過期`
    if (daysUntil <= 7) return `⏰ ${daysUntil} 天後過期`
    if (daysUntil >= 365) {
        const years = Math.floor(daysUntil / 365)
        const remainingDays = daysUntil % 365
        if (remainingDays === 0) {
            return `📅 ${years} 年後過期`
        }
        const months = Math.floor(remainingDays / 30)
        if (months === 0) {
            return `📅 ${years} 年 ${remainingDays} 天後過期`
        }
        return `📅 ${years} 年 ${months} 個月後過期`
    }
    if (daysUntil >= 30) {
        const months = Math.floor(daysUntil / 30)
        const remainingDays = daysUntil % 30
        if (remainingDays === 0) {
            return `📅 ${months} 個月後過期`
        }
        return `📅 ${months} 個月 ${remainingDays} 天後過期`
    }
    return `📅 ${daysUntil} 天後過期`
}

const formatPrice = (val) => {
    if (val == null) return '-'
    return Number(val).toLocaleString('zh-TW')
}

const formatDate = (date) => {
    if (!date) return '-'
    return new Date(date).toLocaleDateString('zh-TW', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit'
    })
}

const fetchItems = async (page = 1) => {
    loading.value = true
    
    // 更新 URL 參數
    const query = {
        ...(days.value !== 30 ? { days: days.value } : {}),
        ...(page > 1 ? { page } : {}),
    }
    
    // 更新瀏覽器 URL
    router.push({
        path: '/expiring-items',
        query: query
    })
    
    try {
        const res = await axios.get('/api/items/expiring-soon', {
            params: {
                page,
                days: days.value,
                per_page: 20,
            },
        })
        items.value = res.data.items
        pagination.value = res.data.meta
        
        // 更新範圍統計
        if (res.data.range_statistics) {
            rangeStatistics.value = res.data.range_statistics
        }
        
        // 更新總數（用於智能提示）
        if (res.data.total_all_with_expiration_date !== undefined) {
            debugInfo.value = {
                total_all_with_expiration_date: res.data.total_all_with_expiration_date
            }
        } else {
            debugInfo.value = null
        }
    } catch (error) {
        console.error('載入資料失敗:', error)
    } finally {
        loading.value = false
    }
}

const updateDays = () => {
    fetchItems(1)
}

onMounted(() => {
    fetchItems(Number(route.query.page) || 1)
})
</script>

<template>
    <div class="bg-[#f5f5f5] min-h-screen p-4 max-w-2xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">⏰ 近期過期商品</h1>
        </div>

        <!-- 日期範圍選擇 -->
        <div class="mb-6">
            <div class="mb-3">
                <label class="text-sm text-gray-700 font-medium block mb-2">
                    選擇查看範圍：
                </label>
                <!-- 快速選擇按鈕組 -->
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="option in dateRangeOptions"
                        :key="option.days"
                        @click="days = option.days; updateDays()"
                        :class="[
                            'px-4 py-2 rounded-lg text-sm font-medium transition-all relative',
                            days === option.days
                                ? 'bg-blue-500 text-white shadow-md scale-105'
                                : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50 hover:border-blue-300'
                        ]"
                    >
                        <span>{{ option.icon }} {{ option.label }}</span>
                        <span 
                            v-if="getRangeCount(option.days) > 0"
                            :class="[
                                'ml-2 px-1.5 py-0.5 rounded-full text-xs font-bold',
                                days === option.days
                                    ? 'bg-white text-blue-500'
                                    : 'bg-blue-100 text-blue-600'
                            ]"
                        >
                            {{ getRangeCount(option.days) }}
                        </span>
                    </button>
                </div>
            </div>
            
            <!-- 當前選擇和統計 -->
            <div class="flex items-center justify-between bg-white rounded-lg p-3 border border-gray-200">
                <div class="flex items-center gap-2">
                    <span class="text-sm text-gray-600">目前查看：</span>
                    <span class="text-sm font-semibold text-blue-600">{{ getRangeLabel(days) }}</span>
                </div>
                <div v-if="pagination.total !== null" class="text-sm">
                    <span class="text-gray-600">共</span>
                    <span class="font-bold text-blue-600 mx-1">{{ pagination.total }}</span>
                    <span class="text-gray-600">筆商品</span>
                </div>
            </div>

            <!-- 智能提示 -->
            <div v-if="debugInfo && debugInfo.total_all_with_expiration_date > 0 && pagination.total === 0" 
                 class="mt-3 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                <div class="flex items-start gap-2">
                    <span class="text-yellow-600 text-lg">💡</span>
                    <div class="flex-1">
                        <div class="text-sm font-semibold text-yellow-800 mb-1">提示</div>
                        <div class="text-xs text-yellow-700">
                            目前範圍內沒有商品，但您有 <strong>{{ debugInfo.total_all_with_expiration_date }}</strong> 筆商品有設定過期日期。
                            建議選擇更大的範圍查看。
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="loading" class="text-center text-gray-500 py-8">載入中...</div>

        <div v-else-if="items.length === 0" class="text-center text-gray-500 py-8">
            <div class="text-6xl mb-4">🎉</div>
            <p class="text-lg font-semibold mb-2">太好了！</p>
            <p class="text-sm">在「{{ getRangeLabel(days) }}」範圍內沒有即將過期的商品。</p>
        </div>

        <ul v-else class="space-y-4">
            <li
                v-for="item in items"
                :key="item.id"
                class="bg-white rounded-2xl shadow-md p-4 flex flex-col gap-2 transition hover:shadow-lg"
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
                        
                        <!-- 過期資訊 -->
                        <div v-if="item.expiration_date" class="mt-2">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span 
                                    :class="['text-sm font-medium', getExpirationColor(getDaysUntilExpiration(item.expiration_date))]"
                                >
                                    {{ getExpirationLabel(getDaysUntilExpiration(item.expiration_date)) }}
                                </span>
                                <span class="text-sm text-gray-500">
                                    （過期日期：{{ formatDate(item.expiration_date) }}）
                                </span>
                            </div>
                        </div>

                        <div class="text-sm text-gray-500 mt-1">
                            <!-- 狀態標籤 -->
                            <div class="flex items-center justify-between mt-1">
                                <div class="flex items-center gap-2">
                                    <span 
                                        v-if="item.status" 
                                        :class="['px-2 py-1 rounded-full text-xs font-medium', getStatusInfo(item.status).color]"
                                    >
                                        {{ getStatusInfo(item.status).label }}
                                    </span>
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
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </li>
        </ul>

        <!-- 分頁按鈕 -->
        <div v-if="pagination.last_page > 1" class="flex justify-center items-center gap-4 mt-6">
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
</template>

