import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import { getRecaptchaToken, loadRecaptchaScript } from '../utils/recaptcha'

export const useAuthForm = mode => {
    const router = useRouter()
    const form = reactive({ name: '', email: '', password: '', password_confirmation: '' })
    const submitting = ref(false), errors = ref({}), errorMessage = ref(''), errorSummary = ref(null)
    const recaptchaEnabled = document.querySelector('meta[name="recaptcha-enabled"]')?.content !== 'false'
    let active = true, controller
    onBeforeUnmount(() => { active = false; controller?.abort() })
    const siteKey = import.meta.env.VITE_RECAPTCHA_SITE_KEY
    const labels = { name: '名稱', email: '電子郵件', password: '密碼', password_confirmation: '確認密碼' }
    const fieldErrors = computed(() => Object.entries(errors.value).filter(([key]) => key in labels))
    onMounted(() => { if (recaptchaEnabled) loadRecaptchaScript(siteKey).catch(() => {}) })
    const submit = async () => {
        if (submitting.value) return
        controller = new AbortController()
        submitting.value = true; errors.value = {}; errorMessage.value = ''
        try {
            const token = recaptchaEnabled ? await getRecaptchaToken(siteKey, mode) : null
            if (!active) return
            await axios.get('/sanctum/csrf-cookie', { signal: controller.signal })
            if (!active) return
            const payload = { email: form.email.trim(), password: form.password, recaptcha_token: token }
            if (mode === 'register') Object.assign(payload, { name: form.name.trim(), password_confirmation: form.password_confirmation })
            await axios.post(`/${mode}`, payload, { signal: controller.signal })
            if (!active) return
            try { localStorage.setItem('loggedIn', 'true') } catch { /* session 成功不依賴 localStorage 可用性。 */ }
            form.password = ''; form.password_confirmation = ''
            await router.push('/')
        } catch (failure) {
            if (!active) return
            errors.value = Object.fromEntries(Object.entries(failure.response?.data?.errors || {}).map(([key, value]) => [key, Array.isArray(value) ? value[0] : value]))
            errorMessage.value = errors.value.recaptcha_token || failure.response?.data?.message || (failure.response ? '操作失敗，請稍後重試。' : failure.message?.startsWith('驗證') ? failure.message : '連線失敗，請確認網路後重試。')
            await nextTick()
            errorSummary.value?.focus()
        } finally { submitting.value = false }
    }
    return { form, submitting, errors, errorMessage, errorSummary, recaptchaEnabled, labels, fieldErrors, submit }
}
