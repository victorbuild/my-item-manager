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
import { ref, nextTick, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';

const RECAPTCHA_SITE_KEY = import.meta.env.VITE_RECAPTCHA_SITE_KEY;

const emailInput = ref(null);
const email = ref('');
const password = ref('');
const errorMessage = ref('');
const router = useRouter();

onMounted(() => {
    if (document.getElementById('recaptcha-script')) return;
    const script = document.createElement('script');
    script.id = 'recaptcha-script';
    script.src = `https://www.google.com/recaptcha/api.js?render=${RECAPTCHA_SITE_KEY}`;
    script.async = true;
    document.head.appendChild(script);
});

const getRecaptchaToken = () => {
    return new Promise((resolve, reject) => {
        window.grecaptcha.ready(async () => {
            try {
                const token = await window.grecaptcha.execute(RECAPTCHA_SITE_KEY, { action: 'login' });
                resolve(token);
            } catch (e) {
                reject(e);
            }
        });
    });
};

const handleLogin = async () => {
    errorMessage.value = '';
    try {
        const recaptchaToken = await getRecaptchaToken();
        await axios.get('/sanctum/csrf-cookie');
        await axios.post('/login', {
            email: email.value,
            password: password.value,
            recaptcha_token: recaptchaToken,
        });
        localStorage.setItem('loggedIn', 'true');
        router.push('/');
    } catch (error) {
        if (error.response?.data?.message) {
            errorMessage.value = error.response.data.message;
        } else if (error.response?.data?.errors?.recaptcha_token) {
            errorMessage.value = error.response.data.errors.recaptcha_token[0];
        } else {
            errorMessage.value = '登入失敗，請稍後再試';
        }

        email.value = '';
        password.value = '';

        await nextTick();
        emailInput.value?.focus();
    }
};
</script>
