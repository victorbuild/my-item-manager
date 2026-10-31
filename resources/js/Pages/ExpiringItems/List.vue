<script setup>
import '../../../css/aligned-pages.css'
import AppIcon from '../../Components/AppIcon.vue'
import ItemThumbnail from '../../Components/ItemThumbnail.vue'
import { useExpiringItems } from '../../composables/useExpiringItems'
import { useRoute, useRouter } from 'vue-router'
import axios from '../../axios'

const route = useRoute()
const router = useRouter()

const { days, items, pagination, loading, error, debugInfo, rangeStatistics, selectDays, goToPage } = useExpiringItems(route, router, axios)

// 日期範圍選項
const dateRangeOptions = [
    { days: 7, label: '本週' },
    { days: 30, label: '本月' },
    { days: 90, label: '三個月' },
    { days: 180, label: '半年' },
    { days: 365, label: '一年' },
    { days: 1095, label: '三年' },
]

// 計算範圍標籤
const getRangeLabel = (dayValue) => {
    const option = dateRangeOptions.find(opt => opt.days === dayValue)
    return option ? option.label : `${dayValue} 天`
}

// 取得範圍的統計數量
const getRangeCount = (dayValue) => {
    return rangeStatistics.value[dayValue] || 0
}

// 狀態翻譯和顏色
const getStatusInfo = (status) => {
    const statusMap = {
        'pre_arrival': { label: '未到貨', color: 'bg-[var(--info-soft)] text-[var(--info)]' },
        'unused': { label: '未使用', color: 'bg-[var(--canvas)] text-[var(--text-muted)]' },
        'in_use': { label: '使用中', color: 'bg-[var(--success-soft)] text-[var(--success)]' },
        'unused_discarded': { label: '未使用就棄用', color: 'bg-[var(--canvas)] text-[var(--text-muted)]' },
        'used_discarded': { label: '使用後棄用', color: 'bg-[var(--canvas)] text-[var(--text-muted)]' }
    }
    return statusMap[status] || { label: status, color: 'bg-[var(--brand-soft)] text-[var(--text)]' }
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
    if (daysUntil === null) return 'text-[var(--text-muted)]'
    if (daysUntil <= 7) return 'text-[var(--danger)] font-semibold'
    if (daysUntil <= 90) return 'text-[var(--warning)] font-semibold'
    return 'text-[var(--text)]'
}

// 取得過期警告標籤
const getExpirationLabel = (daysUntil) => {
    if (daysUntil === null) return ''
    if (daysUntil < 0) return '已過期'
    if (daysUntil === 0) return '今天過期'
    if (daysUntil === 1) return '明天過期'
    if (daysUntil <= 3) return `${daysUntil} 天後過期`
    if (daysUntil <= 7) return `${daysUntil} 天後過期`
    if (daysUntil >= 365) {
        const years = Math.floor(daysUntil / 365)
        const remainingDays = daysUntil % 365
        if (remainingDays === 0) {
            return `${years} 年後過期`
        }
        const months = Math.floor(remainingDays / 30)
        if (months === 0) {
            return `${years} 年 ${remainingDays} 天後過期`
        }
        return `${years} 年 ${months} 個月後過期`
    }
    if (daysUntil >= 30) {
        const months = Math.floor(daysUntil / 30)
        const remainingDays = daysUntil % 30
        if (remainingDays === 0) {
            return `${months} 個月後過期`
        }
        return `${months} 個月 ${remainingDays} 天後過期`
    }
    return `${daysUntil} 天後過期`
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

</script>

<template>
    <div class="aligned-page bg-[var(--canvas)] min-h-screen p-4 mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">近期過期物品</h1>
        </div>

        <!-- 日期範圍選擇 -->
        <div class="mb-6">
            <div class="mb-3">
                <label class="text-sm text-[var(--text-muted)] font-medium block mb-2">
                    選擇查看範圍：
                </label>
                <!-- 快速選擇按鈕組 -->
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="option in dateRangeOptions"
                        :key="option.days"
                        @click="selectDays(option.days)"
                        :class="[
                            'px-4 py-2 rounded-lg text-sm font-medium transition-all relative',
                            days === option.days
                                ? 'bg-[var(--brand)] text-[var(--on-brand)] border border-[var(--border-subtle)]'
                                : 'bg-[var(--surface)] text-[var(--text-muted)] border border-[var(--border-control)] hover:bg-[var(--canvas)] hover:border-[var(--border-control)]'
                        ]"
                    >
                        <span>{{ option.label }}</span>
                        <span 
                            v-if="getRangeCount(option.days) > 0"
                            :class="[
                                'ml-2 px-1.5 py-0.5 rounded-full text-xs font-bold',
                                days === option.days
                                    ? 'bg-[var(--surface)] text-[var(--brand-ink)]'
                                    : 'bg-[var(--brand-soft)] text-[var(--brand-ink)]'
                            ]"
                        >
                            {{ getRangeCount(option.days) }}
                        </span>
                    </button>
                </div>
            </div>
            
            <!-- 當前選擇和統計 -->
            <div class="flex items-center justify-between bg-[var(--surface)] rounded-lg p-3 border border-[var(--border-subtle)]">
                <div class="flex items-center gap-2">
                    <span class="text-sm text-[var(--text-muted)]">目前查看：</span>
                    <span class="text-sm font-semibold text-[var(--brand-ink)]">{{ getRangeLabel(days) }}</span>
                </div>
                <div v-if="pagination.total !== null" class="text-sm">
                    <span class="text-[var(--text-muted)]">共</span>
                    <span class="font-bold text-[var(--brand-ink)] mx-1">{{ pagination.total }}</span>
                    <span class="text-[var(--text-muted)]">筆物品</span>
                </div>
            </div>

            <!-- 智能提示 -->
            <div v-if="debugInfo && debugInfo.total_all_with_expiration_date > 0 && pagination.total === 0" 
                 class="mt-3 p-3 bg-[var(--warning-soft)] border border-[var(--warning)] rounded-lg">
                <div class="flex items-start gap-2">
                    <span class="text-[var(--warning)] text-lg"><AppIcon name="hint" /></span>
                    <div class="flex-1">
                        <div class="text-sm font-semibold text-[var(--warning)] mb-1">提示</div>
                        <div class="text-xs text-[var(--warning)]">
                            目前範圍內沒有物品，但您有 <strong>{{ debugInfo.total_all_with_expiration_date }}</strong> 筆物品有設定過期日期。
                            建議選擇更大的範圍查看。
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="loading" class="text-center text-[var(--text-muted)] py-8">載入中...</div>

        <p v-else-if="error" role="alert" class="text-[var(--danger)] py-4">{{ error }}</p>

        <div v-else-if="items.length === 0" class="text-center text-[var(--text-muted)] py-8">
            <div class="text-6xl mb-4"></div>
            <p class="text-lg font-semibold mb-2">太好了！</p>
            <p class="text-sm">在「{{ getRangeLabel(days) }}」範圍內沒有即將過期的物品。</p>
        </div>

        <ul v-else class="space-y-4">
            <li
                v-for="item in items"
                :key="item.id"
                class="bg-[var(--surface)] rounded-xl border border-[var(--border-subtle)] p-4 flex flex-col gap-2 transition hover:border-[var(--border-control)]"
            >
                <!-- 名稱和資訊 -->
                <div class="flex items-start gap-3">
                    <!-- 主圖 -->
                    <ItemThumbnail :src="item.main_image?.thumb_url || ''" />

                    <!-- 物品資訊 -->
                    <div class="flex-1 min-w-0">
                        <router-link 
                            :to="`/items/${item.short_id}`" 
                            class="font-semibold text-base text-[var(--text)] hover:text-[var(--text-muted)] active:text-[var(--text-muted)] break-words leading-tight cursor-pointer transition-colors"
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
                                <span class="text-sm text-[var(--text-muted)]">
                                    （過期日期：{{ formatDate(item.expiration_date) }}）
                                </span>
                            </div>
                        </div>

                        <div class="text-sm text-[var(--text-muted)] mt-1">
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
                                        class="min-h-11 px-3 inline-flex items-center justify-center gap-2 text-sm text-[var(--text-muted)] hover:text-[var(--text)] hover:bg-[var(--canvas)] transition-colors"
                                        title="編輯" aria-label="編輯"
                                    >
                                        <AppIcon name="edit" />
                                        <span>編輯</span>
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
                @click="goToPage(pagination.current_page - 1)"
                :disabled="pagination.current_page === 1"
                class="px-4 py-2 bg-[var(--disabled)] rounded disabled:opacity-50"
            >
                上一頁
            </button>

            <span class="text-sm">第 {{ pagination.current_page }} 頁 / 共 {{ pagination.last_page }} 頁</span>

            <button
                @click="goToPage(pagination.current_page + 1)"
                :disabled="pagination.current_page === pagination.last_page"
                class="px-4 py-2 bg-[var(--disabled)] rounded disabled:opacity-50"
            >
                下一頁
            </button>
        </div>
    </div>
</template>

