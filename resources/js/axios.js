// resources/js/axios.js
import axios from 'axios'
import router from './router' // 引用 router.js，這樣才能進行跳轉
import { useCurrentUser } from './composables/useCurrentUser'
import { createAuthFailureHandler } from './utils/authFailure'

const instance = axios.create({
    baseURL: '/', // 或根據你實際 Laravel API prefix 設定調整
    withCredentials: true // 若有使用 Sanctum、session 等
})

instance.interceptors.response.use(
    response => response,
    createAuthFailureHandler(router, useCurrentUser())
)

export default instance
