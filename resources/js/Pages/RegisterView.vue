<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-100">
        <div class="bg-white p-6 rounded shadow-md w-full max-w-sm">
            <h2 class="text-xl font-semibold mb-4 text-center">註冊</h2>
            <form @submit.prevent="handleRegister">
                <input v-model="name" type="text" placeholder="名稱" class="w-full mb-3 p-2 border rounded" required />
                <input v-model="email" type="email" placeholder="Email" class="w-full mb-3 p-2 border rounded" required />
                <input v-model="password" type="password" placeholder="密碼" class="w-full mb-3 p-2 border rounded" required />
                <input v-model="passwordConfirmation" type="password" placeholder="確認密碼" class="w-full mb-3 p-2 border rounded" required />
                <button type="submit" class="w-full bg-green-600 text-white p-2 rounded hover:bg-green-700">註冊</button>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';

const name = ref('');
const email = ref('');
const password = ref('');
const passwordConfirmation = ref('');
const router = useRouter();

const handleRegister = async () => {
    try {
        await axios.get('/sanctum/csrf-cookie');
        await axios.post('/register', {
            name: name.value,
            email: email.value,
            password: password.value,
            password_confirmation: passwordConfirmation.value,
        });
        router.push('/');
    } catch (error) {
        alert('註冊失敗，請確認資料格式');
    }
};
</script>
