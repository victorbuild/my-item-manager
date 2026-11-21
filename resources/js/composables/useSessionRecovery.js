import axios from 'axios'
import { watch } from 'vue'
import { useCurrentUser } from './useCurrentUser'
import { createSessionRecovery } from '../utils/sessionRecovery'

// 使用原始 Axios 確認登入，避免恢復請求再次觸發 API interceptor。
const account = useCurrentUser()
const recovery = createSessionRecovery(axios, account)
watch(account.user, user => {
    if (!user && recovery.state.value === 'ready') recovery.state.value = 'idle'
})
export const useSessionRecovery = () => recovery
