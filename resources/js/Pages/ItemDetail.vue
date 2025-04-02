<template>
    <div class="p-4 max-w-md mx-auto space-y-4">
        <button @click="goBack" class="text-blue-500 underline">&larr; 返回</button>

        <h1 class="text-xl font-bold mt-4">📦 物品詳細資料</h1>

        <div v-if="item" class="space-y-2">
            <div><strong>名稱：</strong>{{ item.name }}</div>
            <div><strong>金額：</strong>{{ item.price }}</div>
            <div><strong>數量：</strong>{{ item.quantity }}</div>
            <div><strong>購買日期：</strong>{{ item.purchased_at }}</div>
            <div><strong>描述：</strong>{{ item.description || '—' }}</div>
            <div><strong>位置：</strong>{{ item.location || '—' }}</div>
            <div><strong>是否報廢：</strong>{{ item.is_discarded ? '✅ 是' : '❌ 否' }}</div>
        </div>

        <div v-else>
            <p>讀取中...</p>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'

const route = useRoute()
const router = useRouter()
const item = ref(null)

onMounted(async () => {
    const { id } = route.params
    const res = await axios.get(`/api/items/${id}`)
    item.value = res.data
})

const goBack = () => {
    router.back()
}
</script>
