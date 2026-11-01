import axios from 'axios'
import { createCurrentUserState } from '../utils/currentUser'

// 帳號查詢允許訪客的 401，不套用會跳轉登入頁的 API interceptor。
const account = createCurrentUserState(axios)
export const useCurrentUser = () => account
