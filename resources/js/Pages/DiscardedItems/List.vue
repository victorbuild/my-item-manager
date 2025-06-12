<script setup lang="ts">
import { ref, onMounted } from 'vue'
import axios from '../../axios'

const items = ref([])
const pagination = ref(null)
const search = ref('')
const category = ref(null)
const statuses = ref(['discarded'])
const currentPage = ref(1)

const fetchItems = async (page = 1) => {
  currentPage.value = page
  const res = await axios.get('/api/items', {
    params: {
      page,
      search: search.value || undefined,
      category_id: category.value || undefined,
      statuses: statuses.value.length ? statuses.value.join(',') : undefined,
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

onMounted(() => {
  fetchItems()
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
      <template v-if="item.images?.[0]?.thumb_url">
        <img
          :src="item.images[0].thumb_url"
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
        <div class="text-lg font-semibold">
          🏷 {{ item.product?.brand || '無品牌' }}｜{{ item.product?.category?.name || '無分類' }}
        </div>
        <div class="text-base font-medium text-gray-800">
          📦 {{ item.name }}
        </div>
        <div class="text-sm text-gray-600">#{{ item.unit_number }}</div>
        <div class="text-sm text-gray-600">🗓 棄用時間：{{ item.discarded_at || '—' }}</div>
        <div class="text-sm text-gray-600">
          ⏱ 持有天數：
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
        <div class="text-sm text-gray-600">🧾 備註：{{ item.notes || '—' }}</div>
        <div class="text-sm text-gray-600">🧮 成本：{{ item.price ? `NT$${item.price}` : '—' }}</div>
        <div class="text-sm text-gray-600">
          📉 每日成本：
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
