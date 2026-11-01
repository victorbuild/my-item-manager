import Swal from 'sweetalert2'
import { dialogClasses } from './dialogClasses.js'
import '../../css/app-dialog.css'

// 管理頁共用彈窗外觀；只有明確使用此實例的頁面會套用。
export const appDialog = Swal.mixin({
    buttonsStyling: false,
    returnFocus: true,
    customClass: dialogClasses,
})
