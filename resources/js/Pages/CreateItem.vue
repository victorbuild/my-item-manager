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
            <!-- 圖片上傳 -->
            <label class="block font-medium">📷 上傳圖片或拍照</label>
            <input
                type="file"
                accept="image/*"
                @change="uploadImage"
                class="w-full"
            />
            <div class="flex flex-wrap gap-2">
                <img v-for="url in imageUrls" :src="url" :key="url" class="w-20 h-20 object-cover rounded border" />
            </div>

            <div>
                <label class="block font-medium">名稱 *</label>
                <input v-model="form.name" type="text" class="w-full p-2 border rounded" required />
            </div>

            <div>
                <label class="block font-medium">描述</label>
                <textarea v-model="form.description" class="w-full p-2 border rounded"></textarea>
            </div>

            <div>
                <label class="block font-medium">位置</label>
                <input v-model="form.location" type="text" class="w-full p-2 border rounded" />
            </div>

            <div>
                <label class="block font-medium">數量</label>
                <input v-model.number="form.quantity" type="number" min="1" class="w-full p-2 border rounded" />
            </div>

            <div>
                <label class="block font-medium">金額</label>
                <input v-model.number="form.price" type="number" step="0.01" class="w-full p-2 border rounded" />
            </div>

            <div>
                <label class="block font-medium">購買日期 *</label>
                <input v-model="form.purchased_at" type="date" class="w-full p-2 border rounded" required />
            </div>

            <div>
                <label class="block font-medium">條碼</label>
                <input v-model="form.barcode" type="text" class="w-full p-2 border rounded" />
                <button type="button" @click="startScanner" class="text-blue-500 underline mt-1">📷 掃描條碼</button>
            </div>

            <!-- 掃描器區塊 -->
            <div v-if="showScanner" class="mt-2">
                <div id="scanner" class="border rounded-md w-full h-64"></div>
                <button type="button" @click="stopScanner" class="text-sm mt-2 text-red-500 underline">✖ 關閉掃描器</button>
            </div>

            <!-- 單品名稱列表 -->
            <div class="space-y-1">
                <label class="block font-medium">🧩 單品名稱（可多筆）</label>
                <div v-for="(unit, index) in units" :key="index" class="flex gap-2">
                    <input v-model="units[index]" type="text" class="flex-1 p-2 border rounded" placeholder="單品名稱" />
                    <button @click="removeUnit(index)" type="button" class="text-red-500">✖</button>
                </div>
                <button type="button" @click="units.push('')" class="text-blue-500 mt-2">＋新增一個單品</button>
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
const isSubmitting = ref(false)
const imageUrls = ref([])
const units = ref([''])

const form = ref({
    name: '',
    description: '',
    location: '',
    quantity: 1,
    price: '',
    purchased_at: '',
    barcode: '',
})

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

const removeUnit = (index) => {
    units.value.splice(index, 1)
}

const submitForm = async (stay = false) => {
    if (isSubmitting.value) return
    isSubmitting.value = true

    const payload = {
        ...form.value,
        image_urls: imageUrls.value,
        units: units.value.filter(u => u.trim() !== '')
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
            units.value = ['']
        } else {
            router.push('/')
        }
    } catch (error) {
        console.error('❌ 儲存失敗', error.response?.data ?? error)
        alert('儲存失敗，請確認欄位填寫正確')
    } finally {
        isSubmitting.value = false
    }
}

let html5QrCode

const startScanner = async () => {
    showScanner.value = true
    await nextTick()
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
