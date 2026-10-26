<template>
    <div :class="['app-shell', { 'app-shell--wide': isItemsList }]">
        <AppHeader />
        <router-view />
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import AppHeader from './Components/AppHeader.vue'

const route = useRoute()
const isItemsList = computed(() => route.path.replace(/\/$/, '') === '/items')
</script>

<style scoped>
.app-shell { width: 100%; max-width: 448px; margin-inline: auto; }
.app-shell--wide { max-width: none; }
</style>
