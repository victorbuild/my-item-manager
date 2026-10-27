import { onBeforeUnmount, onMounted, ref } from 'vue'
import Sortable from 'sortablejs'
import { orderPhotosByIds } from '../utils/photoOrder.js'

export const useSortablePhotos = (photos, openPreview) => {
    const photoGrid = ref(null)
    const reorderNotice = ref('')
    let sortable
    let suppressClickUntil = 0
    let dragging = false
    let selected = false
    const announce = id => {
        const position = photos.value.findIndex(photo => String(photo.id) === String(id))
        if (position >= 0) reorderNotice.value = `圖片已移至第 ${position + 1} 張。`
    }
    const onPreviewClick = (photo, event) => {
        if (event.detail === 0 || (!dragging && Date.now() >= suppressClickUntil)) openPreview(photo)
    }
    const movePhotoWithKeyboard = (photo, event) => {
        if (!['ArrowLeft', 'ArrowRight'].includes(event.key) || dragging) return
        event.preventDefault()
        const from = photos.value.findIndex(candidate => candidate.id === photo.id)
        const to = from + (event.key === 'ArrowLeft' ? -1 : 1)
        if (from < 0 || to < 0 || to >= photos.value.length) return
        photos.value.splice(from, 1)
        photos.value.splice(to, 0, photo)
        announce(photo.id)
    }
    onMounted(() => {
        if (!photoGrid.value) return
        sortable = Sortable.create(photoGrid.value, {
            draggable: '.photo-tile',
            handle: '.photo-preview-trigger',
            filter: '.photo-remove, .photo-retry, .photo-add',
            preventOnFilter: false,
            dataIdAttr: 'data-photo-id',
            delay: 350,
            delayOnTouchOnly: true,
            touchStartThreshold: 5,
            fallbackTolerance: 4,
            forceFallback: true,
            fallbackOnBody: true,
            animation: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 180,
            chosenClass: 'photo-sortable-chosen',
            ghostClass: 'photo-sortable-ghost',
            fallbackClass: 'photo-sortable-fallback',
            onChoose: event => { selected = event.originalEvent?.pointerType === 'touch' || Boolean(event.originalEvent?.type?.startsWith('touch')) },
            onStart: () => { dragging = true },
            onUnchoose: () => {
                if (selected || dragging) suppressClickUntil = Date.now() + 500
                selected = false
            },
            onEnd: event => {
                const ids = sortable.toArray()
                // Sortable 先移動 DOM；還原後交由 Vue 更新 keyed 清單，避免兩者重複搬動。
                const root = photoGrid.value
                const tiles = Array.from(root.children).filter(node => node.classList.contains('photo-tile'))
                const byId = new Map(tiles.map(node => [node.dataset.photoId, node]))
                const anchor = root.querySelector('.photo-add, input[type=file]')
                for (const photo of photos.value) {
                    const tile = byId.get(String(photo.id))
                    if (tile) root.insertBefore(tile, anchor)
                }
                photos.value = orderPhotosByIds(photos.value, ids)
                announce(event.item.dataset.photoId)
                dragging = false
                suppressClickUntil = Date.now() + 500
            },
        })
    })
    onBeforeUnmount(() => { sortable?.destroy(); sortable = null })
    return { photoGrid, reorderNotice, onPreviewClick, movePhotoWithKeyboard }
}
