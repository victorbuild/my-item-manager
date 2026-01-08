<template>
    <div class="bg-[#f5f5f5] min-h-screen p-4 max-w-2xl mx-auto space-y-6">
        <div class="flex justify-between items-center">
            <h1 class="text-2xl font-bold">{{ isEdit ? '✏️ 編輯產品' : '➕ 建立產品' }}</h1>
            <router-link
                to="/products"
                class="text-sm bg-gray-300 hover:bg-gray-400 px-3 py-1 rounded"
            >
                ⬅ 返回
            </router-link>
        </div>

        <div class="bg-white p-6 rounded shadow space-y-4">
            <div>
                <label class="block font-medium">📦 產品名稱 *</label>
                <input v-model="form.name" type="text" class="w-full p-2 border rounded" required />
            </div>

            <div>
                <label class="block font-medium">🏷️ 品牌</label>
                <input v-model="form.brand" type="text" class="w-full p-2 border rounded" />
            </div>

            <div>
                <label class="block font-medium">📂 分類</label>
                <Multiselect
                    v-model="form.category"
                    :options="categories"
                    :searchable="true"
                    :custom-label="opt => opt.name"
                    :track-by="'id'"
                    placeholder="選擇分類"
                    :allow-empty="true"
                    :close-on-select="true"
                    @search-change="onSearch"
                    @select="onSelect"
                />
            </div>

            <div>
                <label class="block font-medium">🧾 型號</label>
                <input v-model="form.model" type="text" class="w-full p-2 border rounded" />
            </div>

            <div>
                <label class="block font-medium">⚙️ 規格</label>
                <input v-model="form.spec" type="text" class="w-full p-2 border rounded" />
            </div>

            <div>
                <label class="block font-medium">🔢 條碼</label>
                <input v-model="form.barcode" type="text" class="w-full p-2 border rounded" />
            </div>

            <div class="flex gap-4 pt-2">
                <button @click="submitForm" class="bg-blue-600 text-white px-4 py-2 rounded shadow">
                    {{ isEdit ? '✅ 更新' : '✅ 建立' }}
                </button>
                <router-link to="/products" class="text-gray-500 underline">取消</router-link>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import Multiselect from 'vue-multiselect'
import 'vue-multiselect/dist/vue-multiselect.css'
import { useRouter, useRoute } from 'vue-router'

const router = useRouter()
const route = useRoute()

const isEdit = ref(false)
const form = ref({
    name: '',
    brand: '',
    category: null,
    model: '',
    spec: '',
    barcode: ''
})

const categories = ref([])
const creatingCategory = ref(false)

// Debounce 工具函數
const debounce = (func, delay) => {
    let timeoutId
    return (...args) => {
        clearTimeout(timeoutId)
        timeoutId = setTimeout(() => func.apply(null, args), delay)
    }
}

// 實際的分類搜尋函數
const _searchCategory = async (query) => {
    try {
        const res = await axios.get('/api/categories', { params: { q: query } })
        // 處理分頁返回的數據結構
        categories.value = res.data.items || res.data || []

        // 如果沒有完全相符的分類，加入「新增分類」選項
        if (query && !categories.value.find(c => c.name === query)) {
            categories.value.unshift({
                id: '__create__',
                name: `➕ 點選以建立新分類：「${query}」`,
                _rawName: query,
                isNew: true
            })
        }
    } catch (err) {
        console.error('❌ 搜尋分類失敗', err)
    }
}

// 使用 debounce 包裝的分類搜尋函數（500ms 延遲）
const onSearch = debounce(_searchCategory, 500)

const onSelect = async (option) => {
    if (option && option.isNew) {
        // 顯示確認對話框
        const categoryName = option._rawName || option.name.replace('➕ 點選以建立新分類：「', '').replace('」', '')
        const confirmed = confirm(`是否要新增分類「${categoryName}」？`)
        
        if (confirmed) {
            await createCategory(categoryName)
        } else {
            // 取消選擇，回到未選擇狀態
            form.value.category = null
        }
    } else if (option) {
        form.value.category = option
    }
}

const createCategory = async (categoryName) => {
    if (!categoryName || !categoryName.trim()) {
        alert('請輸入分類名稱')
        return
    }
    
    if (creatingCategory.value) return
    creatingCategory.value = true
    
    try {
        const res = await axios.post('/api/categories', { name: categoryName.trim() })
        const newCategory = res.data.items[0]
        
        // 添加到分類列表
        if (!categories.value.find(c => c.id === newCategory.id)) {
            categories.value.push(newCategory)
        }
        
        // 自動選中新建的分類
        form.value.category = newCategory
    } catch (e) {
        console.error('新增分類失敗', e)
        if (e.response?.data?.message) {
            alert(`❌ 新增分類失敗：${e.response.data.message}`)
        } else {
            alert('❌ 新增分類失敗，請確認分類名稱是否正確')
        }
        // 失敗時清空選擇
        form.value.category = null
    } finally {
        creatingCategory.value = false
    }
}

const submitForm = async () => {
    try {
        if (isEdit.value) {
            await axios.put(`/api/products/${route.params.id}`, {
                name: form.value.name,
                brand: form.value.brand,
                category_id: form.value.category?.id,
                model: form.value.model,
                spec: form.value.spec,
                barcode: form.value.barcode,
            })
            alert('✅ 已更新產品')
            router.push(`/products/${route.params.id}`)
        } else {
            const res = await axios.post('/api/products', {
                name: form.value.name,
                brand: form.value.brand,
                category_id: form.value.category?.id,
                model: form.value.model,
                spec: form.value.spec,
                barcode: form.value.barcode,
            })
            alert('✅ 已建立產品')
            // 若需要導向至新產品詳細頁，請改為 router.push(`/products/${res.data.id}`)
            router.push('/products')
        }
    } catch (e) {
        alert('❌ 操作失敗，請確認欄位是否正確')
    }
}

onMounted(async () => {
    try {
        const res = await axios.get('/api/categories')
        // 處理分頁返回的數據結構
        categories.value = res.data.items || res.data || []
    } catch (e) {
        console.error('❌ 載入分類失敗', e)
    }

    if (route.params.id) {
        isEdit.value = true
        try {
            const res = await axios.get(`/api/products/${route.params.id}`)
            const p = res.data.item
            form.value.name = p.name
            form.value.brand = p.brand
            form.value.category = p.category
            form.value.model = p.model
            form.value.spec = p.spec
            form.value.barcode = p.barcode
        } catch (e) {
            alert('❌ 載入產品資料失敗')
            router.push('/products')
        }
    }
})
</script>
