<template>
    <div class="container mx-auto px-3 py-4">
        <div class="mb-4">
            <h1 class="text-2xl font-bold text-gray-800 mb-1">媒體櫃</h1>
            <p class="text-sm text-gray-600 mb-3">管理您的圖片媒體</p>
            
            <!-- 配額進度條 -->
            <div class="bg-white p-3 rounded-lg shadow">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-gray-700">圖片配額</span>
                    <span class="text-sm text-gray-600">
                        <span v-if="quota.is_unlimited">{{ quota.used }}張 / ∞</span>
                        <span v-else>{{ quota.used }}張 / {{ quota.limit }}張</span>
                    </span>
                </div>
                
                <!-- 進度條 -->
                <div v-if="!quota.is_unlimited" class="w-full bg-gray-200 rounded-full h-2.5 mb-2">
                    <div
                        class="h-2.5 rounded-full transition-all duration-300"
                        :class="{
                            'bg-green-500': quota.percentage < 80,
                            'bg-yellow-500': quota.percentage >= 80 && quota.percentage < 95,
                            'bg-red-500': quota.percentage >= 95
                        }"
                        :style="{ width: quota.percentage + '%' }"
                    ></div>
                </div>
                
                <!-- 無限制提示 -->
                <div v-else class="w-full bg-gradient-to-r from-green-100 to-blue-100 rounded-full h-2.5 mb-2"></div>
                
                <!-- 配額訊息 -->
                <p class="text-xs text-gray-500">
                    {{ quota.message }}
                    <span class="inline-block ml-1">🎉🎉🎉🐴</span>
                </p>
            </div>
        </div>

        <!-- 篩選器 -->
        <div class="mb-4 bg-white p-3 rounded-lg shadow">
            <div class="flex flex-col gap-3">
                <div class="flex items-center gap-2">
                    <label class="text-xs font-medium text-gray-700 flex-shrink-0">狀態：</label>
                    <select v-model="statusFilter" @change="fetchImages" class="flex-1 px-2 py-1.5 text-sm border border-gray-300 rounded-md">
                        <option value="">全部</option>
                        <option value="draft">草稿</option>
                        <option value="used">已使用</option>
                    </select>
                </div>
                
                <div class="flex items-center gap-2">
                    <label class="text-xs font-medium text-gray-700 flex-shrink-0">關聯：</label>
                    <select v-model="hasItemsFilter" @change="fetchImages" class="flex-1 px-2 py-1.5 text-sm border border-gray-300 rounded-md">
                        <option value="">全部</option>
                        <option value="true">有關聯</option>
                        <option value="false">沒有關聯</option>
                    </select>
                </div>
                
                <div class="flex items-center gap-2">
                    <label class="text-xs font-medium text-gray-700 flex-shrink-0">每頁：</label>
                    <select v-model="perPage" @change="fetchImages(1)" class="flex-1 px-2 py-1.5 text-sm border border-gray-300 rounded-md">
                        <option value="24">24</option>
                        <option value="48">48</option>
                        <option value="96">96</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- 載入中 -->
        <div v-if="loading" class="text-center py-8">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
            <p class="mt-3 text-sm text-gray-600">載入中...</p>
        </div>

        <!-- 圖片網格 -->
        <div v-else-if="images.length > 0" class="grid grid-cols-3 gap-2">
            <div
                v-for="image in images"
                :key="image.uuid"
                class="bg-white rounded-lg shadow hover:shadow-lg transition cursor-pointer overflow-hidden group"
                @click="showImageDetail(image)"
            >
                <div class="aspect-square bg-gray-100 relative overflow-hidden">
                    <img
                        :src="image.thumb_url"
                        :alt="image.image_path"
                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-200"
                        @error="handleImageError($event, image)"

                        loading="lazy"
                    />
                </div>
                <div class="p-1.5">
                    <div class="flex items-center gap-1.5 justify-between">
                        <span
                            :class="{
                                'bg-yellow-100 text-yellow-800': image.status === 'draft',
                                'bg-green-100 text-green-800': image.status === 'used'
                            }"
                            class="px-1.5 py-0.5 rounded text-xs font-medium"
                        >
                            {{ image.status === 'draft' ? '草稿' : '已使用' }}
                        </span>
                        <div class="flex items-center gap-1">
                            <span v-if="image.usage_count > 0" class="text-xs text-gray-500">
                                {{ image.usage_count }}
                            </span>
                            <button
                                @click.stop="deleteImage(image)"
                                :disabled="image.usage_count > 0"
                                class="text-red-500 hover:text-red-700 disabled:text-gray-400 disabled:cursor-not-allowed text-sm p-1"
                                title="刪除圖片"
                            >
                                🗑️
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 空狀態 -->
        <div v-else class="text-center py-8 bg-white rounded-lg shadow">
            <p class="text-sm text-gray-600">沒有找到圖片</p>
        </div>

        <!-- 分頁 -->
        <div v-if="pagination.last_page > 1" class="mt-4 flex justify-center">
            <nav class="flex gap-2 items-center">
                <button
                    @click="fetchImages(pagination.current_page - 1)"
                    :disabled="pagination.current_page === 1"
                    class="px-3 py-1.5 text-sm border border-gray-300 rounded-md disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
                >
                    上一頁
                </button>
                <span class="px-3 py-1.5 text-sm text-gray-700">
                    {{ pagination.current_page }} / {{ pagination.last_page }}
                </span>
                <button
                    @click="fetchImages(pagination.current_page + 1)"
                    :disabled="pagination.current_page === pagination.last_page"
                    class="px-3 py-1.5 text-sm border border-gray-300 rounded-md disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
                >
                    下一頁
                </button>
            </nav>
        </div>

        <!-- 圖片詳情 Modal -->
        <div
            v-if="selectedImage"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-3"
            @click.self="selectedImage = null"
        >
            <div class="bg-white rounded-lg w-full max-w-md max-h-[90vh] overflow-y-auto">
                <div class="p-4">
                    <div class="flex justify-between items-start mb-3">
                        <h2 class="text-lg font-bold text-gray-800">圖片詳情</h2>
                        <div class="flex gap-2">
                            <button
                                @click="deleteImage(selectedImage)"
                                :disabled="selectedImage.usage_count > 0"
                                class="px-3 py-1 bg-red-500 text-white text-sm rounded hover:bg-red-600 disabled:bg-gray-400 disabled:cursor-not-allowed"
                            >
                                刪除
                            </button>
                            <button
                                @click="selectedImage = null"
                                class="text-gray-500 hover:text-gray-700 text-2xl leading-none"
                            >
                                ×
                            </button>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <!-- 圖片預覽 -->
                        <div>
                            <img
                                :src="selectedImage.preview_url"
                                :alt="selectedImage.image_path"
                                class="w-full rounded-lg shadow"
                            />
                        </div>

                        <!-- 詳細資訊 -->
                        <div class="space-y-3">
                            <div>
                                <label class="text-xs font-medium text-gray-700">UUID</label>
                                <p class="text-xs text-gray-900 font-mono break-all mt-0.5">{{ selectedImage.uuid }}</p>
                            </div>
                            <div>
                                <label class="text-xs font-medium text-gray-700">狀態</label>
                                <div class="mt-0.5">
                                    <span
                                        :class="{
                                            'bg-yellow-100 text-yellow-800': selectedImage.status === 'draft',
                                            'bg-green-100 text-green-800': selectedImage.status === 'used'
                                        }"
                                        class="px-2 py-1 rounded text-xs font-medium"
                                    >
                                        {{ selectedImage.status === 'draft' ? '草稿' : '已使用' }}
                                    </span>
                                </div>
                            </div>
                            <div>
                                <label class="text-xs font-medium text-gray-700">使用次數</label>
                                <p class="text-xs text-gray-900 mt-0.5">{{ selectedImage.usage_count }}</p>
                            </div>
                            <div>
                                <label class="text-xs font-medium text-gray-700">建立時間</label>
                                <p class="text-xs text-gray-900 mt-0.5">{{ formatDate(selectedImage.created_at) }}</p>
                            </div>
                            <div>
                                <label class="text-xs font-medium text-gray-700">更新時間</label>
                                <p class="text-xs text-gray-900 mt-0.5">{{ formatDate(selectedImage.updated_at) }}</p>
                            </div>
                            
                            <!-- 關聯的物品 -->
                            <div v-if="selectedImage.items && selectedImage.items.length > 0">
                                <label class="text-xs font-medium text-gray-700">關聯的物品</label>
                                <div class="mt-1 space-y-1.5">
                                    <router-link
                                        v-for="item in selectedImage.items"
                                        :key="item.id"
                                        :to="`/items/${item.short_id}`"
                                        class="block p-2 bg-gray-50 rounded hover:bg-gray-100 transition"
                                    >
                                        <div class="font-medium text-sm text-gray-900">{{ item.name }}</div>
                                        <div class="text-xs text-gray-500">{{ item.short_id }}</div>
                                    </router-link>
                                </div>
                            </div>
                            <div v-else>
                                <label class="text-xs font-medium text-gray-700">關聯的物品</label>
                                <p class="text-xs text-gray-500 mt-0.5">尚未關聯任何物品</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from '../../axios'

const images = ref([])
const loading = ref(false)
const statusFilter = ref('draft')
const hasItemsFilter = ref('')
const perPage = ref(24)
const pagination = ref({
    current_page: 1,
    last_page: 1,
})
const selectedImage = ref(null)
const quota = ref({
    used: 0,
    limit: null,
    is_unlimited: true,
    message: '2026年新年限時開放，不限制多少數量圖片',
    percentage: 0,
})

const fetchImages = async (page = 1) => {
    loading.value = true
    try {
        const params = {
            page,
            per_page: perPage.value,
        }
        if (statusFilter.value) {
            params.status = statusFilter.value
        }
        if (hasItemsFilter.value) {
            params.has_items = hasItemsFilter.value
        }

        const res = await axios.get('/api/media', { params })
        images.value = res.data.data || []
        pagination.value = {
            current_page: res.data.current_page || 1,
            last_page: res.data.last_page || 1,
        }
        
        // 更新配額資訊
        if (res.data.quota) {
            quota.value = res.data.quota
        }
        
    } catch (error) {
        console.error('載入圖片失敗:', error)
        console.error('錯誤詳情:', error.response?.data || error.message)
    } finally {
        loading.value = false
    }
}

const showImageDetail = async (image) => {
    try {
        const res = await axios.get(`/api/media/${image.uuid}`)
        selectedImage.value = res.data
    } catch (error) {
        console.error('載入圖片詳情失敗:', error)
    }
}

const handleImageError = (event, image) => {
    console.error('圖片載入失敗:', {
        uuid: image.uuid,
        thumb_url: image.thumb_url,
        preview_url: image.preview_url,
        image_path: image.image_path,
        error: event.target.error,
        src: event.target.src
    })
    
    // 如果當前使用的是 thumb_url，嘗試使用 preview_url 作為備用
    if (event.target.src === image.thumb_url && image.preview_url && event.target.src !== image.preview_url) {
        event.target.src = image.preview_url
        return
    }
    
    // 如果都失敗，顯示錯誤占位符
    event.target.src = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="200" height="200"%3E%3Crect fill="%23ddd" width="200" height="200"/%3E%3Ctext fill="%23999" font-family="sans-serif" font-size="14" dy="10.5" font-weight="bold" x="50%25" y="50%25" text-anchor="middle"%3E無法載入圖片%3C/text%3E%3C/svg%3E'
    event.target.style.backgroundColor = '#f3f4f6'
}

const formatDate = (dateString) => {
    if (!dateString) return '-'
    const date = new Date(dateString)
    return date.toLocaleString('zh-TW', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    })
}

const deleteImage = async (image) => {
    if (image.usage_count > 0) {
        alert('無法刪除正在被使用的圖片')
        return
    }

    if (!confirm('確定要刪除這張圖片嗎？此操作無法復原。')) {
        return
    }

    try {
        const res = await axios.delete(`/api/media/${image.uuid}`)
        
        if (res.data.success) {
            // 重新載入圖片列表以確保資料同步，保持在當前頁面
            await fetchImages(pagination.value.current_page)
            
            // 如果是詳情視窗中顯示的圖片，關閉視窗
            if (selectedImage.value && selectedImage.value.uuid === image.uuid) {
                selectedImage.value = null
            }
            
            alert('圖片刪除成功')
        } else {
            alert('刪除失敗：' + res.data.message)
        }
    } catch (error) {
        console.error('刪除圖片失敗:', error)
        alert('刪除失敗：' + (error.response?.data?.message || error.message))
    }
}

onMounted(() => {
    fetchImages()
})
</script>
