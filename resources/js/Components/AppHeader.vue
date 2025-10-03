<template>
    <div :class="headerClass">
        <div class="relative max-w-2xl mx-auto flex items-center justify-center">
            <button
                @click="goBack"
                :class="backBtnClass"
                aria-label="返回上一頁"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M15 18l-6-6 6-6" />
                </svg>
            </button>
            <router-link to="/" class="text-base font-semibold tracking-wide text-gray-700 hover:text-gray-800 transition-colors" aria-label="前往首頁">
                物品管理
            </router-link>
        </div>
    </div>
</template>

<script setup>
import { useRouter } from 'vue-router'
import { ref, onMounted, onUnmounted, computed } from 'vue'

const router = useRouter()
const scrolled = ref(false)

const onScroll = () => {
    scrolled.value = window.scrollY > 4
}

onMounted(() => {
    window.addEventListener('scroll', onScroll, { passive: true })
    onScroll()
})

onUnmounted(() => {
    window.removeEventListener('scroll', onScroll)
})

const goBack = () => {
    if (window.history.length > 1) {
        router.back()
    } else {
        router.push('/items')
    }
}

const headerClass = computed(() => (
    scrolled.value
        ? 'sticky top-0 z-40 w-full px-6 py-3 bg-white/95 backdrop-blur border-b border-gray-200'
        : 'sticky top-0 z-40 w-full px-6 py-3 bg-[#f5f5f5]/95 backdrop-blur supports-[backdrop-filter]:bg-[#f5f5f5]/70'
))

const backBtnClass = computed(() => (
    scrolled.value
        ? 'absolute left-0 w-10 h-10 flex items-center justify-center rounded text-gray-800 active:opacity-70 transition'
        : 'absolute left-0 w-10 h-10 flex items-center justify-center rounded text-gray-700 active:opacity-70 transition'
))
</script>

<style scoped></style>


