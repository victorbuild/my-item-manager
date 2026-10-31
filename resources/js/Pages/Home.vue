<template>
    <div class="aligned-page min-h-screen p-4">
        <h1 class="text-2xl font-bold mb-6">首頁</h1>

        <!-- 首頁近期到期摘要 -->
        <div v-if="isLoggedIn && (expiringItems.length > 0 || loadingExpiring)" class="mb-6">
            <div class="bg-[var(--surface)] rounded-xl p-4 border border-[var(--border-subtle)]">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <h2 class="text-lg font-semibold text-[var(--text)]">近期到期</h2>
                        <p class="text-sm text-[var(--text-muted)] mt-1">未來 90 天</p>
                    </div>
                    <router-link 
                        to="/expiring-items?days=90" 
                        class="text-sm text-[var(--brand-ink)] hover:text-[var(--brand-hover)] underline"
                    >
                        查看全部
                    </router-link>
                </div>
                
                <div v-if="loadingExpiring" class="text-center text-[var(--text-muted)] py-4">
                    載入中...
                </div>
                
                <div v-else-if="expiringItems.length === 0" class="text-center text-[var(--text-muted)] py-4">
                    <p class="text-sm">未來 90 天沒有即將到期的物品。</p>
                </div>
                
                <ul v-else class="space-y-2">
                    <li 
                        v-for="item in expiringItems" 
                        :key="item.id"
                        class="bg-[var(--surface)] rounded-lg p-3 border border-[var(--border-subtle)] hover:border-[var(--border-control)] transition"
                    >
                        <router-link 
                            :to="`/items/${item.short_id}`"
                            class="flex items-center gap-3"
                        >
                            <!-- 圖片 -->
                            <div v-if="item.main_image" class="w-12 h-12 rounded-lg overflow-hidden bg-[var(--disabled)] flex-shrink-0">
                                <img 
                                    :src="item.main_image.thumb_url" 
                                    :alt="item.name"
                                    class="w-full h-full object-cover"
                                    @error="$event.target.style.display='none'"
                                />
                            </div>
                            <div v-else class="w-12 h-12 rounded-lg bg-[var(--disabled)] flex items-center justify-center flex-shrink-0">
                                <span class="text-[var(--text-muted)]"><AppIcon name="package" /></span>
                            </div>
                            
                            <!-- 物品資訊 -->
                            <div class="flex-1 min-w-0">
                                <div class="font-semibold text-sm text-[var(--text)] truncate">
                                    {{ item.name }}
                                </div>
                                <div v-if="item.expiration_date" class="mt-1 flex flex-wrap items-baseline gap-x-2 gap-y-1">
                                    <span 
                                        :class="['text-sm font-medium', getExpirationColor(getDaysUntilExpiration(item.expiration_date))]"
                                    >
                                        {{ getExpirationLabel(getDaysUntilExpiration(item.expiration_date)) }}
                                    </span>
                                    <span class="text-sm text-[var(--text-muted)]">
                                        {{ item.expiration_date }} 到期
                                    </span>
                                </div>
                            </div>
                        </router-link>
                    </li>
                </ul>
            </div>
        </div>

        <!-- 物品管理區塊 -->
        <div class="mb-6">
            <h2 class="text-lg font-bold text-[var(--text)] mb-3">物品管理</h2>
            <ul class="space-y-3">
                <li>
                    <router-link
                        to="/items"
                        class="block px-4 py-3 bg-[var(--surface)] rounded-lg border border-[var(--border-subtle)] hover:bg-[var(--brand-soft)] transition"
                    >
                        我的物品<br/>
                        <span class="text-sm text-[var(--text-muted)]">我實際擁有的東西，例如這本書或這雙鞋</span>
                    </router-link>
                </li>
                <li>
                    <router-link
                        to="/expiring-items"
                        class="block px-4 py-3 bg-[var(--surface)] rounded-lg border border-[var(--border-subtle)] hover:bg-[var(--warning-soft)] transition"
                    >
                        近期過期物品<br/>
                        <span class="text-sm text-[var(--text-muted)]">查看即將過期的使用中物品</span>
                    </router-link>
                </li>
                <li>
                    <router-link
                        to="/discarded"
                        class="block px-4 py-3 bg-[var(--surface)] rounded-lg border border-[var(--border-subtle)] hover:bg-[var(--danger-soft)] transition"
                    >
                        已棄用物品<br/>
                        <span class="text-sm text-[var(--text-muted)]">已經棄用的物品清單</span>
                    </router-link>
                </li>
            </ul>
        </div>

        <!-- 產品管理區塊 -->
        <div class="mb-6">
            <h2 class="text-lg font-bold text-[var(--text)] mb-3">產品管理</h2>
            <ul class="space-y-3">
                <li>
                    <router-link
                        to="/products"
                        class="block px-4 py-3 bg-[var(--surface)] rounded-lg border border-[var(--border-subtle)] hover:bg-[var(--brand-soft)] transition"
                    >
                        產品定義<br/>
                        <span class="text-sm text-[var(--text-muted)]">定義產品資訊（例如 書、電腦、鞋子），作為物品的模板</span>
                    </router-link>
                </li>
                <li>
                    <router-link
                        to="/categories"
                        class="block px-4 py-3 bg-[var(--surface)] rounded-lg border border-[var(--border-subtle)] hover:bg-[var(--success-soft)] transition"
                    >
                        分類管理<br/>
                        <span class="text-sm text-[var(--text-muted)]">新增、編輯、刪除您的分類</span>
                    </router-link>
                </li>
            </ul>
        </div>

        <!-- 媒體管理區塊 -->
        <div class="mb-6">
            <h2 class="text-lg font-bold text-[var(--text)] mb-3">媒體管理</h2>
            <ul class="space-y-3">
                <li>
                    <router-link
                        to="/media"
                        class="block px-4 py-3 bg-[var(--surface)] rounded-lg border border-[var(--border-subtle)] hover:bg-[var(--brand-soft)] transition"
                    >
                        媒體櫃<br/>
                        <span class="text-sm text-[var(--text-muted)]">管理您的圖片媒體，查看和比對圖片與物品的關聯</span>
                    </router-link>
                </li>
            </ul>
        </div>

        <!-- 統計分析區塊 -->
        <div class="mb-6">
            <h2 class="text-lg font-bold text-[var(--text)] mb-3">統計分析</h2>
            <ul class="space-y-3">
                <li>
                    <router-link
                        to="/statistics"
                        class="block px-4 py-3 bg-[var(--surface)] rounded-lg border border-[var(--border-subtle)] hover:bg-[var(--brand-soft)] transition"
                    >
                        統計分析<br/>
                        <span class="text-sm text-[var(--text-muted)]">查看物品管理統計資料與分析</span>
                    </router-link>
                </li>
            </ul>
        </div>
    </div>
</template>

<script setup>
import '../../css/aligned-pages.css'
import AppIcon from '../Components/AppIcon.vue'
import { onMounted, onBeforeUnmount, ref, watch } from 'vue';
import { useCurrentUser } from '../composables/useCurrentUser';
import axios from 'axios';

const { isLoggedIn, user, load } = useCurrentUser();
let expiringController;
const expiringItems = ref([]);
const loadingExpiring = ref(false);

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

// 取得過期警告標籤
const getExpirationLabel = (daysUntil) => {
    if (daysUntil === null) return ''
    if (daysUntil < 0) return `已過期 ${Math.abs(daysUntil)} 天`
    if (daysUntil === 0) return '今天到期'
    return `剩 ${daysUntil} 天`
}

// 到期提醒分級：90 天內琥珀色，7 天內及已過期紅色。
const getExpirationColor = (daysUntil) => {
    if (daysUntil === null) return 'text-[var(--text-muted)]'
    if (daysUntil <= 7) return 'text-[var(--danger)] font-semibold'
    if (daysUntil <= 90) return 'text-[var(--warning)] font-semibold'
    return 'text-[var(--text)]'
}

const fetchExpiringItems = async () => {
    if (!isLoggedIn.value) return
    
    expiringController?.abort()
    const controller = new AbortController()
    expiringController = controller
    const userId = user.value?.id
    loadingExpiring.value = true
    try {
        const res = await axios.get('/api/items/expiring-soon', {
            signal: controller.signal,
            params: {
                days: 90, // 未來 90 天
                per_page: 5, // 只顯示前 5 個
            },
        })
        if (expiringController === controller && user.value?.id === userId) expiringItems.value = res.data.data || []
    } catch (error) {
        if (controller.signal.aborted || expiringController !== controller || user.value?.id !== userId) return
        console.error('載入過期物品失敗:', error)
        expiringItems.value = []
    } finally {
        if (expiringController === controller) loadingExpiring.value = false
    }
}

onMounted(() => load());
watch(user, value => {
    expiringController?.abort();
    expiringItems.value = [];
    loadingExpiring.value = false;
    if (value) fetchExpiringItems();
}, { immediate: true });
onBeforeUnmount(() => { expiringController?.abort(); expiringController = undefined; });
</script>
