<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import AppIcon from './AppIcon.vue'
import { getItemStatusVisual } from '../utils/itemStatusVisual'

const props = defineProps({ item: { type: Object, required: true }, today: { type: String, required: true } })
const state = computed(() => getItemStatusVisual(props.item, props.today))
const host = ref(null), ready = ref(false), failed = ref(false)
let scene, intersection, colors, motion, loading = false, visible = false, alive = true
const fail = () => { failed.value = true; ready.value = false; scene?.dispose(); scene = undefined }
const sync = () => scene?.setActive(visible && document.visibilityState !== 'hidden')
const load = async () => {
    if (!state.value || !visible || document.hidden || scene || loading || failed.value || !alive) return
    loading = true
    try {
        const { createItemStatusScene } = await import('../utils/itemStatusScene')
        if (!alive || !state.value || !host.value || !visible || document.hidden) return
        scene = createItemStatusScene({ container: host.value, state: state.value, reducedMotion: motion.matches, onError: fail })
        ready.value = true; sync()
    } catch { if (alive) fail() }
    finally { loading = false }
}
const visibilityChange = () => { sync(); load() }
const motionChange = event => scene?.setReducedMotion(event.matches)
watch(state, value => {
    if (!value) { scene?.dispose(); scene = undefined; ready.value = false }
    else if (scene) scene.setState(value)
    else load()
})
onMounted(() => {
    motion = window.matchMedia('(prefers-reduced-motion: reduce)'); motion.addEventListener('change', motionChange)
    if ('IntersectionObserver' in window) {
        intersection = new IntersectionObserver(entries => { visible = entries[0].isIntersecting; sync(); if (visible) load() }); intersection.observe(host.value)
    } else { visible = true; load() }
    colors = new MutationObserver(() => scene?.refreshColors())
    colors.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme', 'data-color-mode', 'class', 'style'] })
    document.addEventListener('visibilitychange', visibilityChange)
})
onBeforeUnmount(() => {
    alive = false; intersection?.disconnect(); colors?.disconnect(); motion?.removeEventListener('change', motionChange)
    document.removeEventListener('visibilitychange', visibilityChange); scene?.dispose()
})
</script>

<template>
    <div ref="host" class="status-scene" aria-hidden="true">
        <AppIcon v-if="!ready" :name="failed || !state ? 'package' : 'clock'" :size="32" class="scene-fallback" />
    </div>
</template>

<style scoped>
.status-scene { position: relative; width: 176px; height: 144px; flex-shrink: 0; pointer-events: none; color: var(--text-muted); }
.status-scene :deep(canvas) { display: block; width: 100%; height: 100%; }
.scene-fallback { position: absolute; inset: 0; margin: auto; }
@media (max-width: 479px) { .status-scene { width: 112px; height: 120px; } }
</style>
