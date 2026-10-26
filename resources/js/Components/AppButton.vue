<script setup>
defineProps({
    variant: { type: String, default: 'primary' },
    type: { type: String, default: 'button' },
})
</script>

<template>
    <button :type="type" :class="['app-button', `app-button--${variant}`]">
        <slot />
    </button>
</template>

<style scoped>
.app-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 44px;
    padding: 10px 16px;
    border: 1px solid var(--brand);
    border-radius: var(--radius-control);
    background: var(--brand);
    color: var(--on-brand);
    font-size: 14px;
    font-weight: 500;
    line-height: 1.5;
    cursor: pointer;
}
.app-button:hover:not(:disabled) {
    background: var(--brand-hover);
    border-color: var(--brand-hover);
}
.app-button--secondary {
    border-color: var(--border-control);
    background: var(--surface);
    color: var(--text);
}
.app-button--secondary:hover:not(:disabled) {
    background: var(--brand-soft);
    border-color: var(--brand-ink);
}
.app-button:focus-visible {
    outline: 2px solid var(--brand-ink);
    outline-offset: 2px;
}
.app-button:disabled {
    background: var(--disabled);
    border-color: var(--disabled);
    color: var(--text-muted);
    cursor: not-allowed;
}
</style>
