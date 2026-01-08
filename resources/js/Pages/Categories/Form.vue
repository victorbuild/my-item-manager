<template>
    <div class="bg-[#f5f5f5] min-h-screen p-4 max-w-2xl mx-auto space-y-6">
        <div class="flex justify-between items-center">
            <h1 class="text-2xl font-bold">{{ isEdit ? '✏️ 編輯分類' : '➕ 建立分類' }}</h1>
            <router-link
                to="/categories"
                class="text-sm bg-gray-300 hover:bg-gray-400 px-3 py-1 rounded"
            >
                ⬅ 返回
            </router-link>
        </div>

        <div class="bg-white p-6 rounded shadow space-y-4">
            <div>
                <label class="block font-medium">📂 分類名稱 *</label>
                <input v-model="form.name" type="text" class="w-full p-2 border rounded" required />
            </div>

            <div class="flex gap-4 pt-2">
                <button @click="submitForm" class="bg-blue-600 text-white px-4 py-2 rounded shadow">
                    {{ isEdit ? '✅ 更新' : '✅ 建立' }}
                </button>
                <router-link to="/categories" class="text-gray-500 underline">取消</router-link>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from '../../axios'
import { useRouter, useRoute } from 'vue-router'
import Swal from 'sweetalert2'

const router = useRouter()
const route = useRoute()

const isEdit = ref(false)
const form = ref({
    name: ''
})

const submitForm = async () => {
    try {
        if (isEdit.value) {
            await axios.put(`/api/categories/${route.params.id}`, {
                name: form.value.name
            })
            await Swal.fire({
                icon: 'success',
                title: '成功',
                text: '已更新分類',
                confirmButtonText: '確定'
            })
            router.push(`/categories/${route.params.id}`)
        } else {
            await axios.post('/api/categories', {
                name: form.value.name
            })
            await Swal.fire({
                icon: 'success',
                title: '成功',
                text: '已建立分類',
                confirmButtonText: '確定'
            })
            router.push('/categories')
        }
    } catch (e) {
        // 處理驗證錯誤
        let errorMessage = '操作失敗，請確認欄位是否正確'
        if (e.response?.data?.errors) {
            const errors = e.response.data.errors
            const firstError = Object.values(errors)[0]
            errorMessage = Array.isArray(firstError) ? firstError[0] : firstError
        } else if (e.response?.data?.message) {
            errorMessage = e.response.data.message
        }
        
        await Swal.fire({
            icon: 'error',
            title: '錯誤',
            text: errorMessage,
            confirmButtonText: '確定'
        })
    }
}

onMounted(async () => {
    if (route.params.id) {
        isEdit.value = true
        try {
            const res = await axios.get(`/api/categories/${route.params.id}`)
            const c = res.data.items[0]
            form.value.name = c.name
        } catch (e) {
            await Swal.fire({
                icon: 'error',
                title: '錯誤',
                text: '載入分類資料失敗',
                confirmButtonText: '確定'
            })
            router.push('/categories')
        }
    }
})
</script>
