<script setup>
import { computed, nextTick, ref, useId, watch, watchPostEffect } from 'vue'
import { parseDateText, formatDateText, getDateTextError, shiftDateTextYear } from '../utils/dateTextInput'
import { VueDatePicker } from '@vuepic/vue-datepicker'
import { zhTW } from 'date-fns/locale'
import YearPicker from './YearPicker.vue'
import '@vuepic/vue-datepicker/dist/main.css'

const props = defineProps({
    modelValue: { type: String, default: '' },
    min: { type: String, default: undefined },
    max: { type: String, default: '9999-12-31' },
    required: Boolean,
    disabled: Boolean,
    invalid: Boolean,
    id: { type: String, default: undefined },
    label: { type: String, required: true },
    errorMessage: { type: String, default: '' },
})
const emit = defineEmits(['update:modelValue', 'blur', 'draft-change'])
const container = ref(null)
const datePicker = ref(null)
const teleportTarget = ref('body')
// 原生 dialog 位於 top layer，日曆必須掛在同一個 dialog 內才能顯示與操作。
watchPostEffect(() => { teleportTarget.value = container.value?.closest('[data-reka-modal], dialog') || 'body' })
const typedValue = ref(null)
const textTouched = ref(false)
const textError = computed(() => typedValue.value == null ? '' : getDateTextError(typedValue.value, props.min, props.max))
const hasError = computed(() => props.invalid || Boolean(textError.value))
const onTextInput = event => {
    typedValue.value = typeof event === 'string' ? event : event.target?.value ?? ''
    textTouched.value = false
    emit('draft-change', typedValue.value)
    if (!typedValue.value.trim()) updateValue('')
}
const updateValue = value => {
    if (value == null && typedValue.value?.trim() && textError.value) return
    typedValue.value = null
    textTouched.value = false
    emit('draft-change', null)
    emit('update:modelValue', value ?? '')
}
const onBlur = () => {
    textTouched.value = true
    emit('blur')
}
watch(() => props.modelValue, () => { typedValue.value = null; textTouched.value = false; emit('draft-change', null) })
const onYearKey = async event => {
    const input = event.target
    if (props.disabled || input.tagName !== 'INPUT' || event.isComposing || event.keyCode === 229) return
    if (event.key === 'Enter') {
        event.preventDefault()
        event.stopPropagation()
        typedValue.value = input.value
        textTouched.value = true
        const error = getDateTextError(input.value, props.min, props.max)
        input.setCustomValidity(error)
        if (error) {
            input.reportValidity()
            return
        }
        const parsed = parseDateText(input.value)
        const value = parsed ? formatDateText(parsed) : ''
        input.value = value
        updateValue(value)
        datePicker.value?.closeMenu()
        return
    }
    if (event.altKey && event.key === 'ArrowDown') {
        event.preventDefault()
        event.stopPropagation()
        datePicker.value?.openMenu()
        return
    }
    if (!['ArrowUp', 'ArrowDown'].includes(event.key) || event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) return
    if (input.selectionStart == null || input.selectionEnd == null || input.selectionStart > 4 || input.selectionEnd > 4) return
    event.preventDefault()
    event.stopPropagation()
    const value = shiftDateTextYear(input.value, event.key === 'ArrowUp' ? 1 : -1, props.min, props.max)
    if (!value) return
    input.value = value
    updateValue(value)
    await nextTick()
    input.setSelectionRange(0, 4)
}
const descriptionId = `date-description-${useId()}`
const menuId = `date-menu-${useId()}`
// 套件未提供 aria-describedby 的 input 設定，將說明關聯至其原生輸入框。
watchPostEffect(() => {
    const input = container.value?.querySelector('input')
    input?.setAttribute('aria-describedby', descriptionId)
    input?.setCustomValidity(textError.value)
    input?.setAttribute('aria-invalid', String(hasError.value))
})
// 日期上下限使用本地午夜，避免 UTC 轉換造成日期偏移。
const toLocalDate = (value) => {
    if (!value) return undefined
    const [year, month, day] = value.split('-').map(Number)
    const date = new Date(0)
    date.setFullYear(year, month - 1, day)
    date.setHours(0, 0, 0, 0)
    return date
}
const minDate = computed(() => toLocalDate(props.min))
const maxDate = computed(() => toLocalDate(props.max))
const yearRange = computed(() => [minDate.value?.getFullYear() ?? 1, maxDate.value?.getFullYear() ?? 9999])
const selectYear = (year, picker) => {
    let month = picker.month
    if (year === minDate.value?.getFullYear()) month = Math.max(month, minDate.value.getMonth())
    if (year === maxDate.value?.getFullYear()) month = Math.min(month, maxDate.value.getMonth())
    picker.updateMonthYear(month, year)
    picker.toggle()
}
const ariaLabels = computed(() => ({
    input: props.label, menu: `${props.label}日曆`, calendarIcon: `開啟${props.label}日曆`,
    clearInput: '清除日期', nextMonth: '下個月', prevMonth: '上個月',
    nextYear: '下一年', prevYear: '上一年',
    openYearsOverlay: '選擇年份', openMonthsOverlay: '選擇月份',
    toggleOverlay: '切換日期選單',
    day: ({ value }) => value.toLocaleDateString('zh-TW', { year: 'numeric', month: 'long', day: 'numeric', weekday: 'long' }),
    weekDay: (day) => ['星期日', '星期一', '星期二', '星期三', '星期四', '星期五', '星期六'][day],
    monthPicker: (overlay) => overlay ? '選擇月份' : '開啟月份選單',
    yearPicker: (overlay) => overlay ? '選擇年份' : '開啟年份選單',
}))
</script>

<template>
    <div ref="container" class="date-input" @keydown.capture="onYearKey">
        <VueDatePicker
            ref="datePicker"
            :model-value="modelValue || null"
            :disabled="disabled"
            model-type="yyyy-MM-dd"
            :formats="{ input: 'yyyy-MM-dd' }"
            :text-input="{ format: parseDateText, enterSubmit: true, tabSubmit: true, applyOnBlur: true, openMenu: 'open', selectOnFocus: false }"
            :locale="zhTW"
            :week-start="0"
            :aria-labels="ariaLabels"
            :menu-id="menuId"
            :time-config="{ enableTimePicker: false }"
            :input-attrs="{ id, required, state: hasError ? false : undefined, clearable: !required, inputmode: 'numeric', autocomplete: 'off'  }"
            :min-date="minDate"
            :max-date="maxDate"
            :year-range="yearRange"
            prevent-min-max-navigation
            :floating="{ placement: 'bottom-start', strategy: 'fixed', offset: 8, flip: { padding: 12 }, shift: { padding: 12 } }"
            :ui="{ menu: 'item-date-menu' }"
            placeholder="YYYY-MM-DD"
            auto-apply
            arrow-navigation
            :teleport="teleportTarget"
            @update:model-value="updateValue"
            @text-input="onTextInput"
            @cleared="updateValue('')"
            @invalid="textTouched = true"
            @invalid-select="textTouched = true"
            @blur="onBlur"
        >
            <template #year-overlay="picker">
                <YearPicker :year="picker.year" :min-year="yearRange[0]" :max-year="yearRange[1]"
                    @select="selectYear($event, picker)" />
            </template>
        </VueDatePicker>
        <p :id="descriptionId" class="sr-only">
            可直接輸入 2026-10-27、2026 10 27 或 20261027，按 Enter、Tab 或離開欄位套用。
            游標在年份時，上下鍵可加減一年。按 Alt 加向下鍵或點日曆圖示選擇日期，按 Escape 關閉。
            <template v-if="min">最早日期 {{ min }}。</template>
            <template v-if="max">最晚日期 {{ max }}。</template>
            <span aria-live="polite">{{ errorMessage }}</span>
        </p>
        <p v-if="textTouched && textError" class="date-text-error" role="alert">{{ textError }}</p>
    </div>
</template>

<style>
.date-text-error { margin: 4px 0 0; color: var(--danger); font-size: 14px; line-height: 1.5; }
.date-input {
    width: 100%;
    min-width: 0;
    --dp-border-radius: var(--radius-control);
    --dp-font-family: inherit;
    --dp-font-size: 1rem;
    --dp-input-padding: 8px 48px 8px 44px;
    --dp-input-icon-padding: 44px;
    --dp-input-not-clearable-padding: 12px;
}
.date-input .dp--input {
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
    min-height: 44px;
}
.date-input .dp--clear-btn {
    width: 44px;
    height: 44px;
    padding: 0;
    justify-content: center;
    touch-action: manipulation;
}
.date-input .dp--clear-btn .dp--input-icons {
    width: 16px;
    height: 16px;
    padding: 0;
    flex-shrink: 0;
}
.date-input .dp--input:focus-visible,
.item-date-menu :focus-visible {
    outline: 2px solid var(--brand-ink);
    outline-offset: 2px;
}
.date-input .dp--theme-light,
.item-date-menu.dp--theme-light {
    --dp-background-color: var(--surface);
    --dp-text-color: var(--text);
    --dp-primary-color: var(--brand);
    --dp-primary-text-color: var(--on-brand);
    --dp-hover-color: var(--brand-soft);
    --dp-hover-text-color: var(--brand-ink);
    --dp-hover-icon-color: var(--brand-ink);
    --dp-border-color: var(--border-control);
    --dp-border-color-hover: var(--brand-ink);
    --dp-border-color-focus: var(--brand-ink);
    --dp-menu-border-color: var(--border-control);
    --dp-icon-color: var(--text-muted);
    --dp-secondary-color: var(--text-muted);
    --dp-disabled-color: var(--disabled);
    --dp-disabled-color-text: var(--text-muted);
    --dp-danger-color: var(--danger);
}
.item-date-menu {
    --dp-border-radius: var(--radius-control);
    --dp-font-family: inherit;
    --dp-font-size: 1rem;
    --dp-menu-min-width: 260px;
    --dp-month-year-row-height: 44px;
    --dp-month-year-row-button-size: 44px;
    --dp-button-height: 44px;
    z-index: 110;
    max-width: calc(100vw - 24px);
}
.item-date-menu button {
    touch-action: manipulation;
}

/* 浮層限制在可視範圍內，定位交由套件依輸入框翻轉及避讓。 */
.item-date-menu {
    max-height: calc(100dvh - 32px);
    overflow-y: auto;
    overscroll-behavior: contain;
}
</style>
