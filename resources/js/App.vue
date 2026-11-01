<template>
    <div class="app-shell">
        <AppHeader />
        <div class="page-layout">
            <router-view />
        </div>
    </div>
</template>

<script setup>
import AppHeader from './Components/AppHeader.vue'
</script>

<style scoped>
.app-shell { width: 100%; min-width: 0; min-height: 100vh; background: var(--canvas); }
</style>
