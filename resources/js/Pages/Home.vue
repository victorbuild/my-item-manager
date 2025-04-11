<template>
    <div class="min-h-screen bg-gray-50 p-8">
        <h1 class="text-2xl font-bold mb-6">物品管理首頁</h1>

        <ul class="space-y-4">
            <li>
                <router-link
                    to="/items"
                    class="block px-4 py-3 bg-white rounded-lg shadow hover:bg-blue-50 transition"
                >
                    📦 物品管理<br />
                    <span class="text-sm text-gray-500">定義產品類型，例如鞋子、電腦、書等</span>
                </router-link>
            </li>

            <li>
                <router-link
                    to="/item-units"
                    class="block px-4 py-3 bg-white rounded-lg shadow hover:bg-green-50 transition"
                >
                    📋 單品管理<br />
                    <span class="text-sm text-gray-500">實際持有的物品，含序號、購買日與價格</span>
                </router-link>
            </li>

            <li>
                <router-link
                    to="/usage-records"
                    class="block px-4 py-3 bg-white rounded-lg shadow hover:bg-yellow-50 transition"
                >
                    🕓 使用紀錄<br />
                    <span class="text-sm text-gray-500">紀錄每次使用情境、時間、地點</span>
                </router-link>
            </li>

            <li>
                <router-link
                    to="/discarded"
                    class="block px-4 py-3 bg-white rounded-lg shadow hover:bg-red-50 transition"
                >
                    🗑️ 報廢項目<br />
                    <span class="text-sm text-gray-500">查看已淘汰或不再使用的物品</span>
                </router-link>
            </li>
        </ul>
    </div>
</template>

<script setup>
</script>
