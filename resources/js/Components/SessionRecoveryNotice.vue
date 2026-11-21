<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { DialogRoot, DialogPortal, DialogOverlay, DialogContent, DialogTitle, DialogDescription, DialogClose } from 'reka-ui'
import { useSessionRecovery } from '../composables/useSessionRecovery'

const route = useRoute()
const { state, dialogOpen, message, requiresLogin, check, reset } = useSessionRecovery()
const visible = computed(() => state.value !== 'idle' && !['/login', '/register'].includes(route.path))
const title = computed(() => requiresLogin.value ? '請重新登入' : state.value === 'ready' ? '可以再次操作' : '確認安全驗證')
const loginUrl = '/login?reason=session-expired'
const checkButton = ref(null)
let previousFocus
watch(dialogOpen, open => {
    if (open) previousFocus = document.activeElement
})
const restoreFocus = async event => {
    event.preventDefault()
    await nextTick()
    if (previousFocus?.isConnected && previousFocus !== document.body) previousFocus.focus()
    else checkButton.value?.focus()
}
watch(() => route.path, (path, previous) => {
    if (path !== previous) reset()
})
</script>

<template>
    <section v-if="visible && !dialogOpen" class="session-notice" :class="{ 'session-notice-ready': state === 'ready' }" aria-label="登入與安全驗證狀態">
        <div role="status"><strong>{{ title }}</strong><p>{{ message || '正在確認登入狀態，請稍候。' }}</p></div>
        <div class="session-actions">
            <a v-if="requiresLogin" :href="loginUrl" target="_blank" rel="noopener" class="session-primary">在新分頁重新登入</a>
            <button v-if="state !== 'ready'" ref="checkButton" type="button" :disabled="state === 'checking'" @click="check">{{ state === 'checking' ? '確認中…' : requiresLogin ? '我已登入，繼續編輯' : '重新確認' }}</button>
            <button v-else type="button" @click="state = 'idle'">關閉提示</button>
        </div>
    </section>
    <DialogRoot v-if="visible" v-model:open="dialogOpen">
        <DialogPortal>
            <DialogOverlay class="session-overlay" />
            <DialogContent class="session-dialog" @close-auto-focus="restoreFocus">
                <DialogTitle class="session-title">{{ state === 'account-mismatch' ? '請使用原帳號登入' : '登入已過期' }}</DialogTitle>
                <DialogDescription class="session-description">{{ message }}</DialogDescription>
                <div class="session-actions">
                    <a :href="loginUrl" target="_blank" rel="noopener" class="session-primary" @click="dialogOpen = false">在新分頁重新登入</a>
                    <DialogClose class="session-secondary">留在此頁</DialogClose>
                </div>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>

<style scoped>
.session-notice { margin: 20px 0; padding: 20px; border: 1px solid var(--warning); border-radius: var(--radius-panel); background: var(--warning-soft); color: var(--text); }
.session-notice-ready { border-color: var(--success); background: var(--success-soft); }
.session-notice p, .session-description { margin: 8px 0 16px; line-height: 1.6; }
.session-actions { display: flex; flex-wrap: wrap; gap: 12px; }
.session-actions a, .session-actions button { display: inline-flex; justify-content: center; align-items: center; min-height: 44px; padding: 10px 16px; border: 1px solid var(--border-subtle); border-radius: var(--radius-control); font: inherit; text-decoration: none; cursor: pointer; background: var(--surface); color: var(--text); }
.session-actions .session-primary { background: var(--brand); color: var(--on-brand); border-color: var(--brand); }
.session-actions :focus-visible { outline: 2px solid var(--brand-ink); outline-offset: 3px; }
.session-actions button:disabled { opacity: .6; cursor: wait; }
.session-overlay { position: fixed; inset: 0; z-index: 80; background: var(--backdrop); }
.session-dialog { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 81; width: min(480px, calc(100vw - 32px)); max-height: calc(100dvh - 32px); overflow-y: auto; padding: 24px; border: 1px solid var(--border-subtle); border-radius: var(--radius-panel); background: var(--surface); color: var(--text); box-shadow: var(--shadow-dialog); }
.session-title { margin: 0; font-size: 20px; font-weight: 600; }
</style>
