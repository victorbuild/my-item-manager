<script setup>
import '../../../css/aligned-pages.css'
import '../../../css/management-pages.css'
import AppPagination from '../../Components/AppPagination.vue'
import { ref } from 'vue'
import { useCategories } from '../../composables/useCategories'
import { X } from '@lucide/vue'
import axios from '../../axios'
import { useRoute, useRouter } from 'vue-router'
import { appDialog } from '../../utils/appDialog'
import { confirmCategoryDeletion } from '../../utils/categoryDeletion'

const route = useRoute()
const router = useRouter()

const { search, appliedSearch, categories, pagination, loading, error, submitSearch, clearSearch, goToPage, reload } = useCategories(route, router, axios)
const searchInput = ref(null)
const clearSearchInput = () => { clearSearch(); searchInput.value?.focus() }

const deleting = ref(false)
const confirmDelete = async category => {
    if (deleting.value) return
    deleting.value = true
    try {
        if (await confirmCategoryDeletion(axios, appDialog, category)) await reload()
    } finally {
        deleting.value = false
    }
}

</script>

<template>
    <div class="aligned-page management-page categories-page bg-[var(--canvas)] min-h-screen p-4 mx-auto">
        <div class="product-heading mb-6">
            <h1 class="text-2xl font-bold">分類清單</h1>
            <router-link to="/categories/create"
                class="product-secondary">
                新增分類
            </router-link>
        </div>

        <!-- 搜尋框 -->
        <form class="mb-4 flex gap-2" @submit.prevent="submitSearch">
            <div class="product-search-input">
                <input ref="searchInput" v-model="search" type="text" placeholder="搜尋分類名稱" aria-label="搜尋分類名稱"
                    class="w-full p-2 border border-[var(--border-control)] rounded" />
                <button v-if="search" type="button" class="product-search-clear" aria-label="清除搜尋" title="清除搜尋" @click="clearSearchInput">
                    <X :size="20" :stroke-width="1.75" aria-hidden="true" />
                </button>
            </div>
            <button type="submit" class="px-4 py-2 bg-[var(--brand)] text-[var(--on-brand)] rounded">搜尋</button>
        </form>

        <div v-if="loading" class="text-center text-[var(--text-muted)]">載入中...</div>

        <p v-else-if="error" role="alert" class="text-[var(--danger)]">{{ error }} <button type="button" class="underline" @click="reload">重試</button></p>
        <section v-else-if="!categories.length" class="product-empty" aria-labelledby="categories-empty-title">
            <h2 id="categories-empty-title">{{ appliedSearch ? '找不到符合的分類' : '尚未建立分類' }}</h2>
            <p>{{ appliedSearch ? `沒有符合「${appliedSearch}」的分類，請試試其他名稱。` : '建立分類，方便整理產品與物品。' }}</p>
            <button v-if="appliedSearch" type="button" class="product-secondary" @click="clearSearchInput">清除搜尋</button>
            <router-link v-else to="/categories/create" class="product-secondary">新增分類</router-link>
        </section>
        <ul v-else class="space-y-3">
            <li v-for="category in categories" :key="category.id"
                class="category-row bg-[var(--surface)] rounded-xl border border-[var(--border-subtle)]">
                <div>
                    <div class="font-semibold text-base text-[var(--text)] break-words max-w-full">
                        {{ category.name }}
                    </div>
                    <div class="text-sm text-[var(--text-muted)] mt-1">
                        產品數：{{ category.products_count || 0 }} ·
                        物品數：{{ category.items_count || 0 }}
                    </div>
                </div>

                <div class="product-actions">
                    <router-link :to="`/categories/${category.id}`" class="text-[var(--text-muted)] hover:text-[var(--text)]">
                        查看</router-link>
                    <router-link :to="`/categories/${category.id}/edit`" class="text-[var(--brand-ink)] hover:text-[var(--brand-ink)]">
                        編輯</router-link>
                    <button @click="confirmDelete(category)" :disabled="deleting" class="text-[var(--danger)] hover:text-[var(--danger)]">
                        刪除</button>
                </div>
            </li>
        </ul>

        <!-- 分頁按鈕 -->
        <AppPagination v-if="!loading && !error && categories.length && pagination.last_page > 1" :current-page="pagination.current_page" :last-page="pagination.last_page" label="分類分頁" @change="goToPage" />
    </div>
</template>
