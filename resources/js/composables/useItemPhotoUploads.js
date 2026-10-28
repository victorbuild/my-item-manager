import { ref } from 'vue'
import { isSupportedImageFile, prepareImageForUpload } from '../utils/imageUpload.js'
import { imageUuidKey } from '../utils/itemImageSelection.js'

export const useItemPhotoUploads = (photos, { client, prepare = prepareImageForUpload, urls = URL } = {}) => {
    const imageFeedback = ref('')
    let nextId = 0
    let running = false
    let active = true
    const controllers = new Map()
    const previews = new Set()
    const release = photo => { if (previews.delete(photo.preview)) urls.revokeObjectURL(photo.preview) }
    const startUploadQueue = async () => {
        if (running || !active) return
        running = true
        try {
            while (active) {
                const photo = photos.value.find(item => item.status === 'waiting')
                if (!photo) break
                photo.status = 'uploading'
                photo.errorMessage = ''
                const controller = new AbortController()
                controllers.set(photo.id, controller)
                const data = new FormData()
                data.append('image', photo.file)
                try {
                    const response = await client.post('/api/item-images', data, {
                        signal: controller.signal,
                        onUploadProgress: event => {
                            if (!event.total || !photos.value.includes(photo)) return
                            photo.progress = Math.min(100, Math.round(event.loaded * 100 / event.total))
                            if (event.loaded >= event.total) photo.status = 'processing'
                        },
                    })
                    if (!active || !photos.value.includes(photo)) continue
                    const uuid = response.data.data.uuid
                    if (photos.value.some(item => item !== photo && imageUuidKey(item.uuid) === imageUuidKey(uuid))) {
                        release(photo)
                        photos.value = photos.value.filter(item => item !== photo)
                        imageFeedback.value = '已略過重複圖片。'
                        continue
                    }
                    photo.uuid = uuid
                    photo.status = 'done'
                    photo.progress = 100
                    photo.file = null
                } catch (error) {
                    if (active && error.code !== 'ERR_CANCELED' && photos.value.includes(photo)) {
                        photo.status = 'error'
                        photo.errorMessage = error.response?.data?.message || '上傳失敗，請重試。'
                    }
                } finally { controllers.delete(photo.id) }
            }
        } finally { running = false }
    }
    const addFiles = async files => {
        imageFeedback.value = ''
        for (const file of files) {
            if (!active) break
            if (!isSupportedImageFile(file)) { imageFeedback.value = '請選擇支援的圖片格式。'; continue }
            if (photos.value.length >= 9) { imageFeedback.value = '最多只能加入 9 張圖片。'; break }
            const id = `upload-${nextId++}`
            photos.value = [...photos.value, { id, name: file.name, status: 'preparing', file: null, preview: '', progress: 0 }]
            const photo = photos.value.find(item => item.id === id)
            try {
                const prepared = await prepare(file)
                if (!active || !photos.value.includes(photo)) continue
                photo.file = prepared
                photo.preview = urls.createObjectURL(prepared)
                previews.add(photo.preview)
                photo.status = 'waiting'
                startUploadQueue()
            } catch (error) {
                if (!active || !photos.value.includes(photo)) continue
                photo.status = 'error'
                photo.errorMessage = error.message || '圖片無法處理，請移除後重新選擇。'
            }
        }
    }
    const removeImage = index => {
        const photo = photos.value[index]
        if (!photo) return
        controllers.get(photo.id)?.abort()
        release(photo)
        photos.value = photos.value.filter(item => item !== photo)
    }
    const retryImage = photo => {
        if (!active || photo.status !== 'error' || !photo.file) return
        photo.progress = 0
        photo.status = 'waiting'
        startUploadQueue()
    }
    const dispose = () => {
        active = false
        controllers.forEach(controller => controller.abort())
        previews.forEach(preview => urls.revokeObjectURL(preview))
        previews.clear()
    }
    return { imageFeedback, addFiles, retryImage, removeImage, dispose }
}
