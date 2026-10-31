<template>
    <div class="aligned-page management-page bg-[var(--canvas)] min-h-screen p-4 mx-auto space-y-6">
        <div class="product-heading">
            <h1 class="text-2xl font-bold">分類詳情</h1>
            <div class="product-heading-actions">
                <router-link to="/categories" class="product-secondary">返回列表</router-link>
                <router-link :to="`/categories/${route.params.id}/edit`" class="product-secondary">編輯</router-link>
            </div>
        </div>

        <div v-if="category" class="bg-[var(--surface)] p-6 rounded-xl border border-[var(--border-subtle)] space-y-4">
            <div><strong>名稱：</strong>{{ category.name }}</div>
        </div>

        <p v-if="loading" role="status" class="text-[var(--text-muted)]">載入中...</p>
        <p v-else-if="error" role="alert" class="text-[var(--danger)]">{{ error }} <button type="button" class="underline" @click="reload">重試</button></p>
        <section v-if="stats" class="category-stats" aria-label="分類統計">
            <button v-for="stat in statOptions" :key="stat.key" type="button" class="category-stat" :aria-expanded="activeTip === stat.key" @click="toggleTip(stat.key)">
                <component :is="stat.icon" :size="20" :stroke-width="1.75" aria-hidden="true" />
                <span>{{ stat.label }}</span>
                <strong>{{ stats[stat.field] || 0 }}</strong>
                <span v-if="activeTip === stat.key" class="category-stat-tip">{{ stat.tip }}</span>
            </button>
        </section>

        <template v-if="!loading && !error && products?.length">
            <div class="bg-[var(--surface)] p-6 rounded-xl border border-[var(--border-subtle)] space-y-4">
                <h2 class="text-lg font-semibold">產品列表</h2>
                <div v-for="product in products" :key="product.id" class="border-b pb-4 mb-4 last:border-b-0">
                    <div class="mb-2">
                        <strong>
                            <router-link
                                class="text-[var(--brand-ink)] hover:underline"
                                :to="`/products/${product.short_id}`"
                            >
                                {{ product.name }}
                            </router-link>
                        </strong>
                    </div>
                    <div class="text-sm text-[var(--text-muted)] mb-2">
                        品牌：{{ product.brand || '—' }}<br />
                        物品數：{{ product.items_count }}
                    </div>
                    <!-- 狀態統計 -->
                    <div v-if="product.status_counts" class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs mt-3 pt-3 border-t">
                        <div class="text-center">
                            <div class="text-[var(--text-muted)]">未到貨</div>
                            <div class="font-semibold text-[var(--text)]">{{ product.status_counts.pre_arrival || 0 }}</div>
                        </div>
                        <div class="text-center">
                            <div class="text-[var(--text-muted)]">未使用</div>
                            <div class="font-semibold text-[var(--text)]">{{ product.status_counts.unused || 0 }}</div>
                        </div>
                        <div class="text-center">
                            <div class="text-[var(--text-muted)]">使用中</div>
                            <div class="font-semibold text-[var(--text)]">{{ product.status_counts.in_use || 0 }}</div>
                        </div>
                        <div class="text-center">
                            <div class="text-[var(--text-muted)]">已棄用</div>
                            <div class="font-semibold text-[var(--text)]">{{ product.status_counts.discarded || 0 }}</div>
                        </div>
                    </div>
                </div>

                <!-- 分頁按鈕 -->
                <AppPagination v-if="pagination.last_page > 1" :current-page="pagination.current_page" :last-page="pagination.last_page" label="分類產品分頁" @change="goToPage" />
            </div>
        </template>
        <section v-else-if="!loading && !error && category" class="product-empty">
            <h2>此分類尚無產品</h2><p>可以在建立或編輯產品時選擇此分類。</p>
            <router-link to="/products/create" class="product-secondary">新增產品</router-link>
        </section>
    </div>
</template>

<script setup>
import '../../../css/aligned-pages.css'
import '../../../css/management-pages.css'
import AppPagination from '../../Components/AppPagination.vue'
import { Boxes, Package, Truck, Archive, CircleCheck, Trash2 } from '@lucide/vue'
import { ref } from 'vue'
import axios from '../../axios'
import { useRoute, useRouter } from 'vue-router'
import { useCategoryDetails } from '../../composables/useCategoryDetails'
const route = useRoute(), router = useRouter()
const { category, stats, products, pagination, loading, error, reload, goToPage } = useCategoryDetails(route, router, axios)
const activeTip = ref(null)
const toggleTip = key => { activeTip.value = activeTip.value === key ? null : key }
const statOptions = [
    { key: 'products', label: '產品數', field: 'products_count', icon: Boxes, tip: '此分類下的產品總數' },
    { key: 'items', label: '物品數', field: 'items_count', icon: Package, tip: '此分類下的物品總數' },
    { key: 'pre_arrival', label: '未到貨', field: 'items_pre_arrival', icon: Truck, tip: '尚未收到貨的物品' },
    { key: 'unused', label: '未使用', field: 'items_unused', icon: Archive, tip: '已到貨但尚未使用的物品' },
    { key: 'in_use', label: '使用中', field: 'items_in_use', icon: CircleCheck, tip: '目前正在使用中的物品' },
    { key: 'discarded', label: '已棄用', field: 'items_discarded', icon: Trash2, tip: '已棄用的物品' },
]
</script>
