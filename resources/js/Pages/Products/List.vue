<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const products = ref([])
const pagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0
})
const loading = ref(true)

const fetchProducts = async (page = 1) => {
    loading.value = true
    try {
        const res = await axios.get('/api/products', {
            params: { page }
        })
        products.value = res.data.items
        pagination.value = res.data.meta
    } catch (e) {
        console.error('無法取得產品資料', e)
    } finally {
        loading.value = false
    }
}

onMounted(() => {
    fetchProducts()
})
</script>

<template>
    <div class="bg-[#f5f5f5] min-h-screen p-4 max-w-2xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">🏷️ 產品清單</h1>
            <router-link
                to="/products/create"
                class="bg-blue-500 text-white px-4 py-2 rounded shadow hover:bg-blue-600"
            >
                新增產品
            </router-link>
        </div>

        <div v-if="loading" class="text-center text-gray-500">載入中...</div>

        <ul v-else class="space-y-4">
            <li
                v-for="product in products"
                :key="product.id"
                class="bg-white rounded-2xl shadow-md p-6 flex flex-col gap-2 transition hover:shadow-lg"
            >

                <div v-if="product.latest_item?.images?.length" class="relative w-full h-40 rounded-lg overflow-hidden">
                    <!-- 背景：模糊處理的 cover -->
                    <img
                        :src="product.latest_item.first_preview_url"
                        class="absolute inset-0 w-full h-full object-cover blur-sm scale-110"
                        alt="模糊背景"
                    />

                    <!-- 正中置中的圖片 -->
                    <img
                        :src="product.latest_item.first_preview_url"
                        class="relative z-10 h-full object-contain mx-auto"
                        alt="主圖片"
                    />
                </div>
                <div>
                    <div class="font-semibold text-xl text-gray-800 break-words max-w-full">
                        {{ product.name }}
                    </div>
                    <div class="text-sm text-gray-500 mt-1">
                        🏷 品牌：{{ product.brand || '未填寫' }}<br />
                        📂 分類：{{ product.category?.name || '未分類' }}
                    </div>
                    <div class="grid grid-cols-3 gap-4 text-sm text-gray-700 mt-2 text-center">
                        <div class="flex flex-col items-center text-yellow-700">
                            <div>📦 擁有中</div>
                            <div class="text-lg font-semibold">{{ product.owned_items_count }}</div>
                        </div>
                        <div class="flex flex-col items-center text-gray-500">
                            <div>🗑️ 已棄用</div>
                            <div class="text-lg font-semibold">{{ product.discarded_items_count }}</div>
                        </div>
                        <div class="flex flex-col items-center text-blue-700">
                            <div>📊 總數</div>
                            <div class="text-lg font-semibold">{{ product.items_count }}</div>
                        </div>
                    </div>
                </div>

                <div v-if="product.latest_owned_item" class="mt-3">
                    <router-link
                        :to="`/items/${product.latest_owned_item.short_id}`"
                        class="inline-block text-sm text-green-700 hover:text-green-900 underline"
                    >
                        🔗 前往最新物品
                    </router-link>
                </div>

                <div class="flex justify-end gap-4 text-sm mt-4">
                    <router-link :to="`/products/${product.short_id}`" class="text-gray-600 hover:text-gray-800">🔍 查看</router-link>
                    <router-link :to="`/products/${product.short_id}/edit`" class="text-blue-600 hover:text-blue-800">✏️ 編輯</router-link>
                </div>
            </li>
        </ul>
    </div>

    <!-- 分頁按鈕 -->
    <div class="flex justify-center items-center gap-4 mt-6">
        <button
            @click="fetchProducts(pagination.current_page - 1)"
            :disabled="pagination.current_page === 1"
            class="px-4 py-2 bg-gray-200 rounded disabled:opacity-50"
        >
            ← 上一頁
        </button>

        <span class="text-sm">第 {{ pagination.current_page }} 頁 / 共 {{ pagination.last_page }} 頁</span>

        <button
            @click="fetchProducts(pagination.current_page + 1)"
            :disabled="pagination.current_page === pagination.last_page"
            class="px-4 py-2 bg-gray-200 rounded disabled:opacity-50"
        >
            下一頁 →
        </button>
    </div>
</template>
