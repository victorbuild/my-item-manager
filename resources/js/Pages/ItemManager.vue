<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import Multiselect from '@vueform/multiselect'
import '@vueform/multiselect/themes/default.css'

const route = useRoute()
const router = useRouter()

// 預設的狀態（棄用以外）
const DEFAULT_STATUSES = ['pending_delivery', 'pending_use', 'using']

const statuses = ref([])

const items = ref([])
const pagination = ref({
    current_page: 1,
    last_page: 1,
})

const search = ref(route.query.search || '')

const category = ref(route.query.category_id || '')
const categories = ref([])


const fetchCategories = async () => {
    const res = await axios.get('/api/categories')
    categories.value = res.data
}

const doSearch = () => {
    router.push({
        path: '/items',
        query: {
            ...(search.value ? { search: search.value } : {}),
            ...(category.value ? { category_id: category.value } : {}),
            ...(statuses.value.length ? { statuses: statuses.value.join(',') } : {}),
        },
    })

    fetchItems(1)
}

const formatPrice = (val) => {
    if (val == null) return '-'
    return Number(val).toLocaleString('zh-TW')
}

const fetchItems = async (page = 1) => {
    const res = await axios.get('/api/items', {
        params: {
            page,
            search: search.value || undefined,
            category_id: category.value || undefined,
            statuses: statuses.value.length ? statuses.value.join(',') : undefined,
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

onMounted(() => {

    // 初始化篩選狀態
    if (route.query.statuses) {
        statuses.value = route.query.statuses.split(',')
    } else {
        statuses.value = [...DEFAULT_STATUSES]
    }

    fetchCategories()
    fetchItems(Number(route.query.page) || 1)
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
    { value: 'pending_delivery', label: '📦 未到貨' },
    { value: 'pending_use', label: '🚀 未使用' },
    { value: 'using', label: '✅ 使用中' },
    { value: 'discarded', label: '🗑️ 已棄用' }
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
                @click="search = ''; category = ''; statuses = [...DEFAULT_STATUSES]; fetchItems(1)"
                class="text-sm text-gray-500 underline ml-2"
            >
                ❌ 清除
            </button>
        </form>

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
                        📅 購買日期：{{ item.purchased_at }}<br />
                        📦 到貨日期：{{ item.received_at }}<br />
                        🚀 開始使用日期：{{ item.used_at || '（未填寫）' }}<br />
                        🗑️ 棄用日：{{ item.discarded_at }}<br />
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
                        :src="img.thumb_url"
                        class="w-20 h-20 object-cover rounded border shrink-0"
                        :alt="item.name + '-' + (idx+1)"
                    />
                </div>

                <!-- 操作按鈕區域（放到底部右邊） -->
                <div class="flex justify-end gap-4 text-sm mt-4">
                    <router-link :to="`/items/${item.short_id}`" class="text-gray-600 hover:text-gray-800">🔍 查看</router-link>
                    <router-link :to="`/items/${item.short_id}/edit`" class="text-blue-600 hover:text-blue-800">✏️ 編輯</router-link>
                    <button @click="confirmDelete(item.short_id)" class="text-red-500 hover:text-red-700">🗑️ 刪除</button>
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
