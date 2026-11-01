<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import AppButton from '../Components/AppButton.vue'
import { useCurrentUser } from '../composables/useCurrentUser'
import '../../css/auth-forms.css'

const route = useRoute(), router = useRouter()
const { load } = useCurrentUser()
const user = ref(null), busy = ref(false), loading = ref(true), verified = ref(false)
const message = ref(''), error = ref(''), remaining = ref(0)
const controller = new AbortController()
let active = true, timer
onBeforeUnmount(() => { active = false; controller.abort(); clearInterval(timer) })
const check = async () => {
    if (busy.value) return
    busy.value = true; error.value = ''
    try {
        const response = await axios.get('/api/user', { signal: controller.signal })
        if (!active) return
        user.value = response.data.data
        verified.value = !!user.value.email_verified_at
        if (verified.value) message.value = '電子郵件已驗證，可以開始管理物品。'
    } catch (failure) {
        if (!active) return
        if (failure.response?.status === 401) await router.replace('/login')
        else error.value = '無法確認驗證狀態，請稍後再試。'
    } finally { if (active) { busy.value = false; loading.value = false } }
}
const send = async () => {
    if (busy.value || remaining.value > 0) return
    busy.value = true; error.value = ''; message.value = ''
    try {
        await axios.get('/sanctum/csrf-cookie', { signal: controller.signal })
        if (!active) return
        await axios.post('/api/email/verification-notification', {}, { signal: controller.signal })
        if (!active) return
        message.value = '驗證信已寄出，請查看收件匣與垃圾郵件。'
        remaining.value = 60
    } catch (failure) {
        if (!active) return
        if (failure.response?.status === 401) { await router.replace('/login'); return }
        error.value = failure.response?.data?.message || '無法寄送驗證信，請稍後再試。'
        remaining.value = Math.max(0, Number(failure.response?.headers?.['retry-after']) || 60)
    } finally { if (active) busy.value = false }
}
onMounted(async () => {
    await check()
    if (active && !verified.value && route.query.invalid) error.value = '連結已失效或與登入帳號不符，請使用正確帳號重新寄送驗證信。'
    if (!active) return
    timer = setInterval(() => { if (remaining.value > 0) remaining.value-- }, 1000)
})
const continueToItems = async () => { await load({ force: true }); if (active) await router.push('/items') }
</script>

<template>
    <main class="auth-page">
        <section class="auth-card" aria-labelledby="verification-title" :aria-busy="busy || loading">
            <header class="auth-heading">
                <h1 id="verification-title">{{ verified ? '驗證完成' : '驗證電子郵件' }}</h1>
                <p v-if="!verified">請登入您的信箱，點選驗證連結後即可使用物品管理功能。</p>
                <p v-if="user" class="verification-email">{{ user.email }}</p>
            </header>
            <p v-if="loading" role="status">正在確認驗證狀態…</p>
            <p v-if="message" role="status" class="verification-message">{{ message }}</p>
            <p v-if="error" role="alert" class="auth-error-summary">{{ error }}</p>
            <div v-if="!loading" class="auth-fields">
                <AppButton v-if="verified" @click="continueToItems">前往物品列表</AppButton>
                <template v-else>
                    <AppButton :disabled="busy || !user || remaining > 0" @click="send">{{ remaining > 0 ? `${remaining} 秒後可重新寄送` : '寄送驗證信' }}</AppButton>
                    <AppButton variant="secondary" :disabled="busy" @click="check">我已驗證，重新確認</AppButton>
                </template>
            </div>
            <p v-if="!verified && !loading" class="auth-legal">連結有效時間為 60 分鐘。未收到信件時，可重新寄送；每分鐘最多一次、每小時最多六次。</p>
        </section>
    </main>
</template>

<style scoped>
.verification-email { overflow-wrap: anywhere; }
.verification-message { margin-bottom: 24px; color: var(--success); line-height: 1.5; }
</style>
