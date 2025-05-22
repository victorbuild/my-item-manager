<template>
    <div class="min-h-screen p-4">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">物品管理首頁</h1>
            <div>
                <template v-if="isLoggedIn">
                    <div class="flex items-center space-x-2">
                        <div class="w-8 h-8 rounded-full bg-gray-300 flex items-center justify-center text-white">
                            👤
                        </div>
                        <span class="text-sm text-gray-700">{{ user?.name }}</span>
                        <button @click="logout" class="text-sm text-red-600 hover:underline">登出</button>
                    </div>
                </template>
                <template v-else>
                    <router-link
                        to="/login"
                        class="text-sm text-blue-600 hover:underline mr-4"
                    >登入
                    </router-link>
                    <router-link
                        to="/register"
                        class="text-sm text-blue-600 hover:underline"
                    >註冊
                    </router-link>
                </template>
            </div>
        </div>

        <ul class="space-y-4">

            <li>
                <router-link
                    to="/items"
                    class="block px-4 py-3 bg-white rounded-lg shadow hover:bg-blue-50 transition"
                >
                    📦 我的物品<br/>
                    <span class="text-sm text-gray-500">我實際擁有的東西，例如這本書或這雙鞋</span>
                </router-link>
            </li>

            <li>
                <router-link
                    to="/products"
                    class="block px-4 py-3 bg-white rounded-lg shadow hover:bg-indigo-50 transition"
                >
                    🏷️ 產品定義<br/>
                    <span class="text-sm text-gray-500">定義產品資訊（例如 書、電腦、鞋子），作為物品的模板</span>
                </router-link>
            </li>

            <li>
                <router-link
                    to="/usage-records"
                    class="block px-4 py-3 bg-white rounded-lg shadow hover:bg-yellow-50 transition"
                >
                    🕓 使用紀錄<br/>
                    <span class="text-sm text-gray-500">每次使用的時間與情境</span>
                </router-link>
            </li>

            <li>
                <router-link
                    to="/discarded"
                    class="block px-4 py-3 bg-white rounded-lg shadow hover:bg-red-50 transition"
                >
                    🗑️ 已報廢物品<br/>
                    <span class="text-sm text-gray-500">不再使用的物品（含主品與單品）</span>
                </router-link>
            </li>
        </ul>
    </div>
</template>

<script setup>
import {onMounted, ref} from 'vue';
import axios from 'axios';

const isLoggedIn = ref(false);
const user = ref(null);

onMounted(async () => {
    try {
        const res = await axios.get('/api/user');
        isLoggedIn.value = true;
        user.value = res.data;
    } catch {
        isLoggedIn.value = false;
    }
});

const logout = async () => {
    try {
        await axios.post('/logout');
        localStorage.removeItem('loggedIn');
        location.reload();
    } catch (e) {
        console.error('登出失敗', e);
    }
};
</script>
