<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import { LogOut } from '@lucide/vue'
import { DropdownMenuRoot, DropdownMenuTrigger, DropdownMenuPortal, DropdownMenuContent, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuItem } from 'reka-ui'
import AppIcon from './AppIcon.vue'
import { useCurrentUser } from '../composables/useCurrentUser'

const route = useRoute(), router = useRouter()
const { user, isLoggedIn, loading, error, load, clear } = useCurrentUser()
const menuOpen = ref(false), signingOut = ref(false), logoutError = ref('')
const showBack = computed(() => !['/', '/login', '/register'].includes(route.path))
const goBack = () => {
    if (router.options.history.state.back) router.back()
    else router.push('/')
}
watch(() => route.path, (path, previous) => {
    menuOpen.value = false
    logoutError.value = ''
    if (signingOut.value) return
    load({ force: ['/login', '/register'].includes(path) || ['/login', '/register'].includes(previous) })
}, { immediate: true })
const logout = async () => {
    if (signingOut.value) return
    signingOut.value = true; logoutError.value = ''
    try {
        // 先讓既有 route guard 決定是否放棄草稿，通過後才結束 session。
        const destination = route.path === '/verify-email' || user.value?.email_verified_at === null ? '/login' : '/'
        if (route.path !== destination && await router.push(destination)) return
        await axios.post('/logout')
        clear()
        try { localStorage.removeItem('loggedIn') } catch { /* 儲存空間不可用不影響伺服器登出。 */ }
        menuOpen.value = false
        await router.push('/login')
    } catch {
        logoutError.value = '登出失敗，請重試。'
    } finally { signingOut.value = false }
}
</script>

<template>
    <header class="app-header">
        <nav class="header-content page-layout" aria-label="主要導覽">
            <div class="header-start">
                <button v-if="showBack" type="button" class="back-button" aria-label="返回上一頁" @click="goBack"><AppIcon name="back" :size="22" /></button>
                <router-link to="/" class="brand-link" aria-label="物品管理首頁">物品管理</router-link>
            </div>
            <div class="header-account" aria-label="會員帳號">
                <span v-if="loading" class="account-loading" role="status">載入帳號…</span>
                <DropdownMenuRoot v-else-if="isLoggedIn" v-model:open="menuOpen">
                    <DropdownMenuTrigger class="account-trigger" :disabled="signingOut" aria-label="開啟會員選單" title="會員選單">
                        <span class="account-avatar"><AppIcon name="user" :size="18" /></span>
                    </DropdownMenuTrigger>
                    <DropdownMenuPortal>
                        <DropdownMenuContent class="app-account-menu" align="end" :side-offset="8" :collision-padding="16">
                            <DropdownMenuLabel class="app-account-menu-label">
                                <span class="app-account-menu-avatar"><AppIcon name="user" :size="18" /></span>
                                <span class="app-account-menu-identity">
                                    <strong>{{ user?.name || '會員' }}</strong>
                                </span>
                            </DropdownMenuLabel>
                            <DropdownMenuSeparator class="app-account-menu-separator" />
                            <DropdownMenuItem class="app-account-menu-item" :disabled="signingOut" @select="logout"><LogOut :size="18" aria-hidden="true" />登出</DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenuPortal>
                </DropdownMenuRoot>
                <button v-else-if="error" class="account-link" type="button" @click="load({ force: true })">重試帳號載入</button>
                <template v-else>
                    <router-link to="/login" class="account-link">登入</router-link>
                    <router-link to="/register" class="account-link">註冊</router-link>
                </template>
            </div>
        </nav>
        <p v-if="logoutError || error" class="header-message page-layout" role="alert">{{ logoutError || error }}<button v-if="logoutError" type="button" class="account-link" :disabled="signingOut" @click="logout">重試登出</button></p>
        <span v-if="signingOut" class="sr-only" role="status">正在處理登出。</span>
    </header>
</template>

<style scoped>
.app-header { position: sticky; top: 0; z-index: 40; width: 100%; background: var(--surface); color: var(--text); border-bottom: 1px solid var(--border-subtle); }
.header-content { display: flex; justify-content: space-between; align-items: center; gap: 8px 16px; min-height: 64px; padding: 8px 16px; }
.header-start, .header-account { display: flex; align-items: center; gap: 8px; min-width: 0; }
.header-start { flex-shrink: 0; }
.header-account { justify-content: flex-end; }
.back-button, .brand-link, .account-link, .account-trigger { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; border: 1px solid transparent; border-radius: var(--radius-control); background: transparent; color: var(--text); text-decoration: none; cursor: pointer; }
.back-button { width: 44px; padding: 0; color: var(--text-muted); }
.brand-link { padding: 8px 0; font-size: 16px; font-weight: 600; white-space: nowrap; }
.account-link { padding: 8px 12px; font-size: 14px; }
.account-trigger { width: 44px; min-width: 44px; height: 44px; padding: 0; border-radius: 50%; }
.account-avatar { display: inline-flex; flex-shrink: 0; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 50%; background: var(--brand-soft); color: var(--brand-ink); }
.account-loading { font-size: 14px; color: var(--text-muted); }
.back-button:hover, .account-link:hover { background: var(--brand-soft); color: var(--brand-ink); }
.brand-link:hover { color: var(--brand-ink); }
.account-trigger:hover .account-avatar, .account-trigger[data-state="open"] .account-avatar { background: var(--brand); color: var(--on-brand); }
.back-button:focus-visible, .brand-link:focus-visible, .account-link:focus-visible, .account-trigger:focus-visible { outline: 2px solid var(--brand-ink); outline-offset: 2px; }
.account-trigger:disabled, .account-link:disabled { color: var(--text-muted); cursor: wait; }
.header-message { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; margin-block: 0; padding: 0 16px 8px; color: var(--danger); font-size: 14px; }
@media (min-width: 768px) { .header-content { padding-inline: 24px; } .header-message { padding-inline: 24px; } }
@media (max-width: 479px) { .account-link { padding-inline: 8px; } }
</style>

<!-- 選單透過 Portal 掛到 body；使用專屬全域類別，不依賴父元件的 scoped 樣式。 -->
<style>
.app-account-menu { z-index: 60; width: 224px; max-width: calc(100vw - 32px); padding: 6px; border: 1px solid var(--border-subtle); border-radius: var(--radius-panel); background: var(--surface); color: var(--text); box-shadow: var(--shadow-dialog); }
.app-account-menu-label { display: flex; align-items: center; gap: 8px; padding: 8px; }
.app-account-menu-avatar { display: inline-flex; flex-shrink: 0; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 50%; background: var(--brand-soft); color: var(--brand-ink); }
.app-account-menu-identity { display: grid; gap: 2px; min-width: 0; }
.app-account-menu-identity strong { color: var(--text); font-size: 14px; line-height: 1.5; font-weight: 600; overflow-wrap: anywhere; }
.app-account-menu-separator { height: 1px; margin: 4px 0; background: var(--border-subtle); }
.app-account-menu-item { display: flex; align-items: center; gap: 8px; min-height: 44px; padding: 8px; border-radius: var(--radius-control); color: var(--text); font-size: 14px; cursor: pointer; }
.app-account-menu-item[data-highlighted] { outline: 2px solid var(--brand-ink); outline-offset: -2px; background: var(--brand-soft); }
</style>
