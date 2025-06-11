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

const onSearch = async (query) => {
    try {
        const res = await axios.get('/api/categories', { params: { q: query } })
        categories.value = res.data

        if (!categories.value.find(c => c.name === query)) {
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

const onSelect = async (option) => {
    if (option.isNew) {
        try {
            const res = await axios.post('/api/categories', { name: option._rawName })
            form.value.category = res.data
            await onSearch('')
        } catch (e) {
            alert('新增分類失敗')
        }
    } else {
        form.value.category = option
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
        categories.value = res.data
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
