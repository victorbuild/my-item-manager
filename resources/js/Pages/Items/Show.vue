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
                        <input type="date" class="p-1 border rounded" 
                            :value="tempDates.purchased_at !== null && tempDates.purchased_at !== undefined ? tempDates.purchased_at : (item.purchased_at?.slice(0, 10) || '')"
                            :min="getDateMin(effectiveDates, 'purchased_at')"
                            :max="getDateMax(effectiveDates, 'purchased_at')"
                            @input="(e) => handleDateInput('purchased_at', e.target.value)"
                            @blur="validateDate('purchased_at')"
                            @keyup.enter="saveItemDate('purchased_at')" />
                    </div>
                    <div>
                        📦 到貨日期：
                        <input type="date" class="p-1 border rounded" 
                            :value="tempDates.received_at !== null && tempDates.received_at !== undefined ? tempDates.received_at : (item.received_at?.slice(0, 10) || '')"
                            :min="getDateMin(effectiveDates, 'received_at')"
                            :max="getDateMax(effectiveDates, 'received_at')"
                            @input="(e) => handleDateInput('received_at', e.target.value)"
                            @blur="validateDate('received_at')"
                            @keyup.enter="saveItemDate('received_at')" />
                    </div>
                    <div>
                        🚀 開始使用日期：
                        <input type="date" class="p-1 border rounded" 
                            :value="tempDates.used_at !== null && tempDates.used_at !== undefined ? tempDates.used_at : (item.used_at?.slice(0, 10) || '')"
                            :min="getDateMin(effectiveDates, 'used_at')"
                            :max="getDateMax(effectiveDates, 'used_at')"
                            @input="(e) => handleDateInput('used_at', e.target.value)"
                            @blur="validateDate('used_at')"
                            @keyup.enter="saveItemDate('used_at')" />
                    </div>
                    <div>
                        🗑️ 報廢日期：
                        <input type="date" class="p-1 border rounded" 
                            :value="tempDates.discarded_at !== null && tempDates.discarded_at !== undefined ? tempDates.discarded_at : (item.discarded_at?.slice(0, 10) || '')"
                            :min="getDateMin(effectiveDates, 'discarded_at')"
                            :max="getDateMax(effectiveDates, 'discarded_at')"
                            @input="(e) => handleDateInput('discarded_at', e.target.value)"
                            @blur="validateDate('discarded_at')"
                            @keyup.enter="saveItemDate('discarded_at')" />
                    </div>
                    <div v-if="hasDateChanges" class="mt-2">
                        <button @click="saveAllDates" 
                            class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 shadow">
                            💾 儲存日期變更
                        </button>
                        <button @click="cancelDateChanges" 
                            class="ml-2 bg-gray-400 text-white px-4 py-2 rounded hover:bg-gray-500 shadow">
                            ❌ 取消變更
                        </button>
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
import { getMaxItemDate, getDateMin, getDateMax } from '../../utils/itemDates'
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from '../../axios'
import dayjs from 'dayjs'
import Swal from 'sweetalert2'

const route = useRoute()
const router = useRouter()
const item = ref(null)

const today = dayjs()
const discardNote = ref('')

// 日期上限（今天後一個月）
const maxItemDate = getMaxItemDate()
const effectiveDates = computed(() => Object.fromEntries(
    ['purchased_at', 'received_at', 'used_at', 'discarded_at'].map(field => [
        field, tempDates.value[field] ?? item.value?.[field]?.slice(0, 10) ?? ''
    ])
))

// 臨時日期狀態
const tempDates = ref({
    purchased_at: null,
    received_at: null,
    used_at: null,
    discarded_at: null,
})

import { ITEM_STATUS_LABEL_MAP as statusLabelMap } from '@/constants/itemStatus'

const saveDiscardNote = async () => {
    try {
        await axios.patch(`/api/items/${item.value.short_id}`, {
            discard_note: discardNote.value,
        })
        await Swal.fire({
            icon: 'success',
            title: '成功',
            text: '備註已儲存',
            confirmButtonText: '確定'
        })
        fetchItem()
    } catch (err) {
        let errorMessage = '儲存失敗，請確認欄位是否正確'
        if (err.response?.data?.errors) {
            const errors = err.response.data.errors
            const firstError = Object.values(errors)[0]
            errorMessage = Array.isArray(firstError) ? firstError[0] : firstError
        } else if (err.response?.data?.message) {
            errorMessage = err.response.data.message
        }
        
        await Swal.fire({
            icon: 'error',
            title: '錯誤',
            text: errorMessage,
            confirmButtonText: '確定'
        })
        console.error(err)
    }
}

const fetchItem = async () => {
    try {
        const res = await axios.get(`/api/items/${route.params.id}`)
        item.value = res.data.data
        discardNote.value = res.data.data?.discard_note || ''
        // 重置臨時日期狀態
        tempDates.value = {
            purchased_at: null,
            received_at: null,
            used_at: null,
            discarded_at: null,
        }
    } catch (error) {
        if (error.response?.status === 404) {
            router.push({ name: 'NotFound' })
        } else if (error.response?.status === 403) {
            await Swal.fire({
                icon: 'warning',
                title: '無權限',
                text: '您沒有權限檢視此物品，將返回首頁。',
                confirmButtonText: '確定'
            })
            router.push('/')
        } else {
            console.error('載入失敗', error)
        }
    }
}

onMounted(fetchItem)

const formatPrice = (val) => {
    if (val == null) return '—'
    return Number(val).toLocaleString()
}

// 檢查是否有日期變更
const hasDateChanges = computed(() => {
    // 檢查是否有變更（包括要清除的情況，空字串也算變更）
    return (tempDates.value.purchased_at !== null && tempDates.value.purchased_at !== undefined) ||
           (tempDates.value.received_at !== null && tempDates.value.received_at !== undefined) ||
           (tempDates.value.used_at !== null && tempDates.value.used_at !== undefined) ||
           (tempDates.value.discarded_at !== null && tempDates.value.discarded_at !== undefined)
})

// 取消所有日期變更
const cancelDateChanges = () => {
    tempDates.value = {
        purchased_at: null,
        received_at: null,
        used_at: null,
        discarded_at: null,
    }
}

// 處理日期輸入
const handleDateInput = (field, value) => {
    // 如果值為空，表示用戶想要清除日期
    if (!value) {
        // 使用空字串標記「要清除」的意圖
        const currentValue = item.value?.[field]?.slice(0, 10) || ''
        if (currentValue) {
            // 如果原本有值，設置為空字串表示要清除
            tempDates.value[field] = ''
        } else {
            // 如果原本就沒有值，清除臨時值
            tempDates.value[field] = null
        }
        return
    }
    
    // 檢查日期格式是否有效
    if (!isValidDate(value)) {
        // 如果日期無效，暫時儲存但會在失焦時驗證
        tempDates.value[field] = value
        return
    }
    
    // 如果日期有效，儲存到臨時狀態
    tempDates.value[field] = value
}

// 檢查日期是否有效
const isValidDate = (dateString) => {
    if (!dateString) return false
    
    // 檢查格式是否為 YYYY-MM-DD
    if (!dateString.match(/^\d{4}-\d{2}-\d{2}$/)) {
        return false
    }
    
    // 解析日期字串
    const [year, month, day] = dateString.split('-').map(Number)
    
    // 基本範圍檢查
    if (year < 1900 || year > 2100) return false
    if (month < 1 || month > 12) return false
    if (day < 1 || day > 31) return false
    
    // 使用本地時間創建日期對象（避免時區問題）
    const date = new Date(year, month - 1, day)
    
    // 驗證日期是否正確（避免月份溢出等問題，如 2025-11-31）
    return date.getFullYear() === year &&
           date.getMonth() === month - 1 &&
           date.getDate() === day
}

// 驗證單個日期（失焦時）
const validateDate = (field) => {
    const value = tempDates.value[field]
    
    // 如果值為空字串，表示要清除日期，這是有效的操作
    if (value === '') {
        return
    }
    
    // 如果值為 null 或 undefined，清除臨時值
    if (!value) {
        tempDates.value[field] = null
        return
    }
    
    // 檢查日期格式是否有效
    if (!isValidDate(value)) {
        Swal.fire({
            icon: 'warning',
            title: '日期格式錯誤',
            text: '請輸入有效的日期格式（YYYY-MM-DD）',
            confirmButtonText: '確定'
        }).then(() => {
            // 恢復原始值
            tempDates.value[field] = null
        })
        return
    }
    
    // 檢查日期是否超過今天後一個月
    if (value > maxItemDate) {
        Swal.fire({
            icon: 'warning',
            title: '日期驗證',
            text: '日期不能超過今天後一個月',
            confirmButtonText: '確定'
        }).then(() => {
            // 恢復原始值
            tempDates.value[field] = null
        })
        return
    }
    
    const currentValue = item.value?.[field]?.slice(0, 10) || ''
    if (value === currentValue) {
        // 如果與當前值相同，清除臨時值
        tempDates.value[field] = null
        return
    }
    
    // 前端驗證日期順序
    const purchasedAt = effectiveDates.value.purchased_at
    const receivedAt = effectiveDates.value.received_at
    const usedAt = effectiveDates.value.used_at
    const discardedAt = effectiveDates.value.discarded_at
    
    let errorMessage = null
    
    if (field === 'received_at' && value && purchasedAt && value < purchasedAt) {
        errorMessage = '到貨日期不能早於購買日期'
    } else if (field === 'used_at' && value) {
        // 開始使用日期不能早於購買日期
        if (purchasedAt && value < purchasedAt) {
            errorMessage = '開始使用日期不能早於購買日期'
        }
        // 開始使用日期不能早於到貨日期
        else if (receivedAt && value < receivedAt) {
            errorMessage = '開始使用日期不能早於到貨日期'
        }
    } else if (field === 'discarded_at' && value && usedAt && value < usedAt) {
        errorMessage = '報廢日期不能早於開始使用日期'
    } else if (field === 'discarded_at' && value && receivedAt && !usedAt && value < receivedAt) {
        errorMessage = '報廢日期不能早於到貨日期'
    } else if (field === 'discarded_at' && value && purchasedAt && !receivedAt && !usedAt && value < purchasedAt) {
        errorMessage = '報廢日期不能早於購買日期'
    }
    
    if (errorMessage) {
        Swal.fire({
            icon: 'warning',
            title: '日期驗證',
            text: errorMessage,
            confirmButtonText: '確定'
        }).then(() => {
            // 恢復原始值
            tempDates.value[field] = null
        })
    }
}

// 儲存單個日期（Enter 鍵）
const saveItemDate = async (field) => {
    const value = tempDates.value[field]
    
    // 如果值為空字串，表示要清除日期
    if (value === '') {
        const currentValue = item.value?.[field]?.slice(0, 10) || ''
        if (!currentValue) {
            // 如果原本就沒有值，不需要更新
            tempDates.value[field] = null
            return
        }
        
        try {
            await updateItemDate(field, null)
            tempDates.value[field] = null
        } catch (err) {
            tempDates.value[field] = null
        }
        return
    }
    
    if (!value) return
    
    // 檢查日期格式是否有效
    if (!isValidDate(value)) {
        await Swal.fire({
            icon: 'warning',
            title: '日期格式錯誤',
            text: '請輸入有效的日期格式（YYYY-MM-DD）',
            confirmButtonText: '確定'
        })
        tempDates.value[field] = null
        return
    }
    
    // 檢查日期是否超過今天後一個月
    if (value > maxItemDate) {
        await Swal.fire({
            icon: 'warning',
            title: '日期驗證',
            text: '日期不能超過今天後一個月',
            confirmButtonText: '確定'
        })
        tempDates.value[field] = null
        return
    }
    
    const currentValue = item.value?.[field]?.slice(0, 10) || ''
    if (value === currentValue) {
        tempDates.value[field] = null
        return
    }
    
    try {
        await updateItemDate(field, value)
        tempDates.value[field] = null
    } catch (err) {
        // updateItemDate 內部已經處理錯誤，這裡只需要清除臨時值
        tempDates.value[field] = null
    }
}

// 儲存所有日期變更
const saveAllDates = async () => {
    const updates = {}
    let hasUpdates = false
    const invalidFields = []
    
    // 先驗證所有日期
    for (const field of ['purchased_at', 'received_at', 'used_at', 'discarded_at']) {
        const value = tempDates.value[field]
        
        // 如果值為空字串，表示要清除日期
        if (value === '') {
            const currentValue = item.value?.[field]?.slice(0, 10) || ''
            if (currentValue) {
                // 如果原本有值，發送 null 來清除
                updates[field] = null
                hasUpdates = true
            }
            continue
        }
        
        if (!value) continue
        
        // 檢查日期格式是否有效
        if (!isValidDate(value)) {
            invalidFields.push(field)
            continue
        }
        
        // 檢查日期是否超過今天後一個月
        if (value > maxItemDate) {
            invalidFields.push(field)
            continue
        }
        
        const currentValue = item.value?.[field]?.slice(0, 10) || ''
        if (value !== currentValue) {
            updates[field] = value
            hasUpdates = true
        }
    }
    
    if (invalidFields.length > 0) {
        await Swal.fire({
            icon: 'warning',
            title: '日期格式錯誤',
            text: `以下欄位的日期格式無效：${invalidFields.join('、')}`,
            confirmButtonText: '確定'
        })
        return
    }
    
    if (!hasUpdates) {
        cancelDateChanges()
        return
    }
    
    try {
        await axios.patch(`/api/items/${item.value.short_id}`, updates)
        await Swal.fire({
            icon: 'success',
            title: '成功',
            text: '日期已更新',
            confirmButtonText: '確定'
        })
        cancelDateChanges()
        fetchItem()
    } catch (err) {
        let errorMessage = '更新失敗，請確認欄位是否正確'
        if (err.response?.data?.errors) {
            const errors = err.response.data.errors
            const firstError = Object.values(errors)[0]
            errorMessage = Array.isArray(firstError) ? firstError[0] : firstError
        } else if (err.response?.data?.message) {
            errorMessage = err.response.data.message
        }
        
        await Swal.fire({
            icon: 'error',
            title: '錯誤',
            text: errorMessage,
            confirmButtonText: '確定'
        })
        console.error(err)
    }
}

const updateItemDate = async (field, value) => {
    if (!['purchased_at', 'received_at', 'used_at', 'discarded_at'].includes(field)) return

    try {
        await axios.patch(`/api/items/${item.value.short_id}`, {
            [field]: value
        })
        fetchItem() // 重新取得資料
    } catch (err) {
        let errorMessage = '更新失敗，請確認欄位是否正確'
        if (err.response?.data?.errors) {
            const errors = err.response.data.errors
            const firstError = Object.values(errors)[0]
            errorMessage = Array.isArray(firstError) ? firstError[0] : firstError
        } else if (err.response?.data?.message) {
            errorMessage = err.response.data.message
        }
        
        await Swal.fire({
            icon: 'error',
            title: '錯誤',
            text: errorMessage,
            confirmButtonText: '確定'
        })
        console.error(err)
        throw err
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
