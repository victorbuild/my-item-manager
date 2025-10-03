<template>
    <div class="bg-[#f5f5f5] min-h-screen p-4 max-w-2xl mx-auto space-y-6">
        <h1 class="text-2xl font-bold">🔍 物品詳情</h1>

        <template v-if="item">
            <!-- 📦 Item 資訊卡 -->
            <div class="bg-white p-6 rounded shadow space-y-4">
                <div class="flex justify-between items-center">
                    <h2 class="text-xl font-semibold text-gray-800">{{ item.name }}</h2>
                    <router-link :to="`/items/${item.short_id}/edit`" class="text-sm text-blue-600 hover:underline">
                        ✏️ 編輯
                    </router-link>
                </div>

                <div class="text-sm text-gray-700 space-y-1">
                    <div v-if="item.description" class="font-medium">📄 描述</div>
                    <hr v-if="item.description">
                    <div v-if="item.description" style="white-space: pre-line;" class="mb-2 ml-2">{{ item.description }}</div>
                    <hr v-if="item.description">
                    <div>💰 金額：{{ formatPrice(item.price) }}</div>
                    <div>🧊 有效期限：{{ item.expiration_date || '-' }}</div>
                    <div>📍 位置：{{ item.location || '（未指定）' }}</div>
                    <br>
                    <div>🔢 序號：{{ item.serial_number || '-' }}</div>
                    <div>📅 購買日期：{{ item.purchased_at }}</div>
                    <div>📦 到貨日期：{{ item.received_at || '（未填寫）' }}</div>
                    <div>🚀 開始使用日期：{{ item.used_at || '（未填寫）' }}</div>
                    <div>🗑️ 報廢日期：{{ item.discarded_at || '-' }}</div>
                </div>

                <!-- 瀑布流圖片牆 -->
                <div
                    v-if="item.images?.length"
                    class="masonry-gallery"
                >
                    <img
                        v-for="(img, idx) in item.images"
                        :key="img.id || idx"
                        :src="img.preview_url"
                        class="masonry-img"
                        :alt="item.name"
                        @click="openLightbox(idx)"
                        style="cursor:pointer"
                    />
                </div>

                <div class="space-y-2">
                    <div>
                        📅 購買日期：
                        <input type="date" class="p-1 border rounded" :value="item.purchased_at?.slice(0, 10)"
                            @change="(e) => updateItemDate('purchased_at', e.target.value)" />
                    </div>
                    <div>
                        📦 到貨日期：
                        <input type="date" class="p-1 border rounded" :value="item.received_at?.slice(0, 10)"
                            @change="(e) => updateItemDate('received_at', e.target.value)" />
                    </div>
                    <div>
                        🚀 開始使用日期：
                        <input type="date" class="p-1 border rounded" :value="item.used_at?.slice(0, 10)"
                            @change="(e) => updateItemDate('used_at', e.target.value)" />
                    </div>
                    <div>
                        🗑️ 報廢日期：
                        <input type="date" class="p-1 border rounded" :value="item.discarded_at?.slice(0, 10)"
                            @change="(e) => updateItemDate('discarded_at', e.target.value)" />
                    </div>
                    <hr>
                    <div class="mt-3">
                        <label class="block text-sm font-medium text-gray-600">📝 棄用備註</label>
                        <textarea v-model="discardNote" rows="3" class="w-full p-2 border rounded"
                            placeholder="你想對這件物品說些什麼..."></textarea>
                        <button @click="saveDiscardNote"
                            class="mt-2 bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 shadow">
                            ✅ 儲存備註
                        </button>
                    </div>

                </div>
                <div class="text-sm text-gray-700 space-y-1 border-t pt-4 mt-4">
                    <div>📦 到貨時間：{{ getDeliveryDays() !== null ? `${getDeliveryDays()} 天` : '—' }}</div>
                    <div>📦 購買到使用：{{ getDaysFromPurchaseToUse() !== null ? `${getDaysFromPurchaseToUse()}` : '—' }}
                    </div>
                    <div>📅 使用至今：{{ getDaysUsedUntilNow() !== null ? `${getDaysUsedUntilNow()} 天` : '尚未使用' }}</div>
                    <div>🗑️ 使用到報廢：{{ getDaysUsedUntilDiscarded() !== null ? `${getDaysUsedUntilDiscarded()} 天` : '—' }}
                    </div>
                    <div>⏳ 狀態：{{ statusLabelMap[item.status] || '—' }}</div>
                    <div>💰 平均每日成本：{{ getItemCostPerDay() !== null ? `${getItemCostPerDay()} 元` : '—' }}</div>
                </div>
            </div>

            <!-- 📦 所屬產品卡片 -->
            <div v-if="item.product" class="bg-white p-6 rounded shadow space-y-2">
                <h2 class="text-lg font-semibold text-gray-800">📦 所屬產品資訊</h2>
                <div><strong>📛 名稱：</strong>
                    <router-link :to="`/products/${item.product?.short_id}`" class="text-blue-600 hover:underline">
                        {{ item.product?.name || '（無）' }}
                    </router-link>
                </div>
                <div><strong>📂 分類：</strong> {{ item.product?.category?.name || '未分類' }}</div>
                <div>📦 條碼：{{ item.product?.barcode || '-' }}</div>
            </div>

            <!-- 🧾 單位卡片們 -->
            <div v-if="item?.units?.length" class="space-y-3">
                <h3 class="text-lg font-semibold text-gray-700">單品記錄：</h3>
                <div v-for="unit in item.units" :key="unit.id" class="bg-white rounded-lg p-4 shadow space-y-2">
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
                        <input type="date" class="p-1 border rounded w-full max-w-xs"
                            :value="unit.used_at?.slice(0, 10)"
                            @change="(e) => updateUsedDate(unit.id, e.target.value)" />

                        <div v-if="unit.used_at">
                            <label class="block text-sm text-gray-600">丟棄時間：</label>
                            <input type="date" class="p-1 border rounded w-full max-w-xs"
                                :value="unit.discarded_at?.slice(0, 10)"
                                @change="(e) => updateDiscardDate(unit.id, e.target.value)" />
                        </div>

                        <div class="text-sm text-gray-600">
                            ⏳ 使用天數：
                            <span v-if="getUsageDays(unit)">{{ getUsageDays(unit) }} 天</span>
                            <span v-else class="text-gray-400">尚未開始</span>
                        </div>
                        <div class="text-sm text-gray-600" v-if="getCostPerDay(unit)">
                            💰 每日成本：{{ getCostPerDay(unit) }} 元
                        </div>
                    </div>
                </div>
            </div>

            <div class="pt-6">
                <router-link to="/items" class="text-blue-500 hover:underline">← 返回列表</router-link>
            </div>
        </template>

        <template v-else>
            <div class="text-center text-gray-600">載入中...</div>
        </template>

        <!-- Lightbox 預覽 -->
        <div v-if="lightbox.open" class="lightbox-backdrop" @click.self="closeLightbox">
            <div class="lightbox-content">
                <img
                    :src="item.images[lightbox.index].preview_url"
                    :alt="item.name"
                    class="lightbox-img"
                    :class="{ 'lightbox-img-animate': lightbox.animate }"
                    @animationend="lightbox.animate = false"
                />
                <div class="lightbox-counter-below">
                    {{ lightbox.index + 1 }} / {{ item.images.length }}
                </div>
                <button class="lightbox-close" @click="closeLightbox" aria-label="關閉">×</button>
                <button v-if="lightbox.index > 0" class="lightbox-nav left" @click.stop="prevImage" aria-label="上一張">‹</button>
                <button v-if="lightbox.index < item.images.length - 1" class="lightbox-nav right" @click.stop="nextImage" aria-label="下一張">›</button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from '../../axios'
import dayjs from 'dayjs'

const route = useRoute()
const router = useRouter()
const item = ref(null)

const today = dayjs()
const discardNote = ref('')

import { ITEM_STATUS_LABEL_MAP as statusLabelMap } from '@/constants/itemStatus'

const saveDiscardNote = async () => {
    try {
        await axios.patch(`/api/items/${item.value.short_id}`, {
            discard_note: discardNote.value,
        })
        alert('✅ 備註已儲存')
        fetchItem()
    } catch (err) {
        alert('❌ 儲存失敗')
        console.error(err)
    }
}

const fetchItem = async () => {
    try {
        const res = await axios.get(`/api/items/${route.params.id}`)
        item.value = res.data.items[0]
        discardNote.value = res.data.items[0]?.discard_note || ''
    } catch (error) {
        if (error.response && error.response.status === 404) {
            // ✅ 跳轉 Vue 的 404 NotFound 頁面
            router.push({ name: 'NotFound' })
        } else {
            // ✅ 可選：處理其他錯誤
            console.error('載入失敗', error)
        }
    }
}

onMounted(fetchItem)

const formatPrice = (val) => {
    if (val == null) return '—'
    return Number(val).toLocaleString()
}

const updateItemDate = async (field, value) => {
    if (!['purchased_at', 'received_at', 'used_at', 'discarded_at'].includes(field)) return

    try {
        await axios.patch(`/api/items/${item.value.short_id}`, {
            [field]: value
        })
        fetchItem() // 重新取得資料
    } catch (err) {
        alert('❌ 更新失敗')
        console.error(err)
    }
}

// 計算使用天數
const getUsageDays = (unit) => {
    if (!unit.used_at) return null
    const start = dayjs(unit.used_at)
    const end = unit.discarded_at ? dayjs(unit.discarded_at) : today
    return end.diff(start, 'day') + 1
}

// 計算每日成本
const getCostPerDay = (unit) => {
    const days = getUsageDays(unit)
    if (!days || !item.value?.price || item.value.quantity === 0) return null

    const unitPrice = item.value.price / item.value.quantity
    return (unitPrice / days).toFixed(2)
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

// 計算到貨時間（從購買到到貨）
const getDeliveryDays = () => {
    if (!item.value?.purchased_at || !item.value?.received_at) return null
    return dayjs(item.value.received_at).diff(dayjs(item.value.purchased_at), 'day')
}

// 開始使用到現在的天數
const getDaysUsedUntilNow = () => {
    if (!item.value?.used_at) return null
    return today.diff(dayjs(item.value.used_at), 'day') + 1
}

// 開始使用到報廢的天數
const getDaysUsedUntilDiscarded = () => {
    if (!item.value?.used_at || !item.value?.discarded_at) return null
    return dayjs(item.value.discarded_at).diff(dayjs(item.value.used_at), 'day') + 1
}

// 是否從未開始使用
const isNeverUsed = () => {
    return !item.value?.used_at
}

// 平均每日成本（以 item 為整體）
const getItemCostPerDay = () => {
    const days = item.value?.discarded_at
        ? getDaysUsedUntilDiscarded()
        : getDaysUsedUntilNow()

    if (!days || !item.value?.price) return null
    return (item.value.price / days).toFixed(2)
}

// 購買到開始使用的天數
const getDaysFromPurchaseToUse = () => {
    const purchased = item.value?.purchased_at
    const used = item.value?.used_at

    if (!purchased) return null

    if (used) {
        return dayjs(used).diff(dayjs(purchased), 'day') + ' 天'
    } else {
        const daysSincePurchase = today.diff(dayjs(purchased), 'day')
        return `尚未使用（已過 ${daysSincePurchase} 天）`
    }
}

const lightbox = ref({
    open: false,
    index: 0,
    animate: false,
})

const openLightbox = (idx) => {
    lightbox.value.open = true
    lightbox.value.index = idx
    lightbox.value.animate = true
    document.body.style.overflow = 'hidden'
}

const closeLightbox = () => {
    lightbox.value.open = false
    document.body.style.overflow = ''
}

const prevImage = () => {
    if (lightbox.value.index > 0) {
        lightbox.value.index--
        lightbox.value.animate = true
    }
}

const nextImage = () => {
    if (item.value?.images && lightbox.value.index < item.value.images.length - 1) {
        lightbox.value.index++
        lightbox.value.animate = true
    }
}

// 防止滾輪捲動
const preventScroll = (e) => {
    if (lightbox.value.open) {
        e.preventDefault()
    }
}
onMounted(() => {
    window.addEventListener('wheel', preventScroll, { passive: false })
})
onUnmounted(() => {
    window.removeEventListener('wheel', preventScroll)
})
</script>

<style scoped>
body {
    background-color: #f5f5f5;
}

/* Masonry 瀑布流圖片牆 */
.masonry-gallery {
    column-count: 2;
    column-gap: 8px;
    width: 100%;
    padding: 0 4px;
}

.masonry-img {
    width: 100%;
    display: block;
    margin-bottom: 8px;
    border-radius: 8px;
    /* 移除 border */
    box-sizing: border-box;
    object-fit: cover;
    break-inside: avoid;
    background: #fafafa;
    transition: box-shadow 0.15s;
}

.masonry-img:hover {
    box-shadow: 0 2px 8px #0002;
}

/* Lightbox 樣式 */
.lightbox-backdrop {
    position: fixed;
    z-index: 50;
    inset: 0;
    background: rgba(0,0,0,0.85);
    display: flex;
    align-items: center;
    justify-content: center;
}

.lightbox-content {
    position: relative;
    max-width: 96vw;
    max-height: 96vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.lightbox-img {
    max-width: 88vw;
    max-height: 80vh;
    border-radius: 12px;
    background: #fff;
    box-shadow: 0 4px 24px #0005;
    /* 移除 border */
    transition: opacity 0.25s, transform 0.25s;
    opacity: 1;
}
.lightbox-img-animate {
    animation: lightbox-fadein 0.25s;
}
@keyframes lightbox-fadein {
    from {
        opacity: 0;
        transform: scale(0.96);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

/* lightbox 頁數指示（下方且小一點） */
.lightbox-counter-below {
    color: #fff;
    background: rgba(0,0,0,0.35);
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 0.95rem;
    margin-top: 12px;
    z-index: 3;
    pointer-events: none;
    user-select: none;
}

.lightbox-close {
    position: absolute;
    top: 8px;
    right: 16px;
    background: none;
    border: none;
    color: #fff;
    font-size: 2rem;
    cursor: pointer;
    z-index: 2;
    line-height: 1;
}

.lightbox-nav {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #fff;
    font-size: 2.5rem;
    cursor: pointer;
    z-index: 2;
    padding: 0 10px;
    user-select: none;
}

.lightbox-nav.left {
    left: 0;
}

.lightbox-nav.right {
    right: 0;
}
</style>
