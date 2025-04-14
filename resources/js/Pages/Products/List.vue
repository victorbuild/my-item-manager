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
                <div>
                    <div class="font-semibold text-xl text-gray-800 break-words max-w-full">
                        {{ product.name }}
                    </div>
                    <div class="text-sm text-gray-500 mt-1">
                        🏷 品牌：{{ product.brand || '未填寫' }}<br />
                        📂 分類：{{ product.category?.name || '未分類' }}<br />
                        📦 實際物品數：{{ product.items_count }}
                    </div>
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
