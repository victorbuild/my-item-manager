<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import AppIcon from './AppIcon.vue'

const props = defineProps({ item: { type: Object, default: null } })
const emit = defineEmits(['close'])
const dialog = ref(null)
let previousOverflow
const fields = computed(() => props.item ? [
    ['單價', props.item.price == null ? null : Number(props.item.price).toLocaleString('zh-TW')],
    ['購買日期', props.item.purchased_at],
    ['到貨日期', props.item.received_at],
    ['開始使用日期', props.item.used_at],
    ['報廢日期', props.item.discarded_at],
] : [])
const syncDialog = () => {
    if (!dialog.value) return
    if (props.item && !dialog.value.open) {
        previousOverflow = document.body.style.overflow
        document.body.style.overflow = 'hidden'
        dialog.value.showModal()
    } else if (!props.item && dialog.value.open) dialog.value.close()
}
const restoreScroll = () => {
    if (previousOverflow !== undefined) {
        document.body.style.overflow = previousOverflow
        previousOverflow = undefined
    }
}
const close = () => emit('close')
const onClose = () => { restoreScroll(); emit('close') }
watch(() => props.item, syncDialog, { flush: 'post' })
onMounted(syncDialog)
onBeforeUnmount(() => { dialog.value?.close(); restoreScroll() })
</script>

<template>
    <Teleport to="body">
        <dialog ref="dialog" class="info-panel" aria-labelledby="item-info-title" @cancel.prevent="close" @close="onClose" @click="($event.target === dialog) && close()">
            <div v-if="item" class="panel-content">
                <header class="panel-heading">
                    <h2 id="item-info-title" tabindex="-1" autofocus>{{ item.name }}</h2>
                    <button type="button" class="close-button" aria-label="關閉物品資訊" @click="close"><AppIcon name="close" /></button>
                </header>
                <div class="panel-body">
                    <dl><div v-for="[label, value] in fields" :key="label"><dt>{{ label }}</dt><dd>{{ value || '未填寫' }}</dd></div></dl>
                </div>
                <footer class="panel-actions">
                    <router-link :to="`/items/${item.short_id}`" class="panel-link" @click="close">前往物品頁</router-link>
                </footer>
            </div>
        </dialog>
    </Teleport>
</template>

<style scoped>
.info-panel { position: fixed; inset: 0; width: min(440px, calc(100% - 32px)); max-width: none; max-height: calc(100dvh - 32px); margin: auto; padding: 0; border: 1px solid var(--border-subtle); border-radius: var(--radius-panel); background: var(--surface); color: var(--text); overflow-y: auto; }
.info-panel::backdrop { background: var(--backdrop); }
.panel-content { display: flex; flex-direction: column; }
.panel-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; padding: 24px 16px 16px; border-bottom: 1px solid var(--border-subtle); }
h2 { margin: 0; font-size: 18px; line-height: 1.5; font-weight: 600; overflow-wrap: anywhere; }
.close-button { display: inline-flex; align-items: center; justify-content: center; width: 44px; height: 44px; flex-shrink: 0; border-radius: var(--radius-control); color: var(--text); background: var(--canvas); border: 1px solid var(--border-control); cursor: pointer; }
.panel-body { flex: 1; min-height: 0; overflow-y: auto; overscroll-behavior: contain; padding: 24px 16px; }
dl { margin: 0; display: grid; gap: 16px; font-size: 16px; line-height: 1.5; }
dl > div { display: grid; grid-template-columns: 100px minmax(0, 1fr); gap: 12px; }
dt { color: var(--text-muted); } dd { margin: 0; overflow-wrap: anywhere; white-space: pre-wrap; }
.panel-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; padding: 16px 16px max(16px, env(safe-area-inset-bottom)); border-top: 1px solid var(--border-subtle); }
.panel-link { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; padding: 10px 12px; border: 1px solid var(--border-control); border-radius: var(--radius-control); color: var(--text); text-decoration: none; font-size: 14px; }
a:focus-visible, button:focus-visible { outline: 2px solid var(--brand-ink); outline-offset: 2px; }
</style>
