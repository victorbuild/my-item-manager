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
            <p class="text-xs text-gray-400 mt-4 text-center">
                本站受 reCAPTCHA 保護，並適用 Google
                <a href="https://policies.google.com/privacy" target="_blank" class="underline">隱私權政策</a>與
                <a href="https://policies.google.com/terms" target="_blank" class="underline">服務條款</a>。
            </p>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';

const RECAPTCHA_SITE_KEY = import.meta.env.VITE_RECAPTCHA_SITE_KEY;

const name = ref('');
const email = ref('');
const password = ref('');
const passwordConfirmation = ref('');
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
                const token = await window.grecaptcha.execute(RECAPTCHA_SITE_KEY, { action: 'register' });
                resolve(token);
            } catch (e) {
                reject(e);
            }
        });
    });
};

const handleRegister = async () => {
    try {
        const recaptchaToken = await getRecaptchaToken();
        await axios.get('/sanctum/csrf-cookie');
        await axios.post('/register', {
            name: name.value,
            email: email.value,
            password: password.value,
            password_confirmation: passwordConfirmation.value,
            recaptcha_token: recaptchaToken,
        });
        router.push('/');
    } catch (error) {
        alert('註冊失敗，請確認資料格式');
    }
};
</script>
