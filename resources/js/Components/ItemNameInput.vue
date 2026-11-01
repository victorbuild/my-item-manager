<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watchPostEffect } from 'vue'

const props = defineProps({ modelValue: { type: String, default: '' } })
const emit = defineEmits(['update:modelValue'])
const textarea = ref(null)
const name = computed({ get: () => props.modelValue, set: value => emit('update:modelValue', value) })
let observer
let previousWidth = 0
const resizeName = () => {
    const input = textarea.value
    if (!input) return
    input.style.height = 'auto'
    const borderHeight = input.offsetHeight - input.clientHeight
    input.style.height = `${Math.max(44, input.scrollHeight + borderHeight)}px`
}
const onEnter = event => {
    if (!event.isComposing && event.keyCode !== 229) event.preventDefault()
}
watchPostEffect(() => { props.modelValue; resizeName() })
onMounted(() => {
    if (typeof ResizeObserver === 'undefined' || !textarea.value) return
    observer = new ResizeObserver(entries => {
        const width = entries[0]?.contentRect.width
        if (width === previousWidth) return
        previousWidth = width
        resizeName()
    })
    observer.observe(textarea.value)
})
onBeforeUnmount(() => observer?.disconnect())
</script>

<template>
    <textarea ref="textarea" v-model="name" class="item-name-input" rows="1" maxlength="255" wrap="soft" @input="resizeName" @keydown.enter="onEnter"></textarea>
</template>

<style scoped>
.item-name-input { display: block; box-sizing: border-box; width: 100%; min-width: 0; min-height: 44px; padding: 10px 12px; border: 1px solid var(--border-control); border-radius: var(--radius-control); background: var(--surface); color: var(--text); font-size: 16px; font-weight: 400; line-height: 1.5; overflow: hidden; overflow-wrap: anywhere; white-space: pre-wrap; resize: none; }
.item-name-input:focus-visible { outline: 2px solid var(--brand-ink); outline-offset: 2px; }
.item-name-input[aria-invalid=true] { border-color: var(--danger); }
</style>
