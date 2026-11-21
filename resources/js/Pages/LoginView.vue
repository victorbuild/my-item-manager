<script setup>
import AuthForm from '../Components/AuthForm.vue'
import { useRoute } from 'vue-router'
const route = useRoute()
</script>
<template><AuthForm mode="login" :notice="route.query.reason === 'session-expired' ? '登入已過期，請重新登入。若從其他分頁開啟，登入後請回到原分頁確認狀態並重試操作。' : ''" /></template>
