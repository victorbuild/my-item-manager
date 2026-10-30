<script setup>
import { ref } from 'vue'
const props = defineProps({ id: { type: String, required: true }, label: { type: String, required: true }, name: { type: String, default: 'password' }, modelValue: { type: String, default: '' }, autocomplete: { type: String, default: 'current-password' }, minlength: Number, hint: String, error: String })
const emit = defineEmits(['update:modelValue'])
const visible = ref(false)
</script>
<template>
    <div class="auth-field">
        <label :for="id">{{ label }}</label>
        <p v-if="hint" :id="`${id}-hint`" class="auth-hint">{{ hint }}</p>
        <div class="password-control" :class="{ 'has-error': error }">
            <input :id="id" :name="name" :value="modelValue" @input="emit('update:modelValue', $event.target.value)" :type="visible ? 'text' : 'password'" :autocomplete="autocomplete" :minlength="minlength" required spellcheck="false" autocapitalize="none" :aria-invalid="Boolean(error)" :aria-describedby="[hint ? `${id}-hint` : '', error ? `${id}-error` : ''].filter(Boolean).join(' ') || undefined" />
            <button type="button" :aria-controls="id" :aria-pressed="visible" :aria-label="`${visible ? '隱藏' : '顯示'}${label}`" @click="visible = !visible">{{ visible ? '隱藏' : '顯示' }}</button>
        </div>
        <p v-if="error" :id="`${id}-error`" class="auth-error">{{ error }}</p>
    </div>
</template>
<style scoped>
.password-control { display: flex; align-items: stretch; min-width: 0; border: 1px solid var(--border-control); border-radius: var(--radius-control); background: var(--surface); }
.password-control.has-error { border-color: var(--danger); }
.password-control:focus-within { outline: 2px solid var(--brand-ink); outline-offset: 2px; }
.password-control input { flex: 1; min-width: 0; width: 100%; padding: 10px 12px; min-height: 44px; border: 0; background: transparent; color: var(--text); font-size: 16px; border-radius: var(--radius-control); }
.password-control input:focus { outline: none; }
.password-control button { flex-shrink: 0; min-width: 60px; min-height: 44px; padding: 8px 12px; border: 0; border-radius: var(--radius-control); background: transparent; color: var(--brand-ink); font-size: 14px; cursor: pointer; }
.password-control button:hover { background: var(--brand-soft); }
.password-control button:focus-visible { outline: 2px solid var(--brand-ink); outline-offset: -3px; }
.password-control button:disabled { color: var(--text-muted); cursor: wait; }
</style>
