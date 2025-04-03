<template>
    <div class="bg-[#f5f5f5] min-h-screen p-4 max-w-2xl mx-auto space-y-6">
        <h1 class="text-2xl font-bold">🔍 物品詳情</h1>

        <template v-if="item">
            <!-- 📦 Item 資訊卡 -->
            <div class="bg-white p-6 rounded shadow space-y-4">
                <div class="flex justify-between items-center">
                    <h2 class="text-xl font-semibold text-gray-800">{{ item.name }}</h2>
                    <router-link
                        :to="`/edit/${item.short_id}`"
                        class="text-sm text-blue-600 hover:underline"
                    >
                        ✏️ 編輯
                    </router-link>
                </div>

                <div class="text-sm text-gray-700 space-y-1">
                    <div>📄 描述：{{ item.description || '（無）' }}</div>
                    <div>📍 位置：{{ item.location || '（未指定）' }}</div>
                    <div>📦 數量：{{ item.quantity }}</div>
                    <div>💰 金額：{{ formatPrice(item.price) }}</div>
                    <div>📅 購買日期：{{ item.purchased_at }}</div>
                    <div>📦 條碼：{{ item.barcode || '（無）' }}</div>
                    <div>
                        🗑️ 狀態：
                        <span v-if="item.is_discarded" class="text-green-600">✅ 已報廢</span>
                        <span v-else class="text-gray-500">尚未報廢</span>
                    </div>
                    <div v-if="item.discarded_at">📅 報廢日期：{{ item.discarded_at }}</div>
                </div>

                <div v-if="item.images?.length" class="grid grid-cols-2 gap-2">
                    <img
                        v-for="(img, idx) in item.images"
                        :key="img.id || idx"
                        :src="img.url"
                        class="w-full h-32 object-cover rounded border"
                        :alt="item.name"
                    />
                </div>
            </div>

            <!-- 🧾 單位卡片們 -->
            <div v-if="item?.units?.length" class="space-y-3">
                <h3 class="text-lg font-semibold text-gray-700">單品記錄：</h3>
                <div
                    v-for="unit in item.units"
                    :key="unit.id"
                    class="bg-white rounded-lg p-4 shadow space-y-2"
                >
                    <div class="flex justify-between items-center">
                        <div class="font-medium">單品 #{{ unit.unit_number }}</div>
                        <div>
                            丟棄：
                            <span v-if="unit.discarded_at" class="text-green-600">✅ 已丟棄</span>
                            <span v-else class="text-red-500">❌ 未丟棄</span>
                        </div>
                    </div>
                    <div>📄 備註：{{ unit.notes || '—' }}</div>

                    <div class="space-y-2">
                        <label class="block text-sm text-gray-600">開始使用時間：</label>
                        <input
                            type="date"
                            class="p-1 border rounded w-full max-w-xs"
                            :value="unit.used_at?.slice(0, 10)"
                            @change="(e) => updateUsedDate(unit.id, e.target.value)"
                        />

                        <div v-if="unit.used_at">
                            <label class="block text-sm text-gray-600">丟棄時間：</label>
                            <input
                                type="date"
                                class="p-1 border rounded w-full max-w-xs"
                                :value="unit.discarded_at?.slice(0, 10)"
                                @change="(e) => updateDiscardDate(unit.id, e.target.value)"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <div class="pt-6">
                <router-link to="/" class="text-blue-500 hover:underline">← 返回列表</router-link>
            </div>
        </template>

        <template v-else>
            <div class="text-center text-gray-600">載入中...</div>
        </template>
    </div>
</template>

<script setup>
import {ref, onMounted} from 'vue'
import {useRoute} from 'vue-router'
import axios from 'axios'

const route = useRoute()
const item = ref(null)

const fetchItem = async () => {
    const res = await axios.get(`/api/items/${route.params.id}`)
    item.value = res.data.items[0]
}

onMounted(fetchItem)

const formatPrice = (val) => {
    if (val == null) return '—'
    return Number(val).toLocaleString()
}

const updateDiscardDate = async (unitId, date) => {
    if (!confirm('確定要設定丟棄時間？')) return
    try {
        await axios.patch(`/api/item-units/${unitId}`, {
            discarded_at: date
        })
        fetchItem()
    } catch (err) {
        alert('更新失敗')
        console.error(err)
    }
}

const updateUsedDate = async (unitId, date) => {
    try {
        await axios.patch(`/api/item-units/${unitId}`, {
            used_at: date
        })
        fetchItem()
    } catch (err) {
        alert('更新失敗')
        console.error(err)
    }
}

</script>

<style scoped>
body {
    background-color: #f5f5f5;
}
</style>
