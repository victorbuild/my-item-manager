<script setup>
import { onBeforeUnmount, onMounted, ref, useId, watch } from 'vue'
import AppIcon from './AppIcon.vue'

const props = defineProps({
    modelValue: Boolean,
    title: { type: String, required: true },
    busy: Boolean,
    wide: Boolean,
})
const emit = defineEmits(['update:modelValue'])
const dialog = ref(null)
const titleId = `modal-title-${useId()}`
let previousOverflow
const restoreScroll = () => {
    if (previousOverflow !== undefined) {
        document.body.style.overflow = previousOverflow
        previousOverflow = undefined
    }
}
const syncDialog = () => {
    if (!dialog.value) return
    if (props.modelValue && !dialog.value.open) {
        previousOverflow = document.body.style.overflow
        document.body.style.overflow = 'hidden'
        dialog.value.showModal()
    } else if (!props.modelValue) {
        if (dialog.value.open) dialog.value.close()
        restoreScroll()
    }
}
const requestClose = () => {
    if (!props.busy) emit('update:modelValue', false)
}
const onClose = () => {
    restoreScroll()
    if (props.modelValue) emit('update:modelValue', false)
}
watch(() => props.modelValue, syncDialog, { flush: 'post' })
onMounted(syncDialog)
onBeforeUnmount(() => { dialog.value?.close(); restoreScroll() })
</script>

<template>
    <dialog ref="dialog" closedby="none" :class="['app-modal', { 'app-modal--wide': wide }]" :aria-labelledby="titleId" @cancel.prevent @close="onClose">
        <div class="modal-frame">
            <header class="modal-heading">
                <h2 :id="titleId" class="modal-title" tabindex="-1" autofocus>{{ title }}</h2>
                <button type="button" class="modal-close" :aria-label="`關閉${title}`" :disabled="busy" @click="requestClose"><AppIcon name="close" /></button>
            </header>
            <div class="modal-body" :inert="busy" :aria-busy="busy"><slot /></div>
            <footer v-if="$slots.footer" class="modal-actions"><slot name="footer" /></footer>
        </div>
    </dialog>
</template>

<style scoped>
.app-modal { width: min(600px, calc(100% - 32px)); max-width: none; max-height: calc(100dvh - 32px); margin: auto; padding: 0; border: 1px solid var(--border-subtle); border-radius: var(--radius-panel); background: var(--surface); color: var(--text); overflow: hidden; }
.app-modal--wide { width: min(960px, calc(100% - 32px)); }
.app-modal::backdrop { background: var(--backdrop); }
.modal-frame { display: flex; flex-direction: column; max-height: calc(100dvh - 32px); }
.modal-heading { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 16px 24px; border-bottom: 1px solid var(--border-subtle); flex-shrink: 0; }
h2 { margin: 0; font-size: 18px; font-weight: 600; line-height: 1.5; overflow-wrap: anywhere; }
/* 標題只承接開啟時的閱讀焦點，不是可操作控制項。 */
.modal-title:focus { outline: none; }
.modal-close { display: inline-flex; align-items: center; justify-content: center; width: 44px; height: 44px; padding: 0; flex-shrink: 0; border: 1px solid transparent; border-radius: var(--radius-control); background: transparent; color: var(--text); cursor: pointer; }
.modal-close:hover:not(:disabled) { background: var(--brand-soft); }
.modal-close:focus-visible { outline: 2px solid var(--brand-ink); outline-offset: 2px; }
.modal-close:disabled { background: var(--disabled); cursor: not-allowed; }
.modal-body { min-height: 0; overflow-y: auto; overscroll-behavior: contain; padding: 24px; }
.modal-actions { display: flex; justify-content: flex-end; flex-wrap: wrap; gap: 12px; padding: 16px 24px max(16px, env(safe-area-inset-bottom)); border-top: 1px solid var(--border-subtle); flex-shrink: 0; }
@media (max-width: 767px) {
    .app-modal, .app-modal--wide { width: 100%; height: 100dvh; max-height: none; margin: 0; border: 0; border-radius: 0; }
    .modal-frame { height: 100%; max-height: none; }
    .modal-body { flex: 1; padding: 16px; }
    .modal-heading, .modal-actions { padding-inline: 16px; }
}
</style>
