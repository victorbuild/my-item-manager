<script setup>
import Multiselect from '@vueform/multiselect'
import '@vueform/multiselect/themes/default.css'

defineProps({
    modelValue: { type: String, default: '' },
    options: { type: Array, required: true },
    id: { type: String, required: true },
    label: { type: String, required: true },
    placeholder: { type: String, default: '請選擇' },
})
const emit = defineEmits(['update:modelValue'])
</script>

<template>
    <Multiselect
        :id="id"
        class="single-select"
        :model-value="modelValue"
        :options="options"
        mode="single"
        :searchable="true"
        :can-clear="false"
        :can-deselect="false"
        :close-on-select="true"
        :placeholder="placeholder"
        :aria="{ 'aria-label': label, 'aria-labelledby': `${id}-label` }"
        :attrs="{ inputmode: 'text' }"
        autocomplete="off"
        no-options-text="目前沒有選項"
        no-results-text="找不到符合的選項"
        @update:model-value="emit('update:modelValue', $event ?? '')"
    />
</template>

<style scoped>
.single-select {
    min-width: 0;
    min-height: 44px;
    color: var(--text);
    --ms-bg: var(--surface);
    --ms-bg-disabled: var(--disabled);
    --ms-border-color: var(--border-control);
    --ms-border-color-active: var(--brand-ink);
    --ms-radius: var(--radius-control);
    --ms-font-size: 16px;
    --ms-line-height: 1.5;
    --ms-py: 9px;
    --ms-px: 12px;
    --ms-ring-width: 0;
    --ms-placeholder-color: var(--text-muted);
    --ms-caret-color: var(--text-muted);
    --ms-dropdown-bg: var(--surface);
    --ms-dropdown-border-color: var(--border-control);
    --ms-dropdown-radius: var(--radius-control);
    --ms-max-height: 240px;
    --ms-option-font-size: 16px;
    --ms-option-line-height: 1.5;
    --ms-option-py: 10px;
    --ms-option-bg-pointed: var(--brand-soft);
    --ms-option-color-pointed: var(--brand-ink);
    --ms-option-bg-selected: var(--brand);
    --ms-option-color-selected: var(--on-brand);
    --ms-option-bg-selected-pointed: var(--brand-hover);
    --ms-option-color-selected-pointed: var(--on-brand);
    --ms-empty-color: var(--text-muted);
}
.single-select:focus-within {
    outline: 2px solid var(--brand-ink);
    outline-offset: 2px;
}
.single-select :deep(.multiselect-search) {
    font-size: 16px;
    color: var(--text);
    touch-action: manipulation;
}
.single-select :deep(.multiselect-option) {
    min-height: 44px;
    overflow-wrap: anywhere;
    white-space: normal;
}
</style>
