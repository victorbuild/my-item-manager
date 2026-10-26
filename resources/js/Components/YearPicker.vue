<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import AppButton from './AppButton.vue'

const props = defineProps({
    year: { type: Number, required: true },
    minYear: { type: Number, default: 1 },
    maxYear: { type: Number, default: 9999 },
})
const emit = defineEmits(['select'])
const pageStart = ref(1)
const container = ref(null)
let overlay
let overlayRole
const isEnabledYear = (year) => year >= props.minYear && year <= props.maxYear
const focusYear = async (year) => {
    const target = Math.max(props.minYear, Math.min(props.maxYear, year))
    pageStart.value = Math.floor((target - 1) / 12) * 12 + 1
    await nextTick()
    container.value?.querySelector(`[data-year="${target}"]`)?.focus()
}
const changePage = async (direction) => {
    const target = direction < 0 ? pageStart.value - 1 : pageStart.value + 12
    await focusYear(target)
}
const handleKeydown = (event) => {
    const currentYear = Number(event.target.dataset.year)
    if (!currentYear) return
    const destinations = {
        ArrowLeft: currentYear - 1,
        ArrowRight: currentYear + 1,
        ArrowUp: currentYear - 3,
        ArrowDown: currentYear + 3,
        Home: Math.max(props.minYear, pageStart.value),
        End: Math.min(props.maxYear, pageStart.value + 11),
        PageUp: currentYear - 12,
        PageDown: currentYear + 12,
    }
    if (!(event.key in destinations)) return
    event.preventDefault()
    event.stopPropagation()
    focusYear(destinations[event.key])
}
onMounted(async () => {
    // 套件將自訂 overlay 放入 listbox；這裡使用原生按鈕群組而非 option。
    overlay = container.value?.closest('[role="listbox"]')
    overlayRole = overlay?.getAttribute('role')
    overlay?.setAttribute('role', 'group')
    // 等待外層 overlay 完成鍵盤層級初始化，再把焦點移入有效年份。
    await nextTick()
    focusYear(props.year)
})
onBeforeUnmount(() => {
    if (overlay && overlayRole) overlay.setAttribute('role', overlayRole)
})
const resetPage = () => {
    const year = Math.max(props.minYear, Math.min(props.maxYear, props.year))
    pageStart.value = Math.floor((year - 1) / 12) * 12 + 1
}
watch(() => [props.year, props.minYear, props.maxYear], resetPage, { immediate: true })
const years = computed(() => Array.from({ length: Math.min(12, 10000 - pageStart.value) }, (_, index) => pageStart.value + index))
</script>

<template>
    <div ref="container" class="year-picker" role="group" aria-label="選擇年份" @keydown="handleKeydown">
        <div class="year-picker-nav">
            <AppButton variant="secondary" aria-label="前十二年" :disabled="pageStart <= minYear" @click="changePage(-1)">上一組</AppButton>
            <span aria-live="polite">{{ pageStart }}–{{ years.at(-1) }}</span>
            <AppButton variant="secondary" aria-label="後十二年" :disabled="pageStart + 12 > maxYear" @click="changePage(1)">下一組</AppButton>
        </div>
        <div class="year-picker-grid">
            <AppButton v-for="value in years" :key="value" :variant="value === year ? 'primary' : 'secondary'"
                :data-year="value" :aria-label="`${value} 年`" :disabled="!isEnabledYear(value)" :aria-pressed="value === year" @click="emit('select', value)">
                {{ value }}
            </AppButton>
        </div>
    </div>
</template>

<style scoped>
.year-picker { padding: 12px; }
.year-picker-nav { display: flex; align-items: center; justify-content: space-between; gap: 8px; font-size: 14px; }
.year-picker-nav :deep(button) { padding: 8px; }
.year-picker-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; margin-top: 12px; }
</style>
