<template>
    <div class="bg-[#f5f5f5] min-h-screen p-4 max-w-2xl mx-auto space-y-6">
        <h1 class="text-2xl font-bold">🔍 物品詳情</h1>

        <div v-if="item" class="bg-white p-6 rounded shadow space-y-4">
            <h2 class="text-xl font-semibold text-gray-800">{{ item.name }}</h2>

            <div class="text-sm text-gray-700 space-y-1">
                <div>📄 描述：{{ item.description || '（無）' }}</div>
                <div>📍 位置：{{ item.location || '（未指定）' }}</div>
                <div>📦 數量：{{ item.quantity }}</div>
                <div>💰 金額：{{ formatPrice(item.price) }}</div>
                <div>📅 購買日期：{{ item.purchased_at }}</div>
            </div>

            <div v-if="item.images?.length" class="flex gap-2 overflow-x-auto">
                <img
                    v-for="(img, idx) in item.images"
                    :key="img.id || idx"
                    :src="img.url"
                    class="w-24 h-24 object-cover rounded border"
                />
            </div>

            <div v-if="item.units?.length" class="mt-4">
                <h3 class="font-semibold text-gray-700">🧾 單位記錄：</h3>
                <ul class="list-disc list-inside text-sm text-gray-600">
                    <li v-for="unit in item.units" :key="unit.id">
                        ID: {{ unit.id }}｜名稱：{{ unit.name }}｜數量：{{ unit.quantity }}
                    </li>
                </ul>
            </div>

            <div class="pt-4">
                <router-link to="/" class="text-blue-500 hover:underline">← 返回列表</router-link>
            </div>
        </div>

        <div v-else class="text-center text-gray-600">載入中...</div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'

const route = useRoute()
const item = ref(null)

const fetchItem = async () => {
    const res = await axios.get(`/api/items/${route.params.id}`)
    item.value = res.data
}

onMounted(fetchItem)

const formatPrice = (val) => {
    if (val == null) return '—'
    return Number(val).toLocaleString()
}
</script>

<style scoped>
body {
    background-color: #f5f5f5;
}
</style>
