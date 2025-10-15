<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import axios from '../../axios'

const router = useRouter()
const route = useRoute()

const items = ref([])
const pagination = ref(null)
const search = ref(route.query.search || '')
const category = ref(route.query.category_id || '')
const currentPage = ref(1)
const perPage = ref(route.query.per_page || '10')

const fetchItems = async (page = 1) => {
  currentPage.value = page
  
  // 更新 URL 參數
  const query = {
    ...(search.value ? { search: search.value } : {}),
    ...(category.value ? { category_id: category.value } : {}),
    ...(page > 1 ? { page } : {}),
    ...(perPage.value !== '10' ? { per_page: perPage.value } : {}),
  }
  
  // 更新瀏覽器 URL
  router.push({
    path: '/discarded',
    query: query
  })
  
  const res = await axios.get('/api/items', {
    params: {
      page,
      search: search.value || undefined,
      category_id: category.value || undefined,
      statuses: 'unused_discarded,used_discarded', // 固定篩選棄用物品
      sort: 'discarded', // 使用棄用排序
      per_page: perPage.value,
    },
  })
  items.value = res.data.items
  pagination.value = res.data.meta
}

const goToPage = (page: number) => {
  if (page >= 1 && (!pagination.value || page <= pagination.value.last_page)) {
    fetchItems(page)
  }
}

// 狀態資訊函數
const getStatusInfo = (status) => {
  const statusMap = {
    'pre_arrival': { label: '📦 未到貨', color: 'bg-yellow-100 text-yellow-800' },
    'unused': { label: '📚 未使用', color: 'bg-blue-100 text-blue-800' },
    'in_use': { label: '✅ 使用中', color: 'bg-green-100 text-green-800' },
    'unused_discarded': { label: '⚠️ 未使用就棄用', color: 'bg-red-100 text-red-800' },
    'used_discarded': { label: '🗑️ 使用後棄用', color: 'bg-gray-100 text-gray-800' }
  }
  return statusMap[status] || { label: status, color: 'bg-gray-100 text-gray-800' }
}

onMounted(() => {
  // 從 URL 讀取初始頁面
  const initialPage = parseInt(route.query.page as string) || 1
  fetchItems(initialPage)
})
</script>

<template>
  <div class="p-4 space-y-4 max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold">🗑 已棄用物品</h1>

    <div v-if="items.length === 0" class="text-gray-500">目前沒有已棄用的物品。</div>

    <div
      v-for="item in items"
      :key="item.id"
      class="bg-white rounded shadow p-4 flex items-start gap-4"
    >
      <template v-if="item.main_image?.thumb_url">
        <img
          :src="item.main_image.thumb_url"
          class="w-20 h-20 object-cover rounded bg-gray-100"
          alt="Item Image"
        />
      </template>
      <template v-else>
        <div class="w-20 h-20 rounded bg-gray-200 flex items-center justify-center text-gray-400 text-xs">
          無圖片
        </div>
      </template>
      <div class="flex-1 space-y-1">
        <div class="text-lg font-semibold text-gray-800">
          {{ item.name }}
        </div>
        <div class="text-sm text-gray-600">
          <router-link 
            :to="`/items/${item.short_id}`" 
            class="text-blue-600 hover:text-blue-800 hover:underline transition-colors"
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
          <div class="text-sm text-gray-600">
            🗓 <strong>棄用時間：</strong>{{ item.discarded_at || '—' }}
          </div>
          <div class="text-sm text-gray-600">
            💰 <strong>成本：</strong>{{ item.price ? `NT$${item.price}` : '—' }}
          </div>
          <div class="text-sm text-gray-600">
            ⏱ <strong>持有天數：</strong>
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
          <div class="text-sm text-gray-600">
            📉 <strong>每日成本：</strong>
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
        <div v-if="item.notes" class="text-sm text-gray-600 mt-1">
          🧾 <strong>備註：</strong>{{ item.notes }}
        </div>
      </div>
    </div>

    <!-- 分頁按鈕 -->
    <div v-if="pagination" class="flex justify-center mt-6 space-x-2">
      <button
        @click="goToPage(currentPage - 1)"
        :disabled="currentPage === 1"
        class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50"
      >
        ← 上一頁
      </button>
      <span class="px-3 py-1 text-gray-700">第 {{ currentPage }} 頁 / 共 {{ pagination.last_page }} 頁</span>
      <button
        @click="goToPage(currentPage + 1)"
        :disabled="currentPage === pagination.last_page"
        class="px-3 py-1 bg-gray-200 rounded disabled:opacity-50"
      >
        下一頁 →
      </button>
    </div>
  </div>
</template>

<style scoped>

</style>

