<script setup>
import { useRouter } from 'vue-router'
import AppIcon from './AppIcon.vue'

const router = useRouter()
const goBack = () => {
    if (window.history.length > 1) {
        router.back()
    } else {
        router.push('/items')
    }
}
</script>

<template>
    <header class="app-header">
        <nav class="header-content page-layout" aria-label="主要導覽">
            <button type="button" class="back-button" aria-label="返回上一頁" @click="goBack">
                <AppIcon name="back" :size="24" />
            </button>
            <router-link to="/" class="brand-link" aria-label="物品管理首頁">物品管理</router-link>
        </nav>
    </header>
</template>

<style scoped>
.app-header {
    position: sticky;
    top: 0;
    z-index: 40;
    width: 100%;
    background: var(--surface);
    color: var(--text);
    border-bottom: 1px solid var(--border-subtle);
}
.header-content {
    display: grid;
    grid-template-columns: 44px minmax(0, 1fr) 44px;
    align-items: center;
    gap: 8px;
    min-height: 64px;
    padding: 8px 16px;
}
.back-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    padding: 0;
    border: 1px solid transparent;
    border-radius: var(--radius-control);
    background: transparent;
    color: var(--text-muted);
    cursor: pointer;
}
.back-button:hover {
    background: var(--brand-soft);
    color: var(--brand-ink);
}
.back-button:active { background: var(--disabled); }
.brand-link {
    justify-self: center;
    display: inline-flex;
    align-items: center;
    min-height: 44px;
    max-width: 100%;
    padding: 8px;
    border-radius: var(--radius-control);
    color: var(--text);
    font-size: 16px;
    font-weight: 600;
    line-height: 1.5;
    text-align: center;
    overflow-wrap: anywhere;
    text-decoration: none;
}
.brand-link:hover { color: var(--brand-ink); }
.back-button:focus-visible, .brand-link:focus-visible {
    outline: 2px solid var(--brand-ink);
    outline-offset: 2px;
}
@media (min-width: 768px) {
    .header-content { padding-inline: 24px; }
}
</style>
