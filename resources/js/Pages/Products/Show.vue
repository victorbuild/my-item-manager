<template>
    <div class="bg-[#f5f5f5] min-h-screen p-4 max-w-3xl mx-auto space-y-6">
        <div class="flex justify-between items-center">
            <h1 class="text-2xl font-bold">📦 產品詳情</h1>
            <div class="space-x-2">
                <router-link to="/products" class="text-sm bg-gray-300 hover:bg-gray-400 px-3 py-1 rounded">⬅ 返回列表</router-link>
                <router-link :to="`/products/${route.params.id}/edit`" class="text-sm bg-blue-500 text-white hover:bg-blue-600 px-3 py-1 rounded">✏️ 編輯</router-link>
            </div>
        </div>

        <div v-if="product" class="bg-white p-6 rounded shadow space-y-4">
            <div><strong>📛 名稱：</strong>{{ product.name }}</div>
            <div><strong>🏷️ 品牌：</strong>{{ product.brand || '—' }}</div>
            <div>
                <strong>📂 分類：</strong>
                <router-link 
                    v-if="product.category?.id" 
                    :to="`/categories/${product.category.id}`"
                    class="text-blue-600 hover:text-blue-800 hover:underline"
                >
                    {{ product.category.name }}
                </router-link>
                <span v-else>未分類</span>
            </div>
            <div><strong>🧾 型號：</strong>{{ product.model || '—' }}</div>
            <div><strong>⚙️ 規格：</strong>{{ product.spec || '—' }}</div>
            <div><strong>🔢 條碼：</strong>{{ product.barcode || '—' }}</div>
        </div>

        <!-- 統計卡片 -->
        <div v-if="product?.stats"
             class="bg-white p-4 rounded shadow grid [grid-template-columns:repeat(auto-fit,minmax(0,1fr))] gap-4 text-sm font-medium text-center">
            <div class="flex flex-col items-center cursor-pointer space-y-1" @click="toggleTip('pre_arrival')">
                <div class="text-gray-500 whitespace-nowrap">📭 未到貨</div>
                <div class="text-xl min-h-[32px] whitespace-nowrap">{{ stats.pre_arrival || 0 }}</div>
                <div v-if="activeTip === 'pre_arrival'"
                     class="text-xs text-gray-500 mt-1 bg-gray-100 rounded px-2 py-1">{{ statusTips.pre_arrival }}
                </div>
            </div>
            <div class="flex flex-col items-center cursor-pointer space-y-1" @click="toggleTip('unused')">
                <div class="text-gray-500 whitespace-nowrap">📦 未使用</div>
                <div class="text-xl min-h-[32px] whitespace-nowrap">{{ stats.unused || 0 }}</div>
                <div v-if="activeTip === 'unused'" class="text-xs text-gray-500 mt-1 bg-gray-100 rounded px-2 py-1">
                    {{ statusTips.unused }}
                </div>
            </div>
            <div class="flex flex-col items-center cursor-pointer space-y-1" @click="toggleTip('in_use')">
                <div class="text-gray-500 whitespace-nowrap">🟢 使用中</div>
                <div class="text-xl min-h-[32px] whitespace-nowrap">{{ stats.in_use || 0 }}</div>
                <div v-if="activeTip === 'in_use'" class="text-xs text-gray-500 mt-1 bg-gray-100 rounded px-2 py-1">
                    {{ statusTips.in_use }}
                </div>
            </div>
            <div class="flex flex-col items-center cursor-pointer space-y-1" @click="toggleTip('discarded')">
                <div class="text-gray-500 whitespace-nowrap">🗑️ 報廢</div>
                <div class="text-xl flex flex-wrap justify-center min-h-[32px]">
                    <span>{{ stats.used_discarded || 0 }}</span>
                    <span class="text-red-500 cursor-pointer whitespace-nowrap"
                          @click.stop="toggleTip('discarded_unused')">({{
                            stats.unused_discarded || 0
                        }})</span>
                </div>
                <div v-if="activeTip === 'discarded'" class="text-xs text-gray-500 mt-1 bg-gray-100 rounded px-2 py-1">
                    {{ statusTips.discarded }}
                </div>
                <div v-if="activeTip === 'discarded_unused'"
                     class="text-xs text-red-500 mt-1 bg-gray-100 rounded px-2 py-1">購買後未使用直接報廢
                </div>
            </div>
        </div>

        <!-- Tabs（預設只載入一個狀態，其餘點了才載入） -->
        <div v-if="product" class="bg-white p-4 rounded shadow space-y-4">
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="tab in visibleTabs"
                    :key="tab.key"
                    @click="selectTab(tab.key)"
                    class="px-3 py-1.5 rounded text-sm border"
                    :class="selectedTab === tab.key ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
                >
                    {{ tab.label }}（{{ stats[tab.key] || 0 }}）
                </button>

                <button
                    v-if="discardedTotal > 0"
                    @click="selectTab('discarded')"
                    class="px-3 py-1.5 rounded text-sm border"
                    :class="selectedTab === 'discarded' ? 'bg-gray-800 text-white border-gray-800' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
                >
                    🗑️ 棄用（{{ discardedTotal }}）
                </button>
            </div>

            <div v-if="selectedTab === null" class="text-sm text-gray-600">
                目前已經沒有該產品（僅剩棄用項目）。
            </div>

            <div v-else>
                <div v-if="loading" class="text-sm text-gray-600">載入中...</div>

                <div v-else-if="currentItems.length === 0" class="text-sm text-gray-600">
                    此分類沒有物品
                </div>

                <div v-else class="space-y-3">
                    <div v-for="item in currentItems" :key="item.short_id" class="border-b pb-3">
                        <div>
                            <strong>
                                <router-link
                                    class="text-blue-600 hover:underline"
                                    :to="`/items/${item.short_id}`"
                                >
                                    #{{ item.serial_number }}
                                </router-link>
                            </strong>
                        </div>
                        <div>💰 價格：{{ item.price ? `$${item.price}` : '—' }}</div>
                        <div>
                            ⏳ 有效期限：
                            {{ item.expiration_date || '—' }}
                            <span v-if="item.expiration_date">（剩餘 {{ daysLeft(item.expiration_date) }} 天）</span>
                        </div>
                        <div>📝 備註：{{ item.notes || '—' }}</div>
                        <div class="text-sm text-gray-600 mt-1">
                            📅 購買：{{ item.purchased_at || '—' }} /
                            🚚 到貨：{{ item.received_at || '—' }} /
                            🚀 使用：{{ item.used_at || '—' }} /
                            🗑️ 棄用：{{ item.discarded_at || '—' }}
                        </div>
                        <div v-if="item.main_image?.thumb_url" class="mt-2">
                            <img :src="item.main_image.thumb_url" :alt="item.name || '物品圖片'" class="h-24 rounded border"/>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <div
                    v-if="currentMeta && currentMeta.last_page > 1"
                    class="flex items-center justify-between gap-2 pt-3 text-sm"
                >
                    <button
                        class="px-3 py-1.5 rounded border"
                        :class="(currentMeta.current_page ?? 1) <= 1 ? 'bg-gray-100 text-gray-400 border-gray-200 cursor-not-allowed' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
                        :disabled="(currentMeta.current_page ?? 1) <= 1 || loading"
                        @click="goToPage((currentMeta.current_page ?? 1) - 1)"
                    >
                        上一頁
                    </button>

                    <div class="text-gray-600">
                        第 {{ currentMeta.current_page }} / {{ currentMeta.last_page }} 頁（共 {{ currentMeta.total }} 筆）
                    </div>

                    <button
                        class="px-3 py-1.5 rounded border"
                        :class="(currentMeta.current_page ?? 1) >= (currentMeta.last_page ?? 1) ? 'bg-gray-100 text-gray-400 border-gray-200 cursor-not-allowed' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
                        :disabled="(currentMeta.current_page ?? 1) >= (currentMeta.last_page ?? 1) || loading"
                        @click="goToPage((currentMeta.current_page ?? 1) + 1)"
                    >
                        下一頁
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import {ref, onMounted, computed} from 'vue'
import axios from '../../axios'
import {useRoute} from 'vue-router'
import dayjs from 'dayjs'

const route = useRoute()
const product = ref(null)
const stats = computed(() => product.value?.stats ?? {})
const PER_PAGE = 10

const TABS = [
    { key: 'pre_arrival', label: '📭 未到貨' },
    { key: 'unused', label: '📦 未使用' },
    { key: 'in_use', label: '🟢 使用中' },
]

const visibleTabs = computed(() => {
    return TABS.filter(t => (stats.value?.[t.key] ?? 0) > 0)
})

const discardedTotal = computed(() => {
    return (stats.value?.unused_discarded ?? 0) + (stats.value?.used_discarded ?? 0)
})

const selectedTab = ref(null) // 'pre_arrival' | 'unused' | 'in_use' | 'discarded' | null
const loading = ref(false)
const itemsCache = ref({
    pre_arrival: { items: [], meta: null },
    unused: { items: [], meta: null },
    in_use: { items: [], meta: null },
    discarded: { items: [], meta: null },
})

const currentItems = computed(() => {
    if (!selectedTab.value) return []
    return itemsCache.value[selectedTab.value]?.items || []
})

const currentMeta = computed(() => {
    if (!selectedTab.value) return null
    return itemsCache.value[selectedTab.value]?.meta || null
})

const activeTip = ref(null)
const toggleTip = (key) => {
    activeTip.value = activeTip.value === key ? null : key
}
const statusTips = {
    pre_arrival: '尚未收到貨，未開始使用',
    unused: '貨已到但尚未開始使用',
    in_use: '目前正在使用中',
    discarded: '已使用後報廢的項目，括號內為未使用直接報廢的數量'
}

const daysLeft = (dateStr) => {
  const now = dayjs().startOf('day')
  const target = dayjs(dateStr).startOf('day')
  return target.diff(now, 'day')
}

const getDefaultTab = () => {
    const candidates = ['pre_arrival', 'unused', 'in_use']
    for (const key of candidates) {
        if ((stats.value?.[key] ?? 0) > 0) {
            return key
        }
    }
    return null
}

const buildStatusesParam = (tabKey) => {
    if (tabKey === 'discarded') {
        return 'unused_discarded,used_discarded'
    }
    return tabKey
}

const fetchItemsForTab = async (tabKey, page = 1) => {
    const state = itemsCache.value[tabKey]
    if (!state) return

    loading.value = true
    try {
        const res = await axios.get('/api/items', {
            params: {
                product_short_id: route.params.id,
                statuses: buildStatusesParam(tabKey),
                per_page: PER_PAGE,
                page,
            }
        })
        state.items = res.data.data || []
        state.meta = res.data.meta || null
    } finally {
        loading.value = false
    }
}

const selectTab = async (tabKey) => {
    selectedTab.value = tabKey
    if (itemsCache.value[tabKey]?.meta) return
    await fetchItemsForTab(tabKey, 1)
}

const goToPage = async (page) => {
    if (!selectedTab.value) return
    await fetchItemsForTab(selectedTab.value, page)
}

onMounted(async () => {
    try {
        const res = await axios.get(`/api/products/${route.params.id}`)
        product.value = res.data.data

        selectedTab.value = getDefaultTab()

        if (selectedTab.value) {
            await fetchItemsForTab(selectedTab.value, 1)
        }
    } catch (e) {
        if (e.response?.status !== 401) {
            alert('❌ 載入產品失敗')
        }
        // 可選：401 的處理，如跳轉登入頁等
    }
})
</script>
