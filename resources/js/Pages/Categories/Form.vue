<template>
    <div class="aligned-page management-page bg-[var(--canvas)] min-h-screen p-4 mx-auto space-y-6">
        <div class="product-heading">
            <h1 class="text-2xl font-bold">{{ isEdit ? '編輯分類' : '建立分類' }}</h1>
            <router-link
                to="/categories"
                class="product-secondary"
            >
                返回列表
            </router-link>
        </div>

        <div class="bg-[var(--surface)] p-6 rounded-xl border border-[var(--border-subtle)] space-y-4">
            <div>
                <label for="category-name" class="block font-medium mb-2 text-sm">分類名稱 <span class="text-[var(--danger)]">*</span></label>
                <input id="category-name" v-model="form.name" maxlength="255" type="text" class="w-full p-2 border rounded" required />
            </div>

            <div class="flex flex-wrap justify-end items-center gap-3 pt-2">
                <AppButton variant="secondary" @click="router.push('/categories')">取消</AppButton>
                <AppButton @click="submitForm">{{ isEdit ? '更新分類' : '建立分類' }}</AppButton>
            </div>
        </div>
    </div>
</template>

<script setup>
import '../../../css/aligned-pages.css'
import '../../../css/management-pages.css'
import AppButton from '../../Components/AppButton.vue'
import { ref, onMounted } from 'vue'
import axios from '../../axios'
import { useRouter, useRoute } from 'vue-router'
import { appDialog as Swal } from '../../utils/appDialog'

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
