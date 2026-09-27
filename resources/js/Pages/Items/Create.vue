<template>
    <div class="bg-[#f5f5f5] min-h-screen p-4 max-w-2xl mx-auto space-y-6">
        <div class="flex justify-between items-center">
            <h1 class="text-2xl font-bold">📥 新增物品</h1>
            <router-link to="/items" class="text-sm bg-gray-300 hover:bg-gray-400 px-3 py-1 rounded">
                ⬅ 返回列表
            </router-link>
        </div>

        <!-- 產品選擇 Multiselect -->
        <div class="space-y-2">
            <label class="block font-medium text-gray-700">📦 產品關聯（可選）</label>
            <Multiselect v-model="selectedProduct" :options="products" :searchable="true"
                :custom-label="option => option.name" :track-by="'id'" placeholder="請輸入或選擇產品（可選）"
                :internal-search="false" @search-change="searchProduct" @select="onProductSelect" />
            <p class="text-sm text-gray-500">選擇產品可以幫助您更好地管理物品，但這不是必填的</p>
        </div>

        <!-- 如果選到 isNew，顯示建立表單 -->
        <div v-if="creatingProduct" class="mt-3 space-y-2 bg-white p-4 rounded shadow border">
            <label class="block font-medium">🆕 建立新產品</label>
            <input v-model="newProduct.name" type="text" class="w-full p-2 border rounded" placeholder="產品名稱（必填）" />
            <input v-model="newProduct.brand" type="text" class="w-full p-2 border rounded" placeholder="品牌（可選）" />
            <div class="space-y-2">
                <Multiselect v-model="newProduct.category" :options="categories" :searchable="true"
                    :custom-label="opt => opt.name" :track-by="'id'" placeholder="選擇分類" 
                    :allow-empty="true" :close-on-select="true"
                    @search-change="onSearch" @select="onSelect" />
            </div>
            <input v-model="newProduct.model" type="text" class="w-full p-2 border rounded" placeholder="型號（可選）" />
            <input v-model="newProduct.spec" type="text" class="w-full p-2 border rounded" placeholder="規格（如顏色、容量等）" />

            <!-- 條碼輸入與更新 -->
            <div class="space-y-1">
                <label class="block text-sm font-medium text-gray-700">🔢 條碼</label>
                <div class="flex gap-2 items-center">
                    <input v-model="newProduct.barcode" type="text" placeholder="輸入或掃描條碼"
                        class="flex-1 p-2 border rounded" />
                    <button type="button" @click="startBarcodeScan('productBarcode')" 
                        class="text-blue-600 hover:text-blue-800 underline text-sm whitespace-nowrap">
                        📷 掃描
                    </button>
                </div>
            </div>

            <div class="flex gap-4">
                <button @click="confirmCreateProduct" class="bg-blue-600 text-white px-4 py-1 rounded">✅ 建立</button>
                <button @click="cancelCreateProduct" class="text-gray-500 underline">取消</button>
            </div>
        </div>

        <!-- 成功選到產品後顯示卡片 -->
        <div v-if="selectedProduct && !creatingProduct"
            class="bg-white border rounded p-4 mt-4 shadow space-y-1 text-sm text-gray-700">
            <div class="text-lg font-bold">{{ selectedProduct.name }}</div>

            <div v-if="selectedProduct.brand">🏷️ 品牌：{{ selectedProduct.brand }}</div>
            <div v-if="selectedProduct.category">📂 分類：{{ selectedProduct.category.name }}</div>
            <div v-if="selectedProduct.model">🧾 型號：{{ selectedProduct.model }}</div>
            <div v-if="selectedProduct.spec">⚙️ 規格：{{ selectedProduct.spec }}</div>
            <div v-if="selectedProduct.barcode">🔢 條碼：{{ selectedProduct.barcode }}</div>
            <div>📦 目前已有物品數量：{{ selectedProduct.owned_items_count ?? 0 }}</div>
        </div>

        <form @submit.prevent="submitForm(false)" class="space-y-4">
            <!-- 圖片上傳 -->
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="block font-medium">
                        圖片
                        <span class="ml-1 text-sm text-gray-500 align-middle">（{{ uploadList.length }}/9）</span>
                    </label>
                    <button type="button" @click="showMediaLibrary = true" 
                        class="text-sm bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded">
                        🖼️ 從媒體櫃選擇
                    </button>
                </div>
                <p v-if="formErrors.images" class="text-sm text-red-500 mt-1 mb-2">{{ formErrors.images }}</p>
                <div class="grid grid-cols-4 gap-2 mt-2">
                    <div v-for="(item, index) in uploadList" :key="item.id"
                        class="relative aspect-square border border-gray-300 rounded bg-white overflow-visible"
                        :class="{ 'opacity-50': item.status !== 'done' }">
                        <img v-if="item.preview" :src="item.preview" class="w-full h-full object-contain"
                            :alt="`${form.name || '未命名物品'} - 預覽圖片 ${index + 1}`" />
                        <div v-else class="w-full h-full flex items-center justify-center px-2 text-center text-xs text-gray-500">
                            正在準備圖片…
                        </div>
                        <button type="button" @click="removeImage(index)"
                            class="absolute top-0 right-0 bg-gray-500 rounded-full w-4 h-4 flex items-center justify-center shadow"
                            style="transform: translate(50%,-50%); z-index:10">
                            <span class="text-xs font-bold text-white leading-none">×</span>
                        </button>
                        <div v-if="item.status === 'uploading'" class="absolute bottom-0 left-0 w-full h-2 bg-gray-200">
                            <div class="bg-blue-500 h-full" :style="{ width: item.progress + '%' }"></div>
                        </div>
                        <div v-else-if="item.status === 'error'"
                            class="absolute top-1 right-1 bg-red-500 text-white text-xs px-1 rounded">❌</div>
                    </div>
                    <!-- +加入照片按鈕（灰色系，與 input 風格一致） -->
                    <div v-if="uploadList.length < 9"
                        class="relative aspect-square border-2 border-dashed border-gray-300 flex items-center justify-center cursor-pointer bg-white"
                        @click="fileInput.click()" @dragover.prevent @drop.prevent="handleDrop">
                        <span class="text-gray-400 text-sm">+ 加入照片</span>
                        <input type="file" accept="image/*,.dng,.heic,.heif" multiple class="hidden" ref="fileInput"
                            @change="handleFileSelect" />
                    </div>
                </div>
            </div>

            <!-- 媒體櫃選擇 Modal -->
            <div v-if="showMediaLibrary" 
                class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
                @click.self="showMediaLibrary = false">
                <div class="bg-white rounded-lg max-w-4xl w-full max-h-[90vh] overflow-y-auto">
                    <div class="p-4">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-xl font-bold">選擇圖片（媒體櫃）</h2>
                            <button @click="showMediaLibrary = false" class="text-gray-500 hover:text-gray-700 text-2xl">×</button>
                        </div>
                        
                        <div v-if="loadingMediaLibrary" class="text-center py-8">
                            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                            <p class="mt-3 text-sm text-gray-600">載入中...</p>
                        </div>
                        
                        <div v-else-if="mediaLibraryImages.length === 0" class="text-center py-8">
                            <p class="text-gray-600">沒有可用的圖片</p>
                        </div>
                        
                        <div v-else class="grid grid-cols-4 gap-2">
                            <div v-for="image in mediaLibraryImages" :key="image.uuid"
                                class="relative aspect-square border-2 rounded cursor-pointer transition-all"
                                :class="selectedMediaImages.includes(image.uuid) 
                                    ? 'border-blue-500 bg-blue-50' 
                                    : 'border-gray-300 hover:border-blue-300'"
                                @click="toggleMediaImage(image)">
                                <img :src="image.thumb_url" :alt="image.image_path" 
                                    class="w-full h-full object-cover rounded" />
                                <div v-if="selectedMediaImages.includes(image.uuid)"
                                    class="absolute top-1 right-1 bg-blue-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-sm">
                                    ✓
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4 flex justify-end gap-2">
                            <button @click="showMediaLibrary = false" 
                                class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded">
                                取消
                            </button>
                            <button @click="addSelectedMediaImages" 
                                class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded">
                                確認選擇（{{ selectedMediaImages.length }}）
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <label class="block font-medium">名稱 <span class="text-red-500">*</span></label>
                <input v-model="form.name" type="text" 
                    :class="['w-full p-2 border rounded', formErrors.name ? 'border-red-500' : '']" 
                    required @keydown.enter.prevent />
                <p v-if="formErrors.name" class="text-sm text-red-500 mt-1">{{ formErrors.name }}</p>
            </div>

            <div>
                <label class="block font-medium">描述</label>
                <textarea v-model="form.description" 
                    :class="['w-full p-2 border rounded', formErrors.description ? 'border-red-500' : '']" 
                    placeholder="可輸入多行描述" rows="4"></textarea>
                <p v-if="formErrors.description" class="text-sm text-red-500 mt-1">{{ formErrors.description }}</p>
            </div>

            <div>
                <label class="block font-medium">位置</label>
                <input v-model="form.location" type="text" 
                    :class="['w-full p-2 border rounded', formErrors.location ? 'border-red-500' : '']" 
                    @keydown.enter.prevent />
                <p v-if="formErrors.location" class="text-sm text-red-500 mt-1">{{ formErrors.location }}</p>
            </div>

            <div>
                <label class="block font-medium">數量</label>
                <input v-model.number="form.quantity" type="number" min="1" 
                    :class="['w-full p-2 border rounded', formErrors.quantity ? 'border-red-500' : '']" />
                <p v-if="formErrors.quantity" class="text-sm text-red-500 mt-1">{{ formErrors.quantity }}</p>
                <p v-else class="text-sm text-gray-500 mt-1">
                    輸入的數量會建立相對應數量的物品（例如填 3 會建立 3 筆物品）
                </p>
            </div>

            <div>
                <label class="block font-medium">單價</label>
                <input v-model.number="form.price" type="number" step="0.01" 
                    :class="['w-full p-2 border rounded', formErrors.price ? 'border-red-500' : '']" 
                    @keydown.enter.prevent />
                <p v-if="formErrors.price" class="text-sm text-red-500 mt-1">{{ formErrors.price }}</p>
            </div>

            <div>
                <label class="block font-medium">💰 購買日期 <span class="text-red-500">*</span></label>
                <input v-model="form.purchased_at" type="date" 
                    :class="['w-full p-2 border rounded', formErrors.purchased_at ? 'border-red-500' : '']" 
                    :max="todayString" required />
                <p v-if="formErrors.purchased_at" class="text-sm text-red-500 mt-1">{{ formErrors.purchased_at }}</p>
            </div>

            <div>
                <label class="block font-medium">📦 到貨日期</label>
                <input v-model="form.received_at" type="date" 
                    :class="['w-full p-2 border rounded', formErrors.received_at ? 'border-red-500' : '']" 
                    :min="form.purchased_at || undefined" 
                    :max="todayString" />
                <p v-if="formErrors.received_at" class="text-sm text-red-500 mt-1">{{ formErrors.received_at }}</p>
            </div>

            <div>
                <label class="block font-medium">🚀 開始使用日期</label>
                <input v-model="form.used_at" type="date" 
                    :class="['w-full p-2 border rounded', formErrors.used_at ? 'border-red-500' : '']" 
                    :min="form.received_at || form.purchased_at || undefined" 
                    :max="todayString" />
                <p v-if="formErrors.used_at" class="text-sm text-red-500 mt-1">{{ formErrors.used_at }}</p>
            </div>

            <div>
                <label class="block font-medium">🗑️ 報廢日期</label>
                <input v-model="form.discarded_at" type="date" 
                    :class="['w-full p-2 border rounded', formErrors.discarded_at ? 'border-red-500' : '']" 
                    :min="form.used_at || form.received_at || form.purchased_at || undefined" 
                    :max="todayString" />
                <p v-if="formErrors.discarded_at" class="text-sm text-red-500 mt-1">{{ formErrors.discarded_at }}</p>
            </div>
            <div>
                <label class="block font-medium">
                    🧊 有效期限
                    <button @click="showManufactureDateModal = true" type="button"
                        class="text-sm text-blue-500 hover:underline ml-2">
                        （使用製造日期換算）
                    </button>
                </label>
                <input v-model="form.expiration_date" type="date" 
                    :class="['w-full p-2 border rounded', formErrors.expiration_date ? 'border-red-500' : '']" />
                <p v-if="formErrors.expiration_date" class="text-sm text-red-500 mt-1">{{ formErrors.expiration_date }}</p>
            </div>

            <!-- 製造日期換算模態框 -->
            <div v-if="showManufactureDateModal"
                class="fixed top-0 left-0 w-screen h-screen bg-gray-900/90 flex items-center justify-center z-50">
                <div class="bg-white p-6 rounded-lg w-96 shadow-xl">
                    <h3 class="text-lg font-semibold mb-4">製造日期換算有效期限</h3>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">製造日期</label>
                            <input v-model="manufactureDate" type="date" class="w-full p-2 border rounded" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1">有效期限長度</label>
                            <div class="flex gap-2">
                                <div class="flex-1">
                                    <input v-model.number="expirationValue" type="number" min="0"
                                        class="w-full p-2 border rounded" placeholder="請輸入數字" />
                                </div>
                                <div class="w-24">
                                    <select v-model="expirationUnit" class="w-full p-2 border rounded">
                                        <option value="years">年</option>
                                        <option value="months">月</option>
                                        <option value="days">日</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="text-sm text-gray-600">
                            計算結果：{{ calculatedExpirationDate || '請輸入製造日期' }}
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-2">
                        <button @click="showManufactureDateModal = false"
                            class="px-4 py-2 border rounded hover:bg-gray-100">
                            取消
                        </button>
                        <button @click="applyCalculatedDate"
                            class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600"
                            :disabled="!calculatedExpirationDate">
                            套用
                        </button>
                    </div>
                </div>
            </div>

            <!-- 全螢幕掃描器模態框 -->
            <div v-if="showScanner" 
                class="fixed inset-0 z-50 bg-black overflow-hidden scanner-container">
                <div class="w-full h-full relative">
                    <div id="scanner" class="scanner-fullscreen"></div>
                    <!-- 自定義掃描框指示器 -->
                    <div class="absolute inset-0 flex items-center justify-center pointer-events-none z-10">
                        <div class="border-2 border-white rounded-lg" 
                            style="width: 80%; max-width: 500px; aspect-ratio: 2.5/1; position: relative;">
                            <!-- 四個角的指示器 -->
                            <div class="absolute top-0 left-0 w-8 h-8 border-t-4 border-l-4 border-green-400 rounded-tl-lg"></div>
                            <div class="absolute top-0 right-0 w-8 h-8 border-t-4 border-r-4 border-green-400 rounded-tr-lg"></div>
                            <div class="absolute bottom-0 left-0 w-8 h-8 border-b-4 border-l-4 border-green-400 rounded-bl-lg"></div>
                            <div class="absolute bottom-0 right-0 w-8 h-8 border-b-4 border-r-4 border-green-400 rounded-br-lg"></div>
                            <!-- 檢測到條碼時的綠色覆蓋層 -->
                            <div v-if="barcodeDetected" 
                                class="absolute inset-0 bg-green-400/30 rounded-lg flex items-center justify-center">
                                <div class="bg-green-500 text-white px-4 py-2 rounded-lg font-semibold">
                                    ✓ 已檢測到條碼
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="absolute top-4 right-4 flex gap-2 z-20">
                        <button type="button" @click="stopScanner" 
                            class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 shadow-lg">
                            ✖ 關閉
                        </button>
                    </div>
                    <div class="absolute bottom-4 left-1/2 transform -translate-x-1/2 text-white text-center z-20">
                        <p class="text-lg font-semibold mb-2">將條碼對準掃描框</p>
                        <p class="text-sm text-gray-300">請保持條碼水平對齊</p>
                    </div>
                </div>
            </div>

            <!-- 操作按鈕 -->
            <div class="flex gap-4">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded shadow disabled:opacity-50"
                    :disabled="isSubmitting">
                    {{ isSubmitting ? '儲存中...' : '儲存' }}
                </button>

                <button type="button" @click="submitForm(true)"
                    class="bg-green-600 text-white px-4 py-2 rounded shadow disabled:opacity-50"
                    :disabled="isSubmitting">
                    {{ isSubmitting ? '儲存中...' : '儲存並繼續新增' }}
                </button>
            </div>
        </form>
    </div>
</template>

<script setup>
import Multiselect from 'vue-multiselect'
import 'vue-multiselect/dist/vue-multiselect.css'
import { ref, onMounted, nextTick, watchEffect, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import axios from '../../axios'
import { Html5Qrcode } from 'html5-qrcode'
import Swal from 'sweetalert2'
import { isDngFile, isSupportedImageFile, prepareImageForUpload } from '../../utils/imageUpload'

const categories = ref([])
const selectedCategory = ref(null)
const searchQuery = ref('')
const creating = ref(false)
const creatingCategory = ref(false)

const router = useRouter()

// 今天的日期字串（用於 max 屬性）
const todayString = new Date().toISOString().split('T')[0]

const showScanner = ref(false)
const isSubmitting = ref(false)
const scanTarget = ref(null) // 記錄掃描目標：'productBarcode' 或 'itemBarcode'
const barcodeDetected = ref(false) // 是否檢測到條碼

const fileInput = ref(null)
const uploadList = ref([])
let uploadId = 0

// 媒體櫃相關
const showMediaLibrary = ref(false)
const mediaLibraryImages = ref([])
const loadingMediaLibrary = ref(false)
const selectedMediaImages = ref([])

const handleFileSelect = async (e) => {
    const files = Array.from(e.target.files)
    const maxImages = 9

    if (uploadList.value.length + files.length > maxImages) {
        alert(`最多只能上傳 ${maxImages} 張圖片，目前已上傳 ${uploadList.value.length} 張`)
        return
    }

    await prepareUpload(files)
    e.target.value = ''
}

const handleDrop = async (e) => {
    const files = Array.from(e.dataTransfer.files)
        .filter(isSupportedImageFile)
        .sort((a, b) => a.name.localeCompare(b.name, 'zh-TW', {
            numeric: true,
            sensitivity: 'base',
        }))
    const maxImages = 9

    if (uploadList.value.length + files.length > maxImages) {
        alert(`最多只能上傳 ${maxImages} 張圖片，目前已上傳 ${uploadList.value.length} 張`)
        return
    }

    await prepareUpload(files)
}

const prepareUpload = async (files) => {
    for (const sourceFile of files) {
        const id = uploadId++
        const item = {
            id,
            file: null,
            preview: '',
            progress: 0,
            status: 'preparing',
            url: '',
            thumb_url: '',
            preview_url: ''
        }
        uploadList.value.push(item)

        try {
            item.file = await prepareImageForUpload(sourceFile)
            item.preview = URL.createObjectURL(item.file)
            item.status = 'waiting'
            item.convertedFromDng = isDngFile(sourceFile)
        } catch (error) {
            uploadList.value = uploadList.value.filter(upload => upload.id !== id)
            console.error('圖片準備失敗', error)
            alert(`${sourceFile.name} 無法處理：${error.message}`)
        }
    }
    startUploadQueue()
}

const startUploadQueue = async () => {
    for (const item of uploadList.value) {
        if (item.status !== 'waiting') continue
        item.status = 'uploading'
        const formData = new FormData()
        formData.append('image', item.file)
        try {
            const res = await axios.post('/api/item-images', formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
                onUploadProgress: (e) => {
                    item.progress = Math.round((e.loaded * 100) / e.total)
                }
            })
            item.status = 'done'
            // 根據 API 回傳格式：{ success: true, message: "...", data: { uuid: "...", ... } }
            item.uuid = res.data.data.uuid
            item.statusFromApi = 'new'
            item.file = null
        } catch (err) {
            item.status = 'error'
            console.error('❌ 上傳失敗', err)
        }
    }
}

// 媒體櫃相關函數
const loadMediaLibrary = async () => {
    loadingMediaLibrary.value = true
    try {
        const res = await axios.get('/api/media/unused', { params: { per_page: 100 } })
        mediaLibraryImages.value = res.data.data || []
    } catch (error) {
        console.error('載入媒體櫃失敗:', error)
        alert('載入媒體櫃失敗')
    } finally {
        loadingMediaLibrary.value = false
    }
}

const toggleMediaImage = (image) => {
    const index = selectedMediaImages.value.indexOf(image.uuid)
    if (index > -1) {
        selectedMediaImages.value.splice(index, 1)
    } else {
        if (uploadList.value.length + selectedMediaImages.value.length < 9) {
            selectedMediaImages.value.push(image.uuid)
        } else {
            alert('最多只能選擇 9 張圖片')
        }
    }
}

const addSelectedMediaImages = () => {
    if (uploadList.value.length + selectedMediaImages.value.length > 9) {
        alert('最多只能選擇 9 張圖片')
        return
    }
    
    selectedMediaImages.value.forEach(uuid => {
        const image = mediaLibraryImages.value.find(img => img.uuid === uuid)
        if (image) {
            const id = uploadId++
            uploadList.value.push({
                id,
                file: null,
                preview: image.preview_url,
                progress: 100,
                status: 'done',
                url: image.preview_url,
                thumb_url: image.thumb_url,
                preview_url: image.preview_url,
                uuid: image.uuid,
                statusFromApi: 'new'
            })
        }
    })
    
    selectedMediaImages.value = []
    showMediaLibrary.value = false
}

// 監聽媒體櫃 modal 打開
watch(showMediaLibrary, (newVal) => {
    if (newVal && mediaLibraryImages.value.length === 0) {
        loadMediaLibrary()
    }
})

const removeImage = (index) => {
    const item = uploadList.value[index]
    URL.revokeObjectURL(item.preview)
    uploadList.value.splice(index, 1)
}

const getImagesForApi = () => {
    // 回傳簡化後的 uuid, status
    return uploadList.value
        .filter(item => item.status === 'done')
        .map(item => ({
            uuid: item.uuid,
            status: item.statusFromApi,
        }))
}

const selectedProduct = ref(null)
const products = ref([])

// Debounce 工具函數
const debounce = (func, delay) => {
    let timeoutId
    return (...args) => {
        clearTimeout(timeoutId)
        timeoutId = setTimeout(() => func.apply(null, args), delay)
    }
}

// 實際的搜尋函數
const _searchProduct = async (query) => {
    if (!query || query.trim() === '') {
        products.value = []
        return
    }
    
    try {
        const res = await axios.get('/api/products', { params: { q: query } })
        products.value = res.data.data

        if (!products.value.find(p => p.name === query)) {
            products.value.unshift({
                id: '__create__',
                name: `➕ 點選建立新產品：「${query}」`,
                _rawName: query,
                isNew: true,
            })
        }
    } catch (e) {
        console.error('搜尋產品失敗', e)
    }
}

// 使用 debounce 包裝的搜尋函數（500ms 延遲）
const searchProduct = debounce(_searchProduct, 500)


const creatingProduct = ref(false)
const newProduct = ref({
    name: '',
    brand: '',
    category: null,
    model: '',
    spec: '',
    barcode: ''
})

const onProductSelect = (option) => {
    if (option.isNew) {
        creatingProduct.value = true
        newProduct.value.name = option._rawName
    } else {
        selectedProduct.value = option
        creatingProduct.value = false
    }
}

const confirmCreateProduct = async () => {
    try {
        const res = await axios.post('/api/products', {
            name: newProduct.value.name,
            brand: newProduct.value.brand,
            category_id: newProduct.value.category?.id || null,
            model: newProduct.value.model,
            spec: newProduct.value.spec,
            barcode: newProduct.value.barcode,
        })

        selectedProduct.value = res.data.data
        creatingProduct.value = false
        newProduct.value = {
            name: '',
            brand: '',
            category: null,
            model: '',
            spec: '',
            barcode: ''
        }
        
        // 重新搜尋產品列表以更新
        await searchProduct(selectedProduct.value.name)
    } catch (e) {
        console.error('建立產品失敗', e)
        if (e.response?.data?.message) {
            alert(`❌ 建立產品失敗：${e.response.data.message}`)
        } else {
            alert('❌ 建立產品失敗，請確認欄位是否正確')
        }
    }
}


const cancelCreateProduct = () => {
    creatingProduct.value = false
    selectedProduct.value = null
}


const form = ref({
    name: '',
    description: '',
    location: '',
    quantity: 1,
    price: '',
    purchased_at: '',
    received_at: '',
    used_at: '',
    discarded_at: '',
    expiration_date: '',
    barcode: '',
})

// 表單驗證錯誤訊息
const formErrors = ref({})

// 實際的分類搜尋函數
const _searchCategory = async (query) => {
    searchQuery.value = query
    // 這邊呼叫 GET API 搜尋
    try {
        const res = await axios.get('/api/categories', { params: { q: query } })
        categories.value = Array.isArray(res.data?.data) ? res.data.data : []

        // 如果沒有完全相符的分類，加入「新增分類」選項
        if (query && !categories.value.find(c => c.name === query)) {
            categories.value.unshift({
                id: '__create__',
                name: `➕ 點選以建立新分類：「${query}」`,
                _rawName: query,
                isNew: true
            })
        }
    } catch (err) {
        console.error('❌ 搜尋分類失敗', err)
    }
}

// 使用 debounce 包裝的分類搜尋函數（500ms 延遲）
const onSearch = debounce(_searchCategory, 500)

const onSelect = async (option) => {
    if (option && option.isNew) {
        // 顯示確認對話框
        const categoryName = option._rawName || option.name.replace('➕ 點選以建立新分類：「', '').replace('」', '')
        const confirmed = confirm(`是否要新增分類「${categoryName}」？`)
        
        if (confirmed) {
            await createCategory(categoryName)
        } else {
            // 取消選擇，回到未選擇狀態
            newProduct.value.category = null
        }
    } else if (option) {
        newProduct.value.category = option
    }
}

const createCategory = async (categoryName) => {
    if (!categoryName || !categoryName.trim()) {
        alert('請輸入分類名稱')
        return
    }
    
    if (creatingCategory.value) return
    creatingCategory.value = true
    
    try {
        const res = await axios.post('/api/categories', { name: categoryName.trim() })
        const newCategory = res.data.items[0]
        
        // 添加到分類列表
        if (!categories.value.find(c => c.id === newCategory.id)) {
            categories.value.push(newCategory)
        }
        
        // 自動選中新建的分類
        newProduct.value.category = newCategory
    } catch (e) {
        console.error('新增分類失敗', e)
        if (e.response?.data?.message) {
            alert(`❌ 新增分類失敗：${e.response.data.message}`)
        } else {
            alert('❌ 新增分類失敗，請確認分類名稱是否正確')
        }
        // 失敗時清空選擇
        newProduct.value.category = null
    } finally {
        creatingCategory.value = false
    }
}

onMounted(async () => {

    try {
        await axios.get('/api/user') // Laravel Sanctum 預設是這個
        // 如果成功，就不做事
    } catch (error) {
        if (error.response?.status === 401) {
            router.push('/login') // 或使用名稱：{ name: 'Login' }
            return
        }
    }

    form.value.purchased_at = new Date().toISOString().split('T')[0]

    try {
        const res = await axios.get('/api/categories')
        categories.value = Array.isArray(res.data?.data) ? res.data.data : []
    } catch (error) {
        console.error('❌ 讀取分類失敗', error)
    }
})

const uploadImage = async (e) => {
    const file = e.target.files[0]
    if (!file) return

    const formData = new FormData()
    formData.append('image', file)

    try {
        const res = await axios.post('/api/item-images', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        })

        uploadList.value.push({
            id: null,
            file,
            preview: URL.createObjectURL(file),
            progress: 0,
            status: 'done',
            url: res.data.url
        })
    } catch (error) {
        console.error('❌ 上傳失敗', error.response?.data ?? error)
        alert('上傳失敗，請檢查檔案格式或大小')
    }
}

const submitForm = async (stay = false) => {
    if (isSubmitting.value) return
    isSubmitting.value = true

    // 清除之前的錯誤訊息
    formErrors.value = {}

    const images = getImagesForApi()

    const payload = {
        ...form.value,
        images,
        category_id: selectedCategory.value?.id ?? null,
        product_id: selectedProduct.value?.id ?? null,
        source_product_id: selectedProduct.value?.is_bundle ? selectedProduct.value.id : null,
    }

    try {
        await axios.post('/api/items', payload)

        await Swal.fire({
            icon: 'success',
            title: '成功',
            text: '已新增物品',
            confirmButtonText: '確定'
        })

        if (stay) {
            resetForm()
        } else {
            router.push('/items')
        }
    } catch (error) {
        console.error('儲存失敗', error.response?.data ?? error)

        // 處理 422 表單驗證錯誤
        if (error.response?.status === 422 && error.response?.data?.errors) {
            const errors = error.response.data.errors
            
            // 將錯誤訊息對應到表單欄位
            Object.keys(errors).forEach(field => {
                const errorMessages = errors[field]
                formErrors.value[field] = Array.isArray(errorMessages) ? errorMessages[0] : errorMessages
            })

            // 顯示 SweetAlert 提示
            await Swal.fire({
                icon: 'error',
                title: '表單驗證失敗',
                text: '請檢查表單欄位並修正錯誤',
                confirmButtonText: '確定'
            })
        } else {
            // 處理其他錯誤
            let errorMessage = '儲存失敗，請確認欄位填寫正確'
            if (error.response?.data?.message) {
                errorMessage = error.response.data.message
            }

            await Swal.fire({
                icon: 'error',
                title: '錯誤',
                text: errorMessage,
                confirmButtonText: '確定'
            })
        }
    } finally {
        isSubmitting.value = false
    }
}


const resetForm = () => {
    form.value = {
        name: '',
        description: '',
        location: '',
        quantity: 1,
        price: '',
        purchased_at: new Date().toISOString().split('T')[0],
        received_at: '',
        used_at: '',
        discarded_at: '',
        expiration_date: '',
        barcode: '',
    }
    formErrors.value = {}
    selectedCategory.value = null
    selectedProduct.value = null
    creatingProduct.value = false
    uploadList.value = []
}

let html5QrCode

const startBarcodeScan = async (target) => {
    scanTarget.value = target
    barcodeDetected.value = false
    showScanner.value = true
    await nextTick()
    html5QrCode = new Html5Qrcode("scanner")

    try {
        // 計算橫向掃描框尺寸（適合手機直立使用）
        // 使用視窗寬度的 80%，高度為寬度的 40%（2.5:1 的比例）
        const viewportWidth = window.innerWidth
        const viewportHeight = window.innerHeight
        const scanBoxWidth = Math.min(viewportWidth * 0.8, 500)
        const scanBoxHeight = scanBoxWidth * 0.4 // 2.5:1 的比例

        await html5QrCode.start(
            { 
                facingMode: "environment"
            },
            { 
                fps: 10, 
                qrbox: { width: scanBoxWidth, height: scanBoxHeight },
                aspectRatio: 1.0,
                disableFlip: false
            },
            async (decodedText, result) => {
                // 檢測到條碼時顯示視覺反饋
                barcodeDetected.value = true
                
                // 短暫延遲後停止掃描並顯示確認
                setTimeout(async () => {
                    await stopScanner()
                    
                    // 顯示確認對話框
                    const confirmed = confirm(`掃描到的條碼：${decodedText}\n\n是否要使用這個條碼？`)
                    
                if (confirmed) {
                    // 根據目標填入對應的輸入框
                    if (target === 'productBarcode') {
                        newProduct.value.barcode = decodedText
                    }
                }
                }, 500) // 給用戶 0.5 秒看到綠色反饋
            },
            (errorMessage) => {
                // 掃描錯誤時的處理（不顯示錯誤訊息避免干擾）
                barcodeDetected.value = false
            }
        )
    } catch (err) {
        alert("無法啟動相機掃描，請確認瀏覽器權限")
        console.error(err)
        showScanner.value = false
        scanTarget.value = null
        barcodeDetected.value = false
    }
}

const stopScanner = async () => {
    if (html5QrCode) {
        try {
            await html5QrCode.stop()
            html5QrCode.clear()
        } catch (err) {
            console.error('停止掃描器時出錯', err)
        }
        html5QrCode = null
    }
    showScanner.value = false
    scanTarget.value = null
    barcodeDetected.value = false
}

const showManufactureDateModal = ref(false)
const manufactureDate = ref('')
const expirationValue = ref(0)
const expirationUnit = ref('years')

const calculatedExpirationDate = computed(() => {
    if (!manufactureDate.value || !expirationValue.value) return ''

    const date = new Date(manufactureDate.value)

    switch (expirationUnit.value) {
        case 'years':
            date.setFullYear(date.getFullYear() + expirationValue.value)
            break
        case 'months':
            date.setMonth(date.getMonth() + expirationValue.value)
            break
        case 'days':
            date.setDate(date.getDate() + expirationValue.value)
            break
    }

    return date.toISOString().split('T')[0]
})

const applyCalculatedDate = () => {
    if (calculatedExpirationDate.value) {
        form.value.expiration_date = calculatedExpirationDate.value
        showManufactureDateModal.value = false
        // 重置輸入值
        manufactureDate.value = ''
        expirationValue.value = 0
        expirationUnit.value = 'years'
    }
}
</script>

<style scoped>
.scanner-container #scanner {
    position: absolute !important;
    top: 0 !important;
    left: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
}

.scanner-container #scanner video,
.scanner-container #scanner canvas {
    position: absolute !important;
    top: 0 !important;
    left: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    object-fit: cover !important;
}
</style>
