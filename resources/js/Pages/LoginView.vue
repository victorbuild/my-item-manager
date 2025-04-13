<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-100">
        <div class="bg-white p-6 rounded shadow-md w-full max-w-sm">
            <h2 class="text-xl font-semibold mb-4 text-center">登入</h2>
            <form @submit.prevent="handleLogin">
                <p v-if="errorMessage" class="text-sm text-red-600 mb-3">{{ errorMessage }}</p>
                <input ref="emailInput" v-model="email" type="email" placeholder="Email" class="w-full mb-3 p-2 border rounded" required />
                <input v-model="password" type="password" placeholder="密碼" class="w-full mb-3 p-2 border rounded" required />
                <button type="submit" class="w-full bg-blue-600 text-white p-2 rounded hover:bg-blue-700">登入</button>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref, nextTick } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';

const emailInput = ref(null);
const email = ref('');
const password = ref('');
const errorMessage = ref('');
const router = useRouter();

const handleLogin = async () => {
    errorMessage.value = ''; // 每次送出前清除錯誤
    try {
        await axios.get('/sanctum/csrf-cookie');
        await axios.post('/login', { email: email.value, password: password.value });
        localStorage.setItem('loggedIn', 'true');
        router.push('/');
    } catch (error) {
        if (error.response?.data?.message) {
            errorMessage.value = error.response.data.message;
        } else {
            errorMessage.value = '登入失敗，請稍後再試';
        }

        // 清空欄位
        email.value = '';
        password.value = '';

        await nextTick();
        emailInput.value?.focus();
    }
};
</script>
