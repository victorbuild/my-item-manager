<template>
    <div class="bg-[#f5f5f5] min-h-screen p-4 max-w-3xl mx-auto space-y-6">
        <div class="flex justify-between items-center">
            <h1 class="text-2xl font-bold">📦 產品詳情</h1>
            <router-link to="/products" class="text-sm bg-gray-300 hover:bg-gray-400 px-3 py-1 rounded">⬅ 返回</router-link>
        </div>

        <div v-if="product" class="bg-white p-6 rounded shadow space-y-4">
            <div><strong>📛 名稱：</strong>{{ product.name }}</div>
            <div><strong>🏷️ 品牌：</strong>{{ product.brand || '—' }}</div>
            <div><strong>📂 分類：</strong>{{ product.category?.name || '未分類' }}</div>
            <div><strong>🧾 型號：</strong>{{ product.model || '—' }}</div>
            <div><strong>⚙️ 規格：</strong>{{ product.spec || '—' }}</div>
            <div><strong>🔢 條碼：</strong>{{ product.barcode || '—' }}</div>
            <div><strong>📊 總數：</strong>{{ product.items.length }}</div>
        </div>

        <!-- 統計卡片 -->
        <div v-if="product?.items?.length" class="bg-white p-4 rounded shadow flex justify-between text-center text-sm font-medium">
            <div class="flex-1">
                <div class="text-gray-500">🟢 使用中</div>
                <div class="text-xl">{{ groupedItems.using.length }}</div>
            </div>
            <div class="flex-1">
                <div class="text-gray-500">📦 擁有中</div>
                <div class="text-xl">{{ groupedItems.owned.length }}</div>
            </div>
            <div class="flex-1">
                <div class="text-gray-500">📭 未到貨</div>
                <div class="text-xl">{{ groupedItems.pending.length }}</div>
            </div>
            <div class="flex-1">
                <div class="text-gray-500">🗑️ 已棄用</div>
                <div class="text-xl">{{ groupedItems.discarded.length }}</div>
            </div>
        </div>

        <template v-if="product?.items?.length">
            <div class="space-y-6">
                <template v-for="(group, key) in {
                    using: '🟢 使用中',
                    owned: '📦 擁有中',
                    pending: '📭 未到貨',
                    discarded: '🗑️ 已棄用'
                }">
                    <div v-if="groupedItems[key]?.length" :key="key" class="bg-white p-6 rounded shadow space-y-4">
                        <h2 class="text-lg font-semibold">{{ group }}</h2>
                        <div v-for="item in groupedItems[key]" :key="item.id" class="border-b pb-2 mb-2">
                            <div><strong>#{{ item.unit_number }}</strong></div>
                            <div>📅 購買日期：{{ item.purchased_at || '—' }}</div>
                            <div>🚀 使用時間：{{ item.used_at || '—' }}</div>
                            <div>🗑️ 棄用時間：{{ item.discarded_at || '—' }}</div>
                            <div>📝 備註：{{ item.notes || '—' }}</div>
                            <div v-if="item.first_thumb_url">
                                <img :src="item.first_thumb_url" class="h-24 rounded border" />
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </template>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'
import { useRoute } from 'vue-router'

const route = useRoute()
const product = ref(null)

onMounted(async () => {
    try {
        const res = await axios.get(`/api/products/${route.params.id}`)
        product.value = res.data.item
    } catch (e) {
        alert('❌ 載入產品失敗')
    }
})

const groupedItems = computed(() => {
    if (!product.value?.items) return {}

    const items = product.value.items
    return {
        using: items.filter(i => i.started_at && !i.discarded_at),
        owned: items.filter(i => !i.started_at && !i.discarded_at && i.purchased_at),
        pending: items.filter(i => !i.started_at && !i.purchased_at && !i.discarded_at),
        discarded: items.filter(i => i.discarded_at)
    }
})
</script>
