<script setup lang="ts">
import '../../../css/aligned-pages.css'
import { useDiscardedItems } from '../../composables/useDiscardedItems'
import { useRouter, useRoute } from 'vue-router'
import axios from '../../axios'
import ItemThumbnail from '../../Components/ItemThumbnail.vue'
import AppPagination from '../../Components/AppPagination.vue'

const router = useRouter()
const route = useRoute()

const { items, pagination, currentPage, loading, error, goToPage } = useDiscardedItems(route, router, axios)

// 狀態資訊函數
const getStatusInfo = (status) => {
  const statusMap = {
    'pre_arrival': { label: '未到貨', color: 'bg-[var(--info-soft)] text-[var(--info)]' },
    'unused': { label: '未使用', color: 'bg-[var(--canvas)] text-[var(--text-muted)]' },
    'in_use': { label: '使用中', color: 'bg-[var(--success-soft)] text-[var(--success)]' },
    'unused_discarded': { label: '未使用就棄用', color: 'bg-[var(--canvas)] text-[var(--text-muted)]' },
    'used_discarded': { label: '使用後棄用', color: 'bg-[var(--canvas)] text-[var(--text-muted)]' }
  }
  return statusMap[status] || { label: status, color: 'bg-[var(--brand-soft)] text-[var(--text)]' }
}

</script>

<template>
  <div class="aligned-page p-4 space-y-4 mx-auto">
    <h1 class="text-2xl font-bold">已棄用物品</h1>

    <p v-if="loading" role="status" class="text-[var(--text-muted)]">載入中...</p>
    <p v-else-if="error" role="alert" class="text-[var(--danger)]">{{ error }}</p>
    <div v-else-if="items.length === 0" class="text-[var(--text-muted)]">目前沒有已棄用的物品。</div>

    <template v-if="!loading && !error">
    <div
      v-for="item in items"
      :key="item.id"
      class="bg-[var(--surface)] rounded border border-[var(--border-subtle)] p-4 flex items-start gap-4"
    >
      <ItemThumbnail :src="item.main_image?.thumb_url || ''" />
      <div class="flex-1 space-y-1">
        <div class="text-lg font-semibold text-[var(--text)]">
          {{ item.name }}
        </div>
        <div class="text-sm text-[var(--text-muted)]">
          <router-link 
            :to="`/items/${item.short_id}`" 
            class="text-[var(--brand-ink)] hover:text-[var(--brand-ink)] hover:underline transition-colors"
            title="點擊查看物品詳情"
          >
            #{{ item.unit_number }}
          </router-link>
        </div>
        
        <!-- 狀態標籤 -->
        <div class="mt-2">
          <span 
            v-if="item.status" 
            :class="['px-2 py-1 rounded-full text-xs font-medium', getStatusInfo(item.status).color]"
          >
            {{ getStatusInfo(item.status).label }}
          </span>
        </div>
        
        <!-- 棄用相關資訊 -->
        <div class="space-y-1 mt-2">
          <div class="text-sm text-[var(--text-muted)]"><strong>棄用時間：</strong>{{ item.discarded_at || '—' }}
          </div>
          <div class="text-sm text-[var(--text-muted)]"><strong>成本：</strong>{{ item.price ? `NT$${item.price}` : '—' }}
          </div>
          <div class="text-sm text-[var(--text-muted)]">
            <strong>持有天數：</strong>
            <template v-if="item.purchased_at && item.discarded_at">
              {{
                Math.ceil(
                  (new Date(item.discarded_at).getTime() - new Date(item.purchased_at).getTime()) /
                  (1000 * 60 * 60 * 24)
                )
              }} 天
            </template>
            <template v-else>—</template>
          </div>
          <div class="text-sm text-[var(--text-muted)]"><strong>每日成本：</strong>
            <template v-if="item.purchased_at && item.discarded_at && item.price">
              {{
                `NT$${(
                  item.price /
                  ((new Date(item.discarded_at).getTime() - new Date(item.purchased_at).getTime()) /
                    (1000 * 60 * 60 * 24))
                ).toFixed(2)}`
              }}
            </template>
            <template v-else>—</template>
          </div>
        </div>
        
        <!-- 備註 -->
        <div v-if="item.notes" class="text-sm text-[var(--text-muted)] mt-1"><strong>備註：</strong>{{ item.notes }}
        </div>
      </div>
    </div>

    </template>

    <!-- 分頁按鈕 -->
    <AppPagination v-if="pagination" :current-page="currentPage" :last-page="pagination.last_page" label="已棄用物品分頁" @change="goToPage" />
  </div>
</template>

<style scoped>

</style>

