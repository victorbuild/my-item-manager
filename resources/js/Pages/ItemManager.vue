<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const items = ref([])
const pagination = ref({
    current_page: 1,
    last_page: 1,
})

const formatPrice = (val) => {
    if (val == null) return '-'
    return Number(val).toLocaleString('zh-TW')
}

const fetchItems = async (page = 1) => {
    const res = await axios.get(`/api/items?page=${page}`)
    items.value = res.data.items
    pagination.value = res.data.meta
}

const confirmDelete = async (id) => {
    if (confirm('確定要刪除這筆資料嗎？')) {
        await axios.delete(`/api/items/${id}`)
        fetchItems(pagination.value.current_page)
    }
}

onMounted(() => fetchItems())
</script>

<template>
    <div class="bg-[#f5f5f5] min-h-screen p-4 max-w-2xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">📦 物品列表</h1>
            <router-link to="/create" class="bg-blue-500 text-white px-4 py-2 rounded shadow hover:bg-blue-600">
                新增
            </router-link>
        </div>

        <ul class="space-y-4">
            <li
                v-for="item in items"
                :key="item.id"
                class="bg-white rounded-2xl shadow-md p-6 flex flex-col gap-2 transition hover:shadow-lg"
            >
                <!-- 名稱和資訊 -->
                <div>
                    <div class="font-semibold text-xl text-gray-800 break-words max-w-full">
                        {{ item.name }}
                    </div>
                    <div class="text-sm text-gray-500 mt-1">
                        💰 金額：{{ formatPrice(item.price) }}<br />
                        📦 數量：{{ item.quantity }}<br />
                        📅 購買日：{{ item.purchased_at }}
                    </div>
                </div>

                <!-- 圖片 -->
                <div
                    v-if="item.images?.length"
                    class="flex gap-2 overflow-x-auto mt-2 pb-1"
                >
                    <img
                        v-for="(img, idx) in item.images.slice(0, 4)"
                        :key="img.id || idx"
                        :src="img.url"
                        class="w-20 h-20 object-cover rounded border shrink-0"
                    />
                </div>

                <!-- 操作按鈕區域（放到底部右邊） -->
                <div class="flex justify-end gap-4 text-sm mt-4">
                    <router-link :to="`/items/${item.id}`" class="text-gray-600 hover:text-gray-800">🔍 查看</router-link>
                    <router-link :to="`/edit/${item.id}`" class="text-blue-600 hover:text-blue-800">✏️ 編輯</router-link>
                    <button @click="confirmDelete(item.id)" class="text-red-500 hover:text-red-700">🗑️ 刪除</button>
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
</template>
