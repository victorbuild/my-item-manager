// resources/js/axios.js
import axios from 'axios'
import router from './router' // 引用 router.js，這樣才能進行跳轉

const instance = axios.create({
    baseURL: '/', // 或根據你實際 Laravel API prefix 設定調整
    withCredentials: true // 若有使用 Sanctum、session 等
})

instance.interceptors.response.use(
    response => response,
    error => {
        if (error.response?.status === 401) {
            router.push('/login') // 或 { name: 'LoginView' }，取決於你的 route name
        }
        return Promise.reject(error)
    }
)

export default instance
