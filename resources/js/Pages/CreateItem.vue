<template>
    <div class="bg-[#f5f5f5] min-h-screen p-4 max-w-2xl mx-auto space-y-6">
        <div class="flex justify-between items-center">
            <h1 class="text-2xl font-bold">📥 新增物品</h1>
            <router-link
                to="/"
                class="text-sm bg-gray-300 hover:bg-gray-400 px-3 py-1 rounded"
            >
                ⬅ 返回列表
            </router-link>
        </div>

        <form @submit.prevent="submitForm(false)" class="space-y-4">
            <input v-model="form.name" type="text" placeholder="名稱 *" class="w-full p-2 border rounded" required />

            <textarea v-model="form.description" placeholder="描述" class="w-full p-2 border rounded"></textarea>

            <input v-model="form.location" type="text" placeholder="位置" class="w-full p-2 border rounded" />

            <input v-model.number="form.quantity" type="number" placeholder="數量" min="1" class="w-full p-2 border rounded" />

            <input v-model.number="form.price" type="number" step="0.01" placeholder="金額" class="w-full p-2 border rounded" />

            <input v-model="form.purchased_at" type="date" class="w-full p-2 border rounded" required />

            <input v-model="form.barcode" type="text" placeholder="條碼" class="w-full p-2 border rounded" />
            <button type="button" @click="startScanner" class="text-blue-500 underline">📷 掃描條碼</button>

            <!-- 掃描器區塊 -->
            <div v-if="showScanner" class="mt-2">
                <div id="scanner" class="border rounded-md w-full h-64"></div>
                <button type="button" @click="stopScanner" class="text-sm mt-2 text-red-500 underline">✖ 關閉掃描器</button>
            </div>

            <!-- 圖片上傳 -->
            <input type="file" accept="image/*" capture="environment" @change="uploadImage" class="w-full" />
            <div class="flex flex-wrap gap-2">
                <img v-for="url in imageUrls" :src="url" :key="url" class="w-20 h-20 object-cover rounded border" />
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
import { ref, onMounted, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import { Html5Qrcode } from 'html5-qrcode'

const router = useRouter()

const showScanner = ref(false)

// 表單與圖片
const form = ref({
    name: '',
    description: '',
    location: '',
    quantity: 1,
    price: '',
    purchased_at: '',
    barcode: '',
})

const imageUrls = ref([])

// 載入狀態
const isSubmitting = ref(false)

onMounted(() => {
    form.value.purchased_at = new Date().toISOString().split('T')[0]
})

const uploadImage = async (e) => {
    const file = e.target.files[0]
    if (!file) return

    const formData = new FormData()
    formData.append('image', file)

    try {
        const res = await axios.post('/api/upload-temp-image', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
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
        image_urls: imageUrls.value
    }

    try {
        await axios.post('/api/items', payload)

        if (stay) {
            alert('✅ 已新增成功，可以繼續新增')
            form.value = {
                name: '',
                description: '',
                location: '',
                quantity: 1,
                price: '',
                purchased_at: new Date().toISOString().split('T')[0],
                barcode: ''
            }
            imageUrls.value = []
        } else {
            console.log('🎯 跳轉到列表頁')
            router.push('/') // 回首頁
        }
    } catch (error) {
        console.error('❌ 儲存失敗', error.response?.data ?? error)
        alert('儲存失敗，請確認欄位填寫正確')
    } finally {
        isSubmitting.value = false
    }
}

let html5QrCode

// 條碼掃描器
const startScanner = async () => {
    showScanner.value = true

    await nextTick() // ⏳ 等待 DOM 出現 scanner 元素

    html5QrCode = new Html5Qrcode("scanner")

    try {
        await html5QrCode.start(
            { facingMode: "environment" },
            { fps: 10, qrbox: { width: 250, height: 250 } },
            (decodedText) => {
                form.value.barcode = decodedText
                stopScanner()
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

