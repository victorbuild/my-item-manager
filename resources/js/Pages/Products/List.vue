<script setup>
import '../../../css/aligned-pages.css'
import '../../../css/management-pages.css'
import { appDialog } from '../../utils/appDialog'
import { confirmProductDeletion } from '../../utils/productDeletion'
import { ref } from 'vue'
import { X } from '@lucide/vue'
import { useProducts } from '../../composables/useProducts'
import axios from '../../axios'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()

const { search, appliedSearch, products, pagination, loading, error, submitSearch, clearSearch, goToPage, reload } = useProducts(route, router, axios)

const searchInput = ref(null)
const clearSearchInput = () => {
    clearSearch()
    searchInput.value?.focus()
}

const deleting = ref(false)
const confirmDelete = async (product) => {
    if (deleting.value) return
    deleting.value = true
    try {
        if (await confirmProductDeletion(axios, appDialog, product)) {
            await reload()
        }
    } finally {
        deleting.value = false
    }
}

</script>

<template>
    <div class="aligned-page management-page bg-[var(--canvas)] min-h-screen p-4 mx-auto">
        <div class="product-heading mb-6">
            <h1 class="text-2xl font-bold">產品清單</h1>
            <router-link to="/products/create"
                class="product-secondary">
                新增產品
            </router-link>
        </div>

        <form class="mb-4 flex gap-2" @submit.prevent="submitSearch">
            <div class="product-search-input">
                <input ref="searchInput" v-model="search" type="text" placeholder="搜尋產品名稱" aria-label="搜尋產品名稱"
                    class="w-full p-2 border border-[var(--border-control)] rounded" />
                <button v-if="search" type="button" class="product-search-clear" aria-label="清除搜尋" title="清除搜尋" @click="clearSearchInput">
                    <X :size="20" :stroke-width="1.75" aria-hidden="true" />
                </button>
            </div>
            <button type="submit" class="px-4 py-2 bg-[var(--brand)] text-[var(--on-brand)] rounded hover:bg-[var(--brand)]">
                搜尋
            </button>
        </form>

        <div v-if="loading" class="text-center text-[var(--text-muted)]">載入中...</div>

        <p v-else-if="error" role="alert" class="text-[var(--danger)]">{{ error }} <button type="button" class="underline" @click="reload">重試</button></p>

        <section v-else-if="!products.length" class="product-empty" aria-labelledby="products-empty-title">
            <h2 id="products-empty-title">{{ appliedSearch ? '找不到符合的產品' : '尚未建立產品' }}</h2>
            <p v-if="appliedSearch">沒有符合「{{ appliedSearch }}」的產品，試試其他名稱，或清除搜尋查看全部產品。</p>
            <p v-else>建立產品，方便整理品牌、分類及關聯物品。</p>
            <button v-if="appliedSearch" type="button" class="product-secondary" @click="clearSearch">清除搜尋</button>
            <router-link v-else to="/products/create" class="product-secondary">新增產品</router-link>
        </section>

        <ul v-else class="space-y-4">
            <li v-for="product in products" :key="product.id"
                class="bg-[var(--surface)] rounded-xl border border-[var(--border-subtle)] p-6 flex flex-col gap-2">

                <div v-if="product.latest_owned_item?.main_image?.preview_url"
                    class="relative w-full h-40 rounded-lg overflow-hidden">
                    <!-- 背景：模糊處理的 cover -->
                    <img :src="product.latest_owned_item.main_image.preview_url" draggable="false"
                        class="absolute inset-0 w-full h-full object-cover blur-sm scale-110" alt="模糊背景" />

                    <!-- 正中置中的圖片 -->
                    <img :src="product.latest_owned_item.main_image.preview_url" draggable="false"
                        class="relative z-10 h-full object-contain mx-auto" alt="主圖片" />
                </div>
                <div>
                    <div class="text-sm text-[var(--text-muted)] font-medium mb-1">
                        擁有 {{ product.owned_items_count }} 個
                    </div>
                    <div class="font-semibold text-xl text-[var(--text)] break-words max-w-full">
                        {{ product.name }}
                    </div>
                    <div class="text-sm text-[var(--text-muted)] mt-1">
                        品牌：{{ product.brand || '未填寫' }}<br />
                        分類：{{ product.category?.name || '未分類' }}
                    </div>
                </div>

                <div v-if="product.latest_owned_item" class="mt-3">
                    <router-link :to="`/items/${product.latest_owned_item.short_id}`"
                        class="inline-block text-sm text-[var(--brand-ink)] hover:text-[var(--brand-hover)] underline">
                        前往最新物品（序號：{{ product.latest_owned_item.serial_number || '-' }}）
                    </router-link>
                </div>

                <div class="product-actions">
                    <router-link :to="`/products/${product.short_id}`" class="text-[var(--text-muted)] hover:text-[var(--text)]">
                        查看</router-link>
                    <router-link :to="`/products/${product.short_id}/edit`" class="text-[var(--brand-ink)] hover:text-[var(--brand-ink)]">
                        編輯</router-link>
                    <button @click="confirmDelete(product)" :disabled="deleting" class="text-[var(--danger)] hover:text-[var(--danger)]">
                        刪除</button>
                </div>
            </li>
        </ul>
    </div>

    <!-- 分頁按鈕 -->
    <div v-if="!loading && !error && products.length && pagination.last_page > 1" class="aligned-pagination flex flex-wrap justify-center items-center gap-4 mt-6">
        <button @click="goToPage(pagination.current_page - 1)" :disabled="pagination.current_page === 1"
            class="px-4 py-2 bg-[var(--disabled)] rounded disabled:opacity-50">
            上一頁
        </button>

        <span class="text-sm">第 {{ pagination.current_page }} 頁 / 共 {{ pagination.last_page }} 頁</span>

        <button @click="goToPage(pagination.current_page + 1)"
            :disabled="pagination.current_page === pagination.last_page"
            class="px-4 py-2 bg-[var(--disabled)] rounded disabled:opacity-50">
            下一頁
        </button>
    </div>
</template>
