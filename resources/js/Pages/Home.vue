<template>
    <div class="min-h-screen p-4">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">物品管理首頁</h1>
            <div>
                <template v-if="isLoggedIn">
                    <div class="flex items-center space-x-2">
                        <div class="w-8 h-8 rounded-full bg-gray-300 flex items-center justify-center text-white">
                            👤
                        </div>
                        <span class="text-sm text-gray-700">{{ user?.name }}</span>
                        <button @click="logout" class="text-sm text-red-600 hover:underline">登出</button>
                    </div>
                </template>
                <template v-else>
                    <router-link
                        to="/login"
                        class="text-sm text-blue-600 hover:underline mr-4"
                    >登入
                    </router-link>
                    <router-link
                        to="/register"
                        class="text-sm text-blue-600 hover:underline"
                    >註冊
                    </router-link>
                </template>
            </div>
        </div>

        <!-- 三個月內過期商品區塊 -->
        <div v-if="isLoggedIn && (expiringItems.length > 0 || loadingExpiring)" class="mb-6">
            <div class="bg-gradient-to-r from-orange-50 to-red-50 rounded-lg p-4 border border-orange-200 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-lg font-bold text-orange-800 flex items-center gap-2">
                        ⏰ 三個月內過期商品
                    </h2>
                    <router-link 
                        to="/expiring-items?days=90" 
                        class="text-sm text-orange-600 hover:text-orange-800 underline"
                    >
                        查看全部 →
                    </router-link>
                </div>
                
                <div v-if="loadingExpiring" class="text-center text-gray-500 py-4">
                    載入中...
                </div>
                
                <div v-else-if="expiringItems.length === 0" class="text-center text-gray-500 py-4">
                    <p class="text-sm">🎉 太好了！三個月內沒有即將過期的商品。</p>
                </div>
                
                <ul v-else class="space-y-2">
                    <li 
                        v-for="item in expiringItems" 
                        :key="item.id"
                        class="bg-white rounded-lg p-3 shadow-sm hover:shadow-md transition"
                    >
                        <router-link 
                            :to="`/items/${item.short_id}`"
                            class="flex items-center gap-3"
                        >
                            <!-- 圖片 -->
                            <div v-if="item.main_image" class="w-12 h-12 rounded-lg overflow-hidden bg-gray-200 flex-shrink-0">
                                <img 
                                    :src="item.main_image.thumb_url" 
                                    :alt="item.name"
                                    class="w-full h-full object-cover"
                                    @error="$event.target.style.display='none'"
                                />
                            </div>
                            <div v-else class="w-12 h-12 rounded-lg bg-gray-200 flex items-center justify-center flex-shrink-0">
                                <span class="text-gray-400">📦</span>
                            </div>
                            
                            <!-- 商品資訊 -->
                            <div class="flex-1 min-w-0">
                                <div class="font-semibold text-sm text-gray-800 truncate">
                                    {{ item.name }}
                                </div>
                                <div v-if="item.expiration_date" class="mt-1">
                                    <span 
                                        :class="['text-xs font-medium', getExpirationColor(getDaysUntilExpiration(item.expiration_date))]"
                                    >
                                        {{ getExpirationLabel(getDaysUntilExpiration(item.expiration_date)) }}
                                    </span>
                                    <span class="text-xs text-gray-500 ml-2">
                                        ({{ formatDate(item.expiration_date) }})
                                    </span>
                                </div>
                            </div>
                        </router-link>
                    </li>
                </ul>
            </div>
        </div>

        <ul class="space-y-4">

            <li>
                <router-link
                    to="/items"
                    class="block px-4 py-3 bg-white rounded-lg shadow hover:bg-blue-50 transition"
                >
                    📦 我的物品<br/>
                    <span class="text-sm text-gray-500">我實際擁有的東西，例如這本書或這雙鞋</span>
                </router-link>
            </li>

            <li>
                <router-link
                    to="/products"
                    class="block px-4 py-3 bg-white rounded-lg shadow hover:bg-indigo-50 transition"
                >
                    🏷️ 產品定義<br/>
                    <span class="text-sm text-gray-500">定義產品資訊（例如 書、電腦、鞋子），作為物品的模板</span>
                </router-link>
            </li>

            <!--
            <li>
                <router-link
                    to="/usage-records"
                    class="block px-4 py-3 bg-white rounded-lg shadow hover:bg-yellow-50 transition"
                >
                    🕓 使用紀錄<br/>
                    <span class="text-sm text-gray-500">每次使用的時間與情境</span>
                </router-link>
            </li>
            -->

            <li>
                <router-link
                    to="/expiring-items"
                    class="block px-4 py-3 bg-white rounded-lg shadow hover:bg-orange-50 transition"
                >
                    ⏰ 近期過期商品<br/>
                    <span class="text-sm text-gray-500">查看即將過期的使用中商品</span>
                </router-link>
            </li>

            <li>
                <router-link
                    to="/discarded"
                    class="block px-4 py-3 bg-white rounded-lg shadow hover:bg-red-50 transition"
                >
                    🗑️ 已棄用物品<br/>
                    <span class="text-sm text-gray-500">已經棄用的物品清單</span>
                </router-link>
            </li>

            <li>
                <router-link
                    to="/statistics"
                    class="block px-4 py-3 bg-white rounded-lg shadow hover:bg-purple-50 transition"
                >
                    📊 統計分析<br/>
                    <span class="text-sm text-gray-500">查看物品管理統計資料與分析</span>
                </router-link>
            </li>

        </ul>
    </div>
</template>

<script setup>
import {onMounted, ref} from 'vue';
import axios from 'axios';

const isLoggedIn = ref(false);
const user = ref(null);
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
    if (daysUntil < 0) return '⚠️ 已過期'
    if (daysUntil === 0) return '⚠️ 今天過期'
    if (daysUntil === 1) return '⚠️ 明天過期'
    if (daysUntil <= 3) return `⚠️ ${daysUntil} 天後過期`
    if (daysUntil <= 7) return `⏰ ${daysUntil} 天後過期`
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

// 取得過期警告顏色
const getExpirationColor = (daysUntil) => {
    if (daysUntil === null) return 'text-gray-600'
    if (daysUntil < 0) return 'text-red-600 font-bold'
    if (daysUntil <= 3) return 'text-red-500 font-semibold'
    if (daysUntil <= 7) return 'text-orange-500 font-semibold'
    return 'text-yellow-600'
}

const formatDate = (date) => {
    if (!date) return '-'
    return new Date(date).toLocaleDateString('zh-TW', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit'
    })
}

const fetchExpiringItems = async () => {
    if (!isLoggedIn.value) return
    
    loadingExpiring.value = true
    try {
        const res = await axios.get('/api/items/expiring-soon', {
            params: {
                days: 90, // 三個月
                per_page: 5, // 只顯示前 5 個
            },
        })
        expiringItems.value = res.data.items || []
    } catch (error) {
        console.error('載入過期商品失敗:', error)
        expiringItems.value = []
    } finally {
        loadingExpiring.value = false
    }
}

onMounted(async () => {
    try {
        const res = await axios.get('/api/user');
        isLoggedIn.value = true;
        user.value = res.data;
        // 登入後載入過期商品
        await fetchExpiringItems();
    } catch {
        isLoggedIn.value = false;
    }
});

const logout = async () => {
    try {
        await axios.post('/logout');
        localStorage.removeItem('loggedIn');
        location.reload();
    } catch (e) {
        console.error('登出失敗', e);
    }
};
</script>
