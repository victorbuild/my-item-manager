<template>
    <div class="min-h-screen flex flex-col items-center justify-center bg-gray-100 p-8">
        <h1 class="text-3xl font-bold mb-6">物品管理系統</h1>
        <p class="text-gray-700 mb-8">歡迎使用您的專屬物品管理工具。</p>

        <router-link
            to="/items"
            class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
        >
            開始管理物品
        </router-link>
    </div>
</template>

<script setup>
</script>
