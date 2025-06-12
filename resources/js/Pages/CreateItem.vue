<template>
    <div class="bg-[#f5f5f5] min-h-screen p-4 max-w-2xl mx-auto space-y-6">
        <div class="flex justify-between items-center">
            <h1 class="text-2xl font-bold">📥 新增物品</h1>
            <router-link
                to="/items"
                class="text-sm bg-gray-300 hover:bg-gray-400 px-3 py-1 rounded"
            >
                ⬅ 返回列表
            </router-link>
        </div>

        <!-- 產品選擇 Multiselect -->
        <Multiselect
            v-model="selectedProduct"
            :options="products"
            :searchable="true"
            :custom-label="option => option.name"
            :track-by="'id'"
            placeholder="請輸入或選擇產品"
            :internal-search="false"
            @search-change="searchProduct"
            @select="onProductSelect"
        />

        <!-- 如果選到 isNew，顯示建立表單 -->
        <div v-if="creatingProduct" class="mt-3 space-y-2 bg-white p-4 rounded shadow border">
            <label class="block font-medium">🆕 建立新產品</label>
            <input v-model="newProduct.name" type="text" class="w-full p-2 border rounded" placeholder="產品名稱（必填）" />
            <input v-model="newProduct.brand" type="text" class="w-full p-2 border rounded" placeholder="品牌（可選）" />
            <Multiselect
                v-model="newProduct.category"
                :options="categories"
                :searchable="true"
                :custom-label="opt => opt.name"
                :track-by="'id'"
                placeholder="選擇分類"
                @search-change="onSearch"
                @select="onSelect"
            />
            <input v-model="newProduct.model" type="text" class="w-full p-2 border rounded" placeholder="型號（可選）" />
            <input v-model="newProduct.spec" type="text" class="w-full p-2 border rounded" placeholder="規格（如顏色、容量等）" />

            <!-- 條碼輸入與更新 -->
            <div class="space-y-1">
                <label class="block text-sm font-medium text-gray-700">🔢 條碼</label>
                <div class="flex gap-2 items-center">
                    <input
                        v-model="newProduct.barcode"
                        type="text"
                        placeholder="輸入或掃描條碼"
                        class="flex-1 p-2 border rounded"
                    />
                    <button
                        type="button"
                        @click="startBarcodeScan"
                        class="text-blue-500 underline text-sm"
                    >
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
        <div
            v-if="selectedProduct && !creatingProduct"
            class="bg-white border rounded p-4 mt-4 shadow space-y-1 text-sm text-gray-700"
        >
            <div class="text-lg font-bold">{{ selectedProduct.name }}</div>

            <div v-if="selectedProduct.brand">🏷️ 品牌：{{ selectedProduct.brand }}</div>
            <div v-if="selectedProduct.category">📂 分類：{{ selectedProduct.category.name }}</div>
            <div v-if="selectedProduct.model">🧾 型號：{{ selectedProduct.model }}</div>
            <div v-if="selectedProduct.spec">⚙️ 規格：{{ selectedProduct.spec }}</div>
            <div v-if="selectedProduct.barcode">🔢 條碼：{{ selectedProduct.barcode }}</div>
            <div>📦 目前已有物品數量：{{ selectedProduct.items_count ?? 0 }}</div>
        </div>

        <hr>

        <form @submit.prevent="submitForm(false)" class="space-y-4">
            <!-- 圖片上傳 -->
            <div>
                <label class="block font-medium">📷 上傳圖片或拍照</label>
                <div
                    class="border-2 border-dashed border-gray-400 rounded p-4 text-center bg-white cursor-pointer"
                    @dragover.prevent
                    @drop.prevent="handleDrop"
                >
                    拖拉圖片到這裡上傳或點擊選擇
                    <input
                        type="file"
                        accept="image/*"
                        multiple
                        class="hidden"
                        ref="fileInput"
                        @change="handleFileSelect"
                    />
                    <button type="button" @click="fileInput.click()" class="ml-2 underline text-blue-500">選擇圖片</button>
                </div>

                <div class="flex flex-wrap gap-2 mt-2">
                    <div
                        v-for="(item, index) in uploadList"
                        :key="item.id"
                        class="relative w-20 h-20 border rounded overflow-hidden"
                        :class="{ 'opacity-50': item.status !== 'done' }"
                    >
                        <img :src="item.preview" class="w-full h-full object-cover" :alt="`${form.name || '未命名物品'} - 預覽圖片 ${index + 1}`" />

                        <div v-if="item.status === 'uploading'" class="absolute bottom-0 left-0 w-full h-2 bg-gray-200">
                            <div class="bg-blue-500 h-full" :style="{ width: item.progress + '%' }"></div>
                        </div>

                        <div
                            v-if="item.status === 'done'"
                            class="absolute top-0 right-0 bg-green-500 text-white text-xs px-1"
                        >✅</div>
                        <div
                            v-else-if="item.status === 'error'"
                            class="absolute top-0 right-0 bg-red-500 text-white text-xs px-1"
                        >❌</div>

                        <button
                            @click="removeImage(index)"
                            class="absolute -top-1 -left-1 bg-black text-white rounded-full w-5 h-5 text-xs"
                        >×</button>
                    </div>
                </div>
            </div>

            <div>
                <label class="block font-medium">名稱 *</label>
                <input v-model="form.name" type="text" class="w-full p-2 border rounded" required/>
            </div>

            <div>
                <label class="block font-medium">描述</label>
                <textarea v-model="form.description" class="w-full p-2 border rounded"></textarea>
            </div>

            <div>
                <label class="block font-medium">位置</label>
                <input v-model="form.location" type="text" class="w-full p-2 border rounded"/>
            </div>

            <div>
                <label class="block font-medium">數量</label>
                <input v-model.number="form.quantity" type="number" min="1" class="w-full p-2 border rounded"/>
            </div>

            <div>
                <label class="block font-medium">金額</label>
                <input v-model.number="form.price" type="number" step="0.01" class="w-full p-2 border rounded"/>
            </div>

            <div>
                <label class="block font-medium">💰 購買日期 *</label>
                <input v-model="form.purchased_at" type="date" class="w-full p-2 border rounded" required/>
            </div>

            <div>
                <label class="block font-medium">📦 到貨日期</label>
                <input v-model="form.received_at" type="date" class="w-full p-2 border rounded" />
            </div>

            <div>
                <label class="block font-medium">🚀 開始使用日期</label>
                <input v-model="form.used_at" type="date" class="w-full p-2 border rounded" />
            </div>

            <div>
                <label class="block font-medium">🗑️ 棄用日期</label>
                <input v-model="form.discarded_at" type="date" class="w-full p-2 border rounded" />
            </div>
            <div>
                <label class="block font-medium">🧊 有效期限</label>
                <input v-model="form.expiration_date" type="date" class="w-full p-2 border rounded" />
            </div>

            <!-- 掃描器區塊 -->
            <div v-if="showScanner" class="mt-2">
                <div id="scanner" class="border rounded-md w-full h-64"></div>
                <button type="button" @click="stopScanner" class="text-sm mt-2 text-red-500 underline">✖ 關閉掃描器
                </button>
            </div>

            <!-- 操作按鈕 -->
            <div class="flex gap-4">
                <button
                    type="submit"
                    class="bg-blue-600 text-white px-4 py-2 rounded shadow disabled:opacity-50"
                    :disabled="isSubmitting"
                >
                    {{ isSubmitting ? '儲存中...' : '儲存' }}
                </button>

                <button
                    type="button"
                    @click="submitForm(true)"
                    class="bg-green-600 text-white px-4 py-2 rounded shadow disabled:opacity-50"
                    :disabled="isSubmitting"
                >
                    {{ isSubmitting ? '儲存中...' : '儲存並繼續新增' }}
                </button>
            </div>
        </form>
    </div>
</template>

<script setup>
import Multiselect from 'vue-multiselect'
import 'vue-multiselect/dist/vue-multiselect.css'
import {ref, onMounted, nextTick, watchEffect} from 'vue'
import {useRouter} from 'vue-router'
import axios from 'axios'
import {Html5Qrcode} from 'html5-qrcode'

const categories = ref([])
const selectedCategory = ref(null)
const searchQuery = ref('')
const creating = ref(false)

const router = useRouter()

const showScanner = ref(false)
const isSubmitting = ref(false)

const fileInput = ref(null)
const uploadList = ref([])
let uploadId = 0

const handleFileSelect = (e) => {
    const files = Array.from(e.target.files)
    prepareUpload(files)
}

const handleDrop = (e) => {
    const files = Array.from(e.dataTransfer.files).filter(file => file.type.startsWith('image/'))
    prepareUpload(files)
}

const prepareUpload = (files) => {
    files.forEach(file => {
        const id = uploadId++
        const preview = URL.createObjectURL(file)
        const item = {
            id,
            file,
            preview,
            progress: 0,
            status: 'waiting', // waiting, uploading, done, error
            url: ''
        }
        uploadList.value.push(item)
    })
    startUploadQueue()
}

const startUploadQueue = async () => {
    for (const item of uploadList.value) {
        if (item.status !== 'waiting') continue
        item.status = 'uploading'

        const formData = new FormData()
        formData.append('image', item.file)

        try {
            const res = await axios.post('/api/upload-temp-image', formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
                onUploadProgress: (e) => {
                    item.progress = Math.round((e.loaded * 100) / e.total)
                }
            })

            item.status = 'done'
            item.url = res.data.url
        } catch (err) {
            item.status = 'error'
            console.error('❌ 上傳失敗', err)
        }
    }
}

const removeImage = (index) => {
    const item = uploadList.value[index]
    URL.revokeObjectURL(item.preview)
    uploadList.value.splice(index, 1)
}

const imageUrls = ref([])
watchEffect(() => {
    imageUrls.value = uploadList.value
        .filter(item => item.status === 'done')
        .map(item => item.url)
})

const selectedProduct = ref(null)
const products = ref([])

const searchProduct = async (query) => {
    try {
        const res = await axios.get('/api/products', { params: { q: query } })
        products.value = res.data.items || res.data // 視 API 結構調整

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


const creatingProduct = ref(false)
const newProduct = ref({
    name: '',
    brand: '',
    category: null
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
            category_id: newProduct.value.category?.id,
            model: newProduct.value.model,
            spec: newProduct.value.spec,
            barcode: newProduct.value.barcode,
        })

        selectedProduct.value = res.data.items?.[0]
        creatingProduct.value = false
    } catch (e) {
        alert('❌ 建立產品失敗，請確認欄位是否正確')
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
const onSearch = async (query) => {
    searchQuery.value = query
    // 這邊呼叫 GET API 搜尋
    try {
        const res = await axios.get('/api/categories', {params: {q: query}})
        categories.value = res.data

        // 如果沒有完全相符的分類，加入「虛擬新增」選項
        if (!categories.value.find(c => c.name === query)) {
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

const onSelect = async (option) => {
    if (option.isNew) {
        // 建立新分類
        try {
            creating.value = true
            const res = await axios.post('/api/categories', {name: option._rawName})
            selectedCategory.value = res.data
            await onSearch('') // 重新拉取分類清單
        } catch (e) {
            alert('新增分類失敗')
        } finally {
            creating.value = false
        }
    } else {
        selectedCategory.value = option
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
        categories.value = res.data
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
        const res = await axios.post('/api/upload-temp-image', formData, {
            headers: {'Content-Type': 'multipart/form-data'},
        })

        imageUrls.value.push(res.data.url)
    } catch (error) {
        console.error('❌ 上傳失敗', error.response?.data ?? error)
        alert('上傳失敗，請檢查檔案格式或大小')
    }
}

const submitForm = async (stay = false) => {
    if (isSubmitting.value) return
    isSubmitting.value = true

    const payload = {
        ...form.value,
        image_urls: imageUrls.value,
        category_id: selectedCategory.value?.id ?? null,
        product_id: selectedProduct.value?.id ?? null,
        source_product_id: selectedProduct.value?.is_bundle ? selectedProduct.value.id : null,
    }

    try {
        await axios.post('/api/items', payload)

        if (stay) {
            alert('✅ 已新增成功，可以繼續新增')
            resetForm()
        } else {
            router.push('/items')
        }
    } catch (error) {
        console.error('❌ 儲存失敗', error.response?.data ?? error)
        alert('儲存失敗，請確認欄位填寫正確')
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
    selectedCategory.value = null
    imageUrls.value = []
}

let html5QrCode

const startBarcodeScan = async () => {
    showScanner.value = true
    await nextTick()
    html5QrCode = new Html5Qrcode("scanner")

    try {
        await html5QrCode.start(
            { facingMode: "environment" },
            { fps: 10, qrbox: { width: 250, height: 250 } },
            (decodedText) => {
                stopScanner()
                newProduct.value.barcode = decodedText
                alert('✅ 條碼已填入')
            }
        )
    } catch (err) {
        alert("無法啟動相機掃描")
        console.error(err)
        showScanner.value = false
    }
}

const stopScanner = async () => {
    if (html5QrCode) {
        await html5QrCode.stop()
        html5QrCode.clear()
        html5QrCode = null
    }
    showScanner.value = false
}
</script>
