<template>
    <div class="bg-[#f5f5f5] min-h-screen p-4 max-w-3xl mx-auto space-y-6">
        <div class="flex justify-between items-center">
            <h1 class="text-2xl font-bold">📦 產品詳情</h1>
            <router-link to="/products" class="text-sm bg-gray-300 hover:bg-gray-400 px-3 py-1 rounded">⬅ 返回
            </router-link>
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
        <div v-if="product?.status_counts"
             class="bg-white p-4 rounded shadow grid [grid-template-columns:repeat(auto-fit,minmax(0,1fr))] gap-4 text-sm font-medium text-center">
            <div class="flex flex-col items-center cursor-pointer space-y-1" @click="toggleTip('pre_arrival')">
                <div class="text-gray-500 whitespace-nowrap">📭 未到貨</div>
                <div class="text-xl min-h-[32px] whitespace-nowrap">{{ product.status_counts.pre_arrival }}</div>
                <div v-if="activeTip === 'pre_arrival'"
                     class="text-xs text-gray-500 mt-1 bg-gray-100 rounded px-2 py-1">{{ statusTips.pre_arrival }}
                </div>
            </div>
            <div class="flex flex-col items-center cursor-pointer space-y-1" @click="toggleTip('stored')">
                <div class="text-gray-500 whitespace-nowrap">📦 未使用</div>
                <div class="text-xl min-h-[32px] whitespace-nowrap">{{ product.status_counts.stored }}</div>
                <div v-if="activeTip === 'stored'" class="text-xs text-gray-500 mt-1 bg-gray-100 rounded px-2 py-1">
                    {{ statusTips.stored }}
                </div>
            </div>
            <div class="flex flex-col items-center cursor-pointer space-y-1" @click="toggleTip('in_use')">
                <div class="text-gray-500 whitespace-nowrap">🟢 使用中</div>
                <div class="text-xl min-h-[32px] whitespace-nowrap">{{ product.status_counts.in_use }}</div>
                <div v-if="activeTip === 'in_use'" class="text-xs text-gray-500 mt-1 bg-gray-100 rounded px-2 py-1">
                    {{ statusTips.in_use }}
                </div>
            </div>
            <div class="flex flex-col items-center cursor-pointer space-y-1" @click="toggleTip('discarded')">
                <div class="text-gray-500 whitespace-nowrap">🗑️ 報廢</div>
                <div class="text-xl flex flex-wrap justify-center min-h-[32px]">
                    <span>{{ product.status_counts.used_and_gone }}</span>
                    <span class="text-red-500 cursor-pointer whitespace-nowrap"
                          @click.stop="toggleTip('discarded_unused')">({{
                            product.status_counts.unused_but_gone
                        }})</span>
                </div>
                <div v-if="activeTip === 'discarded'" class="text-xs text-gray-500 mt-1 bg-gray-100 rounded px-2 py-1">
                    {{ statusTips.discarded }}
                </div>
                <div v-if="activeTip === 'discarded_unused'"
                     class="text-xs text-red-500 mt-1 bg-gray-100 rounded px-2 py-1">購買後未使用直接報廢
                </div>
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
                                <img :src="item.first_thumb_url" :alt="item.name || '物品圖片'" class="h-24 rounded border"/>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </template>
    </div>
</template>

<script setup>
import {ref, onMounted, computed} from 'vue'
import axios from '../../axios'
import {useRoute} from 'vue-router'

const route = useRoute()
const product = ref(null)

const activeTip = ref(null)
const toggleTip = (key) => {
    activeTip.value = activeTip.value === key ? null : key
}
const statusTips = {
    pre_arrival: '尚未收到貨，未開始使用',
    stored: '貨已到但尚未開始使用',
    in_use: '目前正在使用中',
    discarded: '已使用後報廢的項目，括號內為未使用直接報廢的數量'
}

onMounted(async () => {
    try {
        const res = await axios.get(`/api/products/${route.params.id}`)
        product.value = res.data.item
    } catch (e) {
        if (e.response?.status !== 401) {
            alert('❌ 載入產品失敗')
        }
        // 可選：401 的處理，如跳轉登入頁等
    }
})

const groupedItems = computed(() => {
    if (!product.value?.items) return {}

    const items = product.value.items
    return {
        using: items.filter(i => i.used_at && !i.discarded_at),
        owned: items.filter(i => !i.used_at && !i.discarded_at && i.purchased_at),
        pending: items.filter(i => !i.used_at && !i.purchased_at && !i.discarded_at),
        discarded: items.filter(i => i.discarded_at)
    }
})
</script>
