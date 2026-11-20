<script setup>
import AuthForm from '../Components/AuthForm.vue'
import { useRoute } from 'vue-router'
const route = useRoute()
</script>
<template><AuthForm mode="login" :notice="route.query.reason === 'session-expired' ? '登入或安全驗證已過期，請重新登入。尚未儲存的操作可能未完成。' : ''" /></template>
