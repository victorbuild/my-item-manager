<script setup>
import { onBeforeUnmount, ref, useId, watch } from 'vue'
import { DialogContent, DialogDescription, DialogOverlay, DialogPortal, DialogRoot, DialogTitle } from 'reka-ui'
import AppIcon from './AppIcon.vue'

const props = defineProps({
    modelValue: Boolean,
    title: { type: String, required: true },
    description: { type: String, default: '' },
    busy: Boolean,
    wide: Boolean,
    compact: Boolean,
})
const emit = defineEmits(['update:modelValue'])
const heading = ref(null)
const descriptionId = `reka-description-${useId()}`
let returnFocus
watch(() => props.modelValue, open => {
    if (open && typeof document !== 'undefined') returnFocus = document.activeElement
}, { flush: 'sync', immediate: true })
const requestClose = () => {
    if (!props.busy) emit('update:modelValue', false)
}
const focusHeading = event => {
    event.preventDefault()
    heading.value?.focus()
}
const restoreFocus = event => {
    event.preventDefault()
    if (returnFocus?.isConnected) returnFocus.focus()
}
onBeforeUnmount(() => { returnFocus = undefined })
</script>

<template>
    <DialogRoot :open="modelValue" @update:open="value => { if (!value) requestClose() }">
        <DialogPortal>
            <DialogOverlay class="reka-modal-overlay">
                <DialogContent data-reka-modal :class="['reka-modal', { 'reka-modal--wide': wide, 'reka-modal--compact': compact }]" :aria-describedby="description ? descriptionId : undefined" :aria-busy="busy"
                    @interact-outside.prevent @escape-key-down.prevent
                    @open-auto-focus="focusHeading" @close-auto-focus="restoreFocus">
                    <header class="reka-modal-heading">
                        <DialogTitle as-child><h2 ref="heading" tabindex="-1">{{ title }}</h2></DialogTitle>
                        <button type="button" class="reka-modal-close" :aria-label="`關閉${title}`" :disabled="busy" @click="requestClose"><AppIcon name="close" /></button>
                    </header>
                    <DialogDescription v-if="description" :id="descriptionId" class="reka-modal-description">{{ description }}</DialogDescription>
                    <div class="reka-modal-body" :inert="busy"><slot /></div>
                    <footer v-if="$slots.footer" class="reka-modal-actions"><slot name="footer" /></footer>
                </DialogContent>
            </DialogOverlay>
        </DialogPortal>
    </DialogRoot>
</template>

<style scoped>
.reka-modal-overlay { position: fixed; inset: 0; z-index: 100; display: flex; padding: 24px 16px; overflow-y: auto; overscroll-behavior: contain; background: var(--backdrop); }
.reka-modal { position: relative; width: min(600px, 100%); min-width: 0; margin: auto; flex-shrink: 0; border: 1px solid var(--border-subtle); border-radius: var(--radius-panel); background: var(--surface); color: var(--text); }
.reka-modal:focus { outline: none; }
.reka-modal--wide { width: min(960px, 100%); }
.reka-modal-heading { display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 16px 24px; border-bottom: 1px solid var(--border-subtle); }
h2 { margin: 0; font-size: 18px; font-weight: 600; line-height: 1.5; overflow-wrap: anywhere; }
h2:focus { outline: none; }
.reka-modal-close { display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; width: 44px; height: 44px; padding: 0; border: 0; border-radius: var(--radius-control); background: transparent; color: var(--text); cursor: pointer; }
.reka-modal-close:hover:not(:disabled) { background: var(--brand-soft); }
.reka-modal-close:disabled { color: var(--text-muted); cursor: not-allowed; }
.reka-modal-close:focus-visible { outline: 2px solid var(--brand-ink); outline-offset: 2px; }
.reka-modal-body { padding: 24px; min-width: 0; }
.reka-modal-description { margin: 0; padding: 16px 24px 0; color: var(--text-muted); font-size: 14px; line-height: 1.5; }
.reka-modal-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 12px; padding: 16px 24px max(16px, env(safe-area-inset-bottom)); border-top: 1px solid var(--border-subtle); }
.reka-modal--compact { width: min(480px, 100%); border: 0; box-shadow: var(--shadow-dialog); }
.reka-modal--compact .reka-modal-heading { min-height: 56px; padding: 24px 64px 0 24px; border: 0; }
.reka-modal--compact h2 { padding: 0; font-size: 18px; font-weight: 600; line-height: 1.5; }
.reka-modal--compact .reka-modal-close { position: absolute; top: 12px; right: 12px; color: var(--text-muted); }
.reka-modal--compact .reka-modal-close :deep(svg) { width: 18px; height: 18px; }
.reka-modal--compact .reka-modal-description { padding-top: 8px; }
.reka-modal--compact .reka-modal-body { padding: 16px 24px 0; }
.reka-modal--compact .reka-modal-actions { padding: 24px; border: 0; align-items: center; }
.reka-modal--compact .reka-modal-actions :deep(.app-button) { min-height: 44px; padding: 10px 16px; font-size: 14px; line-height: 1.5; }
.reka-modal--compact .reka-modal-actions :deep(.app-button--secondary:not(:disabled)) { border-color: transparent; background: transparent; color: var(--text-muted); }
.reka-modal--compact .reka-modal-actions :deep(.app-button--secondary:hover:not(:disabled)) { background: var(--brand-soft); color: var(--text); }
@media (max-width: 767px) {
    .reka-modal-overlay { padding: 0; }
    .reka-modal, .reka-modal--wide { display: flex; flex-direction: column; width: 100%; min-height: 100dvh; margin: 0; border: 0; border-radius: 0; }
    .reka-modal-heading, .reka-modal-actions { position: sticky; z-index: 1; background: var(--surface); padding-inline: 16px; }
    .reka-modal-heading { top: 0; }
    .reka-modal-actions { bottom: 0; }
    .reka-modal-body { flex: 1; padding: 16px; }
    .reka-modal-description { padding-inline: 16px; }
    .reka-modal--compact { width: calc(100% - 32px); min-height: auto; margin: auto; border-radius: var(--radius-panel); }
    .reka-modal--compact .reka-modal-heading { position: relative; background: transparent; }
    .reka-modal--compact .reka-modal-actions { position: relative; background: transparent; }
    .reka-modal--compact .reka-modal-description { padding-inline: 24px; }
}
</style>
