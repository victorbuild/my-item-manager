<script setup>
import { computed } from 'vue'
import AppButton from './AppButton.vue'
import AuthPasswordInput from './AuthPasswordInput.vue'
import { useAuthForm } from '../composables/useAuthForm'
import '../../css/auth-forms.css'

const props = defineProps({ mode: { type: String, required: true, validator: value => ['login', 'register'].includes(value) } })
const registering = computed(() => props.mode === 'register')
const prefix = `auth-${props.mode}`
const { form, submitting, errors, errorMessage, errorSummary, recaptchaEnabled, labels, fieldErrors, submit } = useAuthForm(props.mode)
const focusField = key => document.getElementById(`${prefix}-${key}`)?.focus()
</script>
<template>
    <main class="auth-page">
        <section class="auth-card" :aria-labelledby="`${prefix}-title`">
            <header class="auth-heading"><h1 :id="`${prefix}-title`">{{ registering ? '建立帳號' : '登入' }}</h1><p>{{ registering ? '建立帳號，開始整理你的物品。' : '登入後繼續管理你的物品與紀錄。' }}</p></header>
            <form @submit.prevent="submit" :aria-busy="submitting">
                <div v-if="errorMessage" ref="errorSummary" class="auth-error-summary" role="alert" tabindex="-1">
                    <h2>請確認後再試一次</h2><p>{{ errorMessage }}</p>
                    <ul v-if="fieldErrors.length"><li v-for="[key, message] in fieldErrors" :key="key"><a :href="`#${prefix}-${key}`" @click.prevent="focusField(key)">{{ labels[key] }}：{{ message }}</a></li></ul>
                </div>
                <fieldset class="auth-fields" :disabled="submitting">
                    <legend class="sr-only">{{ registering ? '註冊資料' : '登入資料' }}</legend>
                    <div v-if="registering" class="auth-field">
                        <label :for="`${prefix}-name`">名稱</label>
                        <input :id="`${prefix}-name`" v-model="form.name" name="name" type="text" autocomplete="nickname" maxlength="60" required :aria-invalid="Boolean(errors.name)" :aria-describedby="errors.name ? `${prefix}-name-error` : undefined" />
                        <p v-if="errors.name" :id="`${prefix}-name-error`" class="auth-error">{{ errors.name }}</p>
                    </div>
                    <div class="auth-field">
                        <label :for="`${prefix}-email`">電子郵件</label>
                        <input :id="`${prefix}-email`" v-model="form.email" name="email" type="email" inputmode="email" autocomplete="username" autocapitalize="none" spellcheck="false" required :aria-invalid="Boolean(errors.email)" :aria-describedby="errors.email ? `${prefix}-email-error` : undefined" />
                        <p v-if="errors.email" :id="`${prefix}-email-error`" class="auth-error">{{ errors.email }}</p>
                    </div>
                    <AuthPasswordInput :id="`${prefix}-password`" v-model="form.password" label="密碼" :autocomplete="registering ? 'new-password' : 'current-password'" :minlength="registering ? 8 : undefined" :hint="registering ? '至少 8 個字元。' : undefined" :error="errors.password" />
                    <AuthPasswordInput v-if="registering" :id="`${prefix}-password_confirmation`" v-model="form.password_confirmation" label="確認密碼" name="password_confirmation" autocomplete="new-password" :error="errors.password_confirmation" />
                    <AppButton type="submit" class="auth-submit" :disabled="submitting">{{ submitting ? (registering ? '建立中…' : '登入中…') : registering ? '建立帳號' : '登入' }}</AppButton>
                </fieldset>
            </form>
            <p class="auth-switch">{{ registering ? '已經有帳號？' : '還沒有帳號？' }} <router-link :to="registering ? '/login' : '/register'">{{ registering ? '前往登入' : '建立帳號' }}</router-link></p>
            <p v-if="recaptchaEnabled" class="auth-legal">本站受 reCAPTCHA 保護，並適用 Google <a href="https://policies.google.com/privacy" target="_blank" rel="noopener noreferrer">隱私權政策</a>與<a href="https://policies.google.com/terms" target="_blank" rel="noopener noreferrer">服務條款</a>。</p>
        </section>
    </main>
</template>
