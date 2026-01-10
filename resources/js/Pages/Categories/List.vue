<script setup>
import { ref, onMounted, watchEffect } from 'vue'
import axios from '../../axios'
import { useRoute, useRouter } from 'vue-router'
import Swal from 'sweetalert2'

const route = useRoute()
const router = useRouter()

const categories = ref([])
const search = ref('')
const pagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0
})
const loading = ref(true)

watchEffect(() => {
    if (route.query.q && typeof route.query.q === 'string') {
        search.value = route.query.q
    }
    if (route.query.page && typeof route.query.page === 'string') {
        const page = parseInt(route.query.page, 10)
        if (page !== pagination.value.current_page) {
            fetchCategories(page)
        }
    }
})

// Debounce 工具函數
const debounce = (func, delay) => {
    let timeoutId
    return (...args) => {
        clearTimeout(timeoutId)
        timeoutId = setTimeout(() => func.apply(null, args), delay)
    }
}

// 實際的搜尋函數
const _performSearch = () => {
    fetchCategories(1)
}

// 使用 debounce 包裝的搜尋函數（500ms 延遲）
const performSearch = debounce(_performSearch, 500)

const fetchCategories = async (page = 1) => {
    loading.value = true

    // 更新網址
    router.replace({
        query: {
            ...route.query,
            q: search.value || undefined,
            page: page !== 1 ? page : undefined
        }
    })

    try {
        const res = await axios.get('/api/categories', {
            params: {
                page,
                per_page: 10,
                q: search.value || undefined
            }
        })
        categories.value = res.data.items
        pagination.value = res.data.meta
    } catch (e) {
        console.error('無法取得分類資料', e)
    } finally {
        loading.value = false
    }
}

const confirmDelete = async (categoryId) => {
    const result = await Swal.fire({
        title: '確定要刪除這個分類嗎？',
        text: '只有沒有關聯產品的分類才能刪除。',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: '確定刪除',
        cancelButtonText: '取消',
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6'
    })

    if (result.isConfirmed) {
        try {
            const res = await axios.delete(`/api/categories/${categoryId}`)
            if (res.status === 204) {
                await Swal.fire({
                    icon: 'success',
                    title: '成功',
                    text: '分類已刪除',
                    confirmButtonText: '確定'
                })
                fetchCategories(pagination.value.current_page)
            }
        } catch (e) {
            await Swal.fire({
                icon: 'error',
                title: '錯誤',
                text: e.response?.data?.message || '刪除失敗',
                confirmButtonText: '確定'
            })
        }
    }
}

onMounted(() => {
    const page = route.query.page ? parseInt(route.query.page, 10) : 1
    const searchQuery = route.query.q || ''
    search.value = searchQuery
    fetchCategories(page)
})
</script>

<template>
    <div class="bg-[#f5f5f5] min-h-screen p-4 max-w-2xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">📂 分類清單</h1>
            <router-link to="/categories/create"
                class="bg-blue-500 text-white px-4 py-2 rounded shadow hover:bg-blue-600">
                新增分類
            </router-link>
        </div>

        <!-- 搜尋框 -->
        <div class="mb-4 flex gap-2">
            <input v-model="search" type="text" placeholder="搜尋分類名稱"
                class="flex-1 p-2 border border-gray-300 rounded" 
                @input="performSearch" />
            <button v-if="search" @click="search = ''; fetchCategories(1)" 
                class="text-sm text-gray-500 underline px-3 py-2 border border-gray-300 rounded hover:bg-gray-50">
                ❌ 清除
            </button>
        </div>

        <div v-if="loading" class="text-center text-gray-500">載入中...</div>

        <ul v-else class="space-y-4">
            <li v-for="category in categories" :key="category.id"
                class="bg-white rounded-2xl shadow-md p-6 flex flex-col gap-2 transition hover:shadow-lg">
                <div>
                    <div class="font-semibold text-xl text-gray-800 break-words max-w-full">
                        {{ category.name }} ({{ category.items_count || 0 }})
                    </div>
                    <div class="text-sm text-gray-500 mt-1">
                        📦 產品數：{{ category.products_count || 0 }}<br />
                        🏷️ 物品數：{{ category.items_count || 0 }}
                    </div>
                </div>

                <div class="flex justify-end gap-4 text-sm mt-4">
                    <router-link :to="`/categories/${category.id}`" class="text-gray-600 hover:text-gray-800">🔍
                        查看</router-link>
                    <router-link :to="`/categories/${category.id}/edit`" class="text-blue-600 hover:text-blue-800">✏️
                        編輯</router-link>
                    <button @click="confirmDelete(category.id)" class="text-red-500 hover:text-red-700">🗑️
                        刪除</button>
                </div>
            </li>
        </ul>

        <!-- 分頁按鈕 -->
        <div v-if="!loading && pagination.last_page > 1" class="flex justify-center items-center gap-4 mt-6">
            <button @click="fetchCategories(pagination.current_page - 1)" :disabled="pagination.current_page === 1"
                class="px-4 py-2 bg-gray-200 rounded disabled:opacity-50">
                ← 上一頁
            </button>

            <span class="text-sm">第 {{ pagination.current_page }} 頁 / 共 {{ pagination.last_page }} 頁</span>

            <button @click="fetchCategories(pagination.current_page + 1)"
                :disabled="pagination.current_page === pagination.last_page"
                class="px-4 py-2 bg-gray-200 rounded disabled:opacity-50">
                下一頁 →
            </button>
        </div>
    </div>
</template>
