<template>
    <div class="bg-[#f5f5f5] min-h-screen p-4 max-w-3xl mx-auto space-y-6">
        <div class="flex justify-between items-center">
            <h1 class="text-2xl font-bold">📂 分類詳情</h1>
            <div class="space-x-2">
                <router-link to="/categories" class="text-sm bg-gray-300 hover:bg-gray-400 px-3 py-1 rounded">⬅ 返回列表</router-link>
                <router-link :to="`/categories/${route.params.id}/edit`" class="text-sm bg-blue-500 text-white hover:bg-blue-600 px-3 py-1 rounded">✏️ 編輯</router-link>
            </div>
        </div>

        <div v-if="category" class="bg-white p-6 rounded shadow space-y-4">
            <div><strong>📛 名稱：</strong>{{ category.name }}</div>
        </div>

        <!-- 統計卡片 -->
        <div v-if="stats" class="bg-white p-3 rounded-lg shadow-md">
            <!-- 第一排：產品數、物品數 -->
            <div class="grid grid-cols-2 gap-2 mb-2">
                <div class="flex flex-col items-center p-2 bg-blue-50 rounded cursor-pointer hover:bg-blue-100 transition-colors"
                     @click="toggleTip('products')">
                    <div class="text-base mb-0.5">📦</div>
                    <div class="text-gray-600 text-xs mb-0.5">產品數</div>
                    <div class="text-lg font-bold text-blue-700">{{ stats.products_count || 0 }}</div>
                    <div v-if="activeTip === 'products'"
                         class="text-xs text-gray-500 mt-1 bg-white rounded px-2 py-1 border border-gray-200">
                        此分類下的產品總數
                    </div>
                </div>
                <div class="flex flex-col items-center p-2 bg-purple-50 rounded cursor-pointer hover:bg-purple-100 transition-colors"
                     @click="toggleTip('items')">
                    <div class="text-base mb-0.5">🏷️</div>
                    <div class="text-gray-600 text-xs mb-0.5">物品數</div>
                    <div class="text-lg font-bold text-purple-700">{{ stats.items_count || 0 }}</div>
                    <div v-if="activeTip === 'items'"
                         class="text-xs text-gray-500 mt-1 bg-white rounded px-2 py-1 border border-gray-200">
                        此分類下的物品總數
                    </div>
                </div>
            </div>

            <!-- 第二排：狀態統計（未到貨、未使用、使用中、已棄用） -->
            <div class="grid grid-cols-4 gap-2">
                <div class="flex flex-col items-center p-2 bg-gray-50 rounded cursor-pointer hover:bg-gray-100 transition-colors"
                     @click="toggleTip('pre_arrival')">
                    <div class="text-base mb-0.5">📭</div>
                    <div class="text-gray-600 text-xs mb-0.5">未到貨</div>
                    <div class="text-lg font-bold text-gray-800">{{ stats.items_pre_arrival || 0 }}</div>
                    <div v-if="activeTip === 'pre_arrival'"
                         class="text-xs text-gray-500 mt-1 bg-white rounded px-2 py-1 border border-gray-200">
                        尚未收到貨的物品
                    </div>
                </div>
                <div class="flex flex-col items-center p-2 bg-gray-50 rounded cursor-pointer hover:bg-gray-100 transition-colors"
                     @click="toggleTip('unused')">
                    <div class="text-base mb-0.5">📦</div>
                    <div class="text-gray-600 text-xs mb-0.5">未使用</div>
                    <div class="text-lg font-bold text-gray-800">{{ stats.items_unused || 0 }}</div>
                    <div v-if="activeTip === 'unused'"
                         class="text-xs text-gray-500 mt-1 bg-white rounded px-2 py-1 border border-gray-200">
                        已到貨但尚未使用的物品
                    </div>
                </div>
                <div class="flex flex-col items-center p-2 bg-green-50 rounded cursor-pointer hover:bg-green-100 transition-colors"
                     @click="toggleTip('in_use')">
                    <div class="text-base mb-0.5">🟢</div>
                    <div class="text-gray-600 text-xs mb-0.5">使用中</div>
                    <div class="text-lg font-bold text-green-700">{{ stats.items_in_use || 0 }}</div>
                    <div v-if="activeTip === 'in_use'"
                         class="text-xs text-gray-500 mt-1 bg-white rounded px-2 py-1 border border-gray-200">
                        目前正在使用中的物品
                    </div>
                </div>
                <div class="flex flex-col items-center p-2 bg-red-50 rounded cursor-pointer hover:bg-red-100 transition-colors"
                     @click="toggleTip('discarded')">
                    <div class="text-base mb-0.5">🗑️</div>
                    <div class="text-gray-600 text-xs mb-0.5">已棄用</div>
                    <div class="text-lg font-bold text-red-700">{{ stats.items_discarded || 0 }}</div>
                    <div v-if="activeTip === 'discarded'"
                         class="text-xs text-gray-500 mt-1 bg-white rounded px-2 py-1 border border-gray-200">
                        已棄用的物品
                    </div>
                </div>
            </div>
        </div>

        <template v-if="products?.length">
            <div class="bg-white p-6 rounded shadow space-y-4">
                <h2 class="text-lg font-semibold">📦 產品列表</h2>
                <div v-for="product in products" :key="product.id" class="border-b pb-4 mb-4 last:border-b-0">
                    <div class="mb-2">
                        <strong>
                            <router-link
                                class="text-blue-600 hover:underline"
                                :to="`/products/${product.short_id}`"
                            >
                                {{ product.name }}
                            </router-link>
                        </strong>
                    </div>
                    <div class="text-sm text-gray-500 mb-2">
                        🏷️ 品牌：{{ product.brand || '—' }}<br />
                        📊 物品數：{{ product.items_count }}
                    </div>
                    <!-- 狀態統計 -->
                    <div v-if="product.status_counts" class="grid grid-cols-4 gap-2 text-xs mt-3 pt-3 border-t">
                        <div class="text-center">
                            <div class="text-gray-500">📭 未到貨</div>
                            <div class="font-semibold text-gray-800">{{ product.status_counts.pre_arrival || 0 }}</div>
                        </div>
                        <div class="text-center">
                            <div class="text-gray-500">📦 未使用</div>
                            <div class="font-semibold text-gray-800">{{ product.status_counts.unused || 0 }}</div>
                        </div>
                        <div class="text-center">
                            <div class="text-gray-500">🟢 使用中</div>
                            <div class="font-semibold text-gray-800">{{ product.status_counts.in_use || 0 }}</div>
                        </div>
                        <div class="text-center">
                            <div class="text-gray-500">🗑️ 已棄用</div>
                            <div class="font-semibold text-gray-800">{{ product.status_counts.discarded || 0 }}</div>
                        </div>
                    </div>
                </div>

                <!-- 分頁按鈕 -->
                <div v-if="pagination.last_page > 1" class="flex justify-center items-center gap-4 mt-4 pt-4 border-t">
                    <button @click="fetchCategory(pagination.current_page - 1)" :disabled="pagination.current_page === 1"
                        class="px-4 py-2 bg-gray-200 rounded disabled:opacity-50">
                        ← 上一頁
                    </button>

                    <span class="text-sm">第 {{ pagination.current_page }} 頁 / 共 {{ pagination.last_page }} 頁</span>

                    <button @click="fetchCategory(pagination.current_page + 1)"
                        :disabled="pagination.current_page === pagination.last_page"
                        class="px-4 py-2 bg-gray-200 rounded disabled:opacity-50">
                        下一頁 →
                    </button>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import {ref, onMounted, watchEffect} from 'vue'
import axios from '../../axios'
import {useRoute, useRouter} from 'vue-router'
import Swal from 'sweetalert2'

const route = useRoute()
const router = useRouter()

const category = ref(null)
const stats = ref(null)
const products = ref([])
const pagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0
})

const activeTip = ref(null)
const toggleTip = (key) => {
    activeTip.value = activeTip.value === key ? null : key
}

watchEffect(() => {
    if (route.query.page && typeof route.query.page === 'string') {
        const page = parseInt(route.query.page, 10)
        if (page !== pagination.value.current_page) {
            fetchCategory(page)
        }
    }
})

const fetchCategory = async (page = 1) => {
    // 更新網址
    router.replace({
        query: {
            ...route.query,
            page: page !== 1 ? page : undefined
        }
    })

    try {
        const res = await axios.get(`/api/categories/${route.params.id}`, {
            params: {
                page,
                per_page: 10
            }
        })
        category.value = res.data.items[0]
        stats.value = res.data.stats
        products.value = res.data.products
        pagination.value = res.data.meta
    } catch (e) {
        if (e.response?.status !== 401) {
            await Swal.fire({
                icon: 'error',
                title: '錯誤',
                text: '載入分類失敗',
                confirmButtonText: '確定'
            })
        }
    }
}

onMounted(async () => {
    const page = route.query.page ? parseInt(route.query.page, 10) : 1
    await fetchCategory(page)
})
</script>
