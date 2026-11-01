<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import axios from '../axios'
import AppIcon from './AppIcon.vue'
import AppButton from './AppButton.vue'
import AppModal from './AppModal.vue'
import { useItemPhotoUploads } from '../composables/useItemPhotoUploads'
import { useSortablePhotos } from '../composables/useSortablePhotos'
import { getAvailableItemMedia, getNewItemMedia, imageUuidKey } from '../utils/itemImageSelection'
import { isSupportedImageFile } from '../utils/imageUpload'

const props = defineProps({ modelValue: { type: Array, required: true }, itemName: { type: String, default: '' }, errorMessage: { type: String, default: '' } })
const emit = defineEmits(['update:modelValue'])
const photos = ref(props.modelValue)
watch(() => props.modelValue, value => { if (value !== photos.value) photos.value = value })
watch(photos, value => emit('update:modelValue', value), { deep: true, flush: 'sync' })
const uploadList = photos
const { imageFeedback, addFiles, retryImage, removeImage, dispose } = useItemPhotoUploads(photos, { client: axios })
const fileInput = ref(null)
const previewPhoto = ref(null)
const { photoGrid, reorderNotice, onPreviewClick, movePhotoWithKeyboard } = useSortablePhotos(photos, photo => { previewPhoto.value = photo })
const showMediaLibrary = ref(false)
const mediaLibraryImages = ref([])
const selectedMediaImages = ref([])
const loadingMediaLibrary = ref(false)
const mediaLibraryError = ref('')
const availableMediaImages = computed(() => getAvailableItemMedia(mediaLibraryImages.value, photos.value))
let active = true
let libraryController
watch(availableMediaImages, images => {
    const ids = new Set(images.map(image => imageUuidKey(image.uuid)))
    selectedMediaImages.value = selectedMediaImages.value.filter(uuid => ids.has(imageUuidKey(uuid)))
})
const loadMediaLibrary = async () => {
    libraryController?.abort()
    const controller = new AbortController()
    libraryController = controller
    loadingMediaLibrary.value = true
    mediaLibraryError.value = ''
    try {
        const response = await axios.get('/api/media/unused', { params: { per_page: 100 }, signal: controller.signal })
        if (active && libraryController === controller) mediaLibraryImages.value = response.data.data || []
    } catch (error) {
        if (active && error.code !== 'ERR_CANCELED') mediaLibraryError.value = '媒體櫃載入失敗，請重試。'
    } finally { if (active && libraryController === controller) loadingMediaLibrary.value = false }
}
watch(showMediaLibrary, open => { if (open) loadMediaLibrary() })
const toggleMediaImage = image => {
    const index = selectedMediaImages.value.indexOf(image.uuid)
    if (index >= 0) selectedMediaImages.value.splice(index, 1)
    else if (photos.value.length + selectedMediaImages.value.length < 9) selectedMediaImages.value.push(image.uuid)
    else imageFeedback.value = '最多只能加入 9 張圖片。'
}
const addSelectedMediaImages = () => {
    const additions = getNewItemMedia(mediaLibraryImages.value, photos.value, selectedMediaImages.value)
    if (photos.value.length + additions.length > 9) { imageFeedback.value = '最多只能加入 9 張圖片。'; return }
    photos.value = [...photos.value, ...additions.map(image => ({
        id: `media-${image.uuid}`, uuid: image.uuid, status: 'done', progress: 100,
        preview: image.preview_url || image.thumb_url, preview_url: image.preview_url, thumb_url: image.thumb_url,
    }))]
    selectedMediaImages.value = []
    showMediaLibrary.value = false
}
const handleFileSelect = async event => {
    const files = Array.from(event.target.files || [])
    event.target.value = ''
    await addFiles(files)
}
const isExternalFileDragging = ref(false)
let dragDepth = 0
const isFileDrag = event => Array.from(event.dataTransfer?.types || []).includes('Files')
const handleDrop = async event => {
    dragDepth = 0
    isExternalFileDragging.value = false
    const files = Array.from(event.dataTransfer?.files || []).filter(isSupportedImageFile)
        .sort((a, b) => a.name.localeCompare(b.name, 'zh-TW', { numeric: true, sensitivity: 'base' }))
    await addFiles(files)
}
const onDragEnter = event => { if (isFileDrag(event)) { event.preventDefault(); dragDepth++; isExternalFileDragging.value = true } }
const onDragOver = event => { if (isFileDrag(event)) { event.preventDefault(); event.dataTransfer.dropEffect = 'copy' } }
const onDragLeave = event => { if (isFileDrag(event)) { dragDepth = Math.max(0, dragDepth - 1); isExternalFileDragging.value = dragDepth > 0 } }
const onDrop = event => { if (isFileDrag(event)) { event.preventDefault(); handleDrop(event) } }
onMounted(() => {
    window.addEventListener('dragenter', onDragEnter)
    window.addEventListener('dragover', onDragOver)
    window.addEventListener('dragleave', onDragLeave)
    window.addEventListener('drop', onDrop)
})
onBeforeUnmount(() => {
    active = false
    libraryController?.abort()
    dispose()
    window.removeEventListener('dragenter', onDragEnter)
    window.removeEventListener('dragover', onDragOver)
    window.removeEventListener('dragleave', onDragLeave)
    window.removeEventListener('drop', onDrop)
})
</script>

<template>
        <section class="item-photos form-section" aria-labelledby="photos-section-title">
            <div class="photo-heading">
                <h2 id="photos-section-title">圖片 <span class="photo-count">（{{ uploadList.length }}/9）</span></h2>
                <AppButton variant="secondary" @click="showMediaLibrary = true">從媒體櫃選擇</AppButton>
            </div>
<div>

                <p v-if="imageFeedback" class="image-feedback" role="status">{{ imageFeedback }}</p>
                <p class="image-help">最多 9 張。照片上傳完成後，按儲存才會關聯到物品；移除只會從本次清單取消。</p>
                <p v-if="errorMessage" class="text-sm text-[var(--danger)] mt-1 mb-2">{{ errorMessage }}</p>
                <p v-if="uploadList.length > 1" class="text-xs text-[var(--text-muted)] mb-2">
                    短按查看大圖，長按拖曳排序。
                </p>
                <p class="sr-only" role="status">{{ reorderNotice }}</p>
                <div ref="photoGrid" class="photo-grid">
                    <div v-for="(item, index) in uploadList" :key="item.id"
                        :data-photo-id="item.id" class="photo-tile relative aspect-square border border-[var(--border-subtle)] rounded bg-[var(--surface)] overflow-visible"
                        draggable="false">
                        <button v-if="item.preview" type="button" class="photo-preview-trigger" draggable="false" @contextmenu.prevent @dragstart.prevent
                            :aria-label="`查看第 ${index + 1} 張大圖，長按或使用左右方向鍵排序`"
                            @click="onPreviewClick(item, $event)" @keydown="movePhotoWithKeyboard(item, $event)">
                            <img :src="item.preview" draggable="false" class="photo-preview w-full h-full object-contain"
                                :class="{ 'photo-preview--pending': ['preparing', 'waiting', 'uploading', 'processing'].includes(item.status) }"
                                :alt="`${itemName || '未命名物品'} - 預覽圖片 ${index + 1}`" />
                        </button>
                        <div v-else class="photo-placeholder" aria-hidden="true"></div>
                        <button type="button" @click.stop="removeImage(index)" @pointerdown.stop
                            class="photo-remove" :aria-label="`移除第 ${index + 1} 張圖片`">
                            <span class="photo-remove-badge"><AppIcon name="close" :size="16" /></span>
                        </button>
                        <div v-if="item.status === 'uploading'" class="absolute bottom-0 left-0 w-full h-1 bg-[var(--disabled)]">
                            <div class="bg-[var(--brand)] h-full" :style="{ width: item.progress + '%' }"></div>
                        </div>
                        <button v-if="item.status === 'error' && item.file" type="button" class="photo-retry" :aria-label="`重試第 ${index + 1} 張：${item.errorMessage}`" :title="item.errorMessage" @click.stop="retryImage(item)">重試上傳</button>
                        <span v-if="['preparing', 'waiting', 'uploading', 'processing'].includes(item.status)" class="photo-state">
                            <span v-if="['preparing', 'processing'].includes(item.status)" class="photo-spinner" aria-hidden="true"></span>
                            <AppIcon v-else :name="item.status === 'waiting' ? 'clock' : 'upload'" :size="20" />
                            <span class="sr-only">{{ item.status === 'uploading' ? `上傳 ${item.progress}%` : item.status === 'processing' ? '處理中' : item.status === 'preparing' ? '準備中' : '等待上傳' }}</span>
                        </span>
                    </div>
                    <!-- +加入照片按鈕（灰色系，與 input 風格一致） -->
                    <button type="button" v-if="uploadList.length < 9"
                        class="photo-add relative aspect-square border-2 border-dashed border-[var(--border-control)] flex items-center justify-center cursor-pointer bg-[var(--surface)]"
                        @click="fileInput.click()" @dragover.prevent @drop.prevent.stop="handleDrop">
                        <span class="text-[var(--text-muted)] text-sm">加入照片</span>
                    </button>
                    <input type="file" accept="image/*,.dng,.heic,.heif" multiple class="hidden" ref="fileInput"
                        aria-label="選擇物品照片" @change="handleFileSelect" />
                </div>
                <ul v-if="uploadList.some(image => image.status === 'error')" class="image-errors" aria-label="照片上傳失敗">
                    <template v-for="(image, index) in uploadList" :key="image.id">
                        <li v-if="image.status === 'error'">第 {{ index + 1 }} 張{{ image.name ? `（${image.name}）` : '' }}：{{ image.errorMessage || '上傳失敗，請重試。' }}</li>
                    </template>
                </ul>
            </div>
            <slot name="after" />
</section>
    <AppModal :model-value="Boolean(previewPhoto)" title="圖片預覽" wide @update:model-value="previewPhoto = null">
        <img v-if="previewPhoto" :src="previewPhoto.preview_url || previewPhoto.url || previewPhoto.preview" :alt="itemName || '物品照片'" class="photo-full-preview" />
    </AppModal>
    <AppModal v-model="showMediaLibrary" title="選擇圖片（媒體櫃）" wide>
        <p class="image-help">已加入本次物品的照片會自動排除，避免重複選取。</p>
        <div v-if="mediaLibraryError" role="alert" class="media-library-error"><p>{{ mediaLibraryError }}</p><AppButton variant="secondary" @click="loadMediaLibrary">重試</AppButton></div>
        <div v-else-if="loadingMediaLibrary" class="text-center py-8">
                            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-[var(--brand)]"></div>
                            <p class="mt-3 text-sm text-[var(--text-muted)]">載入中...</p>
                        </div>
<div v-else-if="availableMediaImages.length === 0" class="text-center py-8">
                            <p class="text-[var(--text-muted)]">目前沒有可加入的圖片，已選照片不會重複顯示。</p>
                        </div>
<div v-else class="grid grid-cols-4 gap-2">
                            <button type="button" v-for="image in availableMediaImages" :key="image.uuid" :aria-pressed="selectedMediaImages.includes(image.uuid)" :aria-label="`選擇圖片 ${image.image_path}`"
                                class="relative aspect-square border-2 rounded cursor-pointer transition-all"
                                :class="selectedMediaImages.includes(image.uuid)
                                    ? 'border-[var(--brand)] bg-[var(--brand-soft)]'
                                    : 'border-[var(--border-control)] hover:border-[var(--brand-ink)]'"
                                @click="toggleMediaImage(image)">
                                <img :src="image.thumb_url" :alt="image.image_path"
                                    class="w-full h-full object-cover rounded" />
                                <div v-if="selectedMediaImages.includes(image.uuid)"
                                    class="absolute top-1 right-1 bg-[var(--brand)] text-[var(--on-brand)] rounded-full w-6 h-6 flex items-center justify-center text-sm"><AppIcon name="check" /></div>
                            </button>
                        </div>
        <template #footer>
            <AppButton variant="secondary" @click="showMediaLibrary = false">取消</AppButton>
            <AppButton :disabled="loadingMediaLibrary || Boolean(mediaLibraryError) || !selectedMediaImages.length" @click="addSelectedMediaImages">確認選擇（{{ selectedMediaImages.length }}）</AppButton>
        </template>
    </AppModal>
        <div v-if="isExternalFileDragging"
            class="fixed inset-0 z-[100] flex items-center justify-center bg-[var(--backdrop)] backdrop-blur-[2px] pointer-events-none">
            <div class="mx-6 w-full max-w-sm rounded-3xl border border-[var(--border-subtle)] bg-[var(--surface)] px-8 py-9 text-center   ring-1 ">
                <div class="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-2xl bg-[var(--brand-soft)] text-[var(--brand-ink)] ring-1 ring-[var(--border-subtle)]">
                    <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5V6.75A2.25 2.25 0 0 1 5.25 4.5h13.5A2.25 2.25 0 0 1 21 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25H7.5M3 16.5l3.22-3.22a2.25 2.25 0 0 1 3.18 0l1.35 1.35 3.72-3.72a2.25 2.25 0 0 1 3.18 0L21 14.25M15.75 8.25h.008v.008h-.008V8.25Z" />
                    </svg>
                </div>
                <p class="text-lg font-semibold tracking-tight text-[var(--text)]">放開以上傳圖片</p>
                <p class="mt-2 text-sm leading-6 text-[var(--text-muted)]">支援一次拖入多張圖片，最多 9 張</p>
            </div>
        </div>
</template>

<style scoped>
.photo-tile .photo-preview-trigger { display: block; width: 100%; height: 100%; min-height: 0; padding: 0; border: 0; border-radius: inherit; background: transparent; overflow: hidden; -webkit-touch-callout: none; user-select: none; -webkit-user-select: none; }
.photo-full-preview { display: block; width: 100%; max-height: 75dvh; object-fit: contain; }
.photo-preview { -webkit-touch-callout: none; user-select: none; -webkit-user-select: none; }
.photo-tile { -webkit-touch-callout: none; user-select: none; -webkit-user-select: none; }
.photo-sortable-chosen { z-index: 20; }
.photo-sortable-chosen .photo-preview-trigger { transform: scale(1.04); opacity: 0.75; box-shadow: var(--shadow-toast); }
.photo-sortable-ghost { opacity: 0.25; background: var(--brand-soft); }
.photo-sortable-ghost .photo-preview-trigger { transform: none; box-shadow: none; }
.photo-sortable-fallback { opacity: 0.8 !important; z-index: 10000 !important; }
.photo-sortable-fallback .photo-preview-trigger { transform: scale(1.05); box-shadow: var(--shadow-dialog); }
.photo-sortable-chosen .photo-remove, .photo-sortable-fallback .photo-remove { visibility: hidden; }
@media (prefers-reduced-motion: reduce) {
    .photo-sortable-chosen .photo-preview-trigger, .photo-sortable-fallback .photo-preview-trigger { transform: none; }
}

.photo-heading, .expiry-heading { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 8px; margin-bottom: 8px; }
.photo-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); column-gap: 8px; row-gap: 24px; margin-top: 12px; }
@media (min-width: 480px) {
    .photo-grid { grid-template-columns: repeat(5, minmax(0, 1fr)); }
}
.photo-count { font-size: 14px; font-weight: 400; color: var(--text-muted); }
.photo-remove { position: absolute; top: 0; right: 0; transform: translate(50%, -50%); z-index: 10; display: inline-flex; align-items: center; justify-content: center; width: 44px; height: 44px; padding: 0; background: transparent; color: var(--text); border: 0; }
.photo-remove-badge { display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border: 2px solid var(--surface); border-radius: 50%; background: var(--border-control); color: var(--on-brand); }
.photo-remove:hover .photo-remove-badge { background: var(--text-muted); }
.photo-preview--pending { opacity: 0.7; }
.photo-placeholder { width: 100%; height: 100%; border-radius: inherit; background: var(--canvas); }
.photo-state { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); display: flex; flex-direction: column; align-items: center; justify-content: center; width: 36px; height: 36px; padding: 0; background: transparent; color: var(--text-muted); font-size: 12px; line-height: 1.5; white-space: nowrap; pointer-events: none; }
.photo-spinner { display: block; width: 20px; height: 20px; border: 2px solid var(--border-subtle); border-top-color: var(--text-muted); border-radius: 50%; animation: photo-spin 0.9s linear infinite; }
@keyframes photo-spin { to { transform: rotate(360deg); } }
@media (prefers-reduced-motion: reduce) { .photo-spinner { animation: none; } }
.photo-retry { position: absolute; bottom: 0; left: 0; right: 0; min-height: 44px; padding: 4px; border: 1px solid var(--danger); background: var(--danger-soft); color: var(--danger); font-size: 12px; }
.image-help, .image-feedback { margin: 0 0 8px; font-size: 14px; line-height: 1.5; color: var(--text-muted); }
.image-errors { margin: 12px 0 0; padding-left: 20px; font-size: 14px; color: var(--danger); overflow-wrap: anywhere; }
.image-feedback { color: var(--brand-ink); }
.media-library-error { color: var(--danger); font-size: 14px; }

.form-section { min-width: 0; padding: 16px; display: grid; gap: 24px; background: var(--surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-panel); }
h2 { margin: 0; font-size: 18px; line-height: 1.5; font-weight: 600; }
a:focus-visible, button:focus-visible { outline: 2px solid var(--brand-ink); outline-offset: 2px; }

</style>
