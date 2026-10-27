<script setup>
import { computed } from 'vue'
import { Clock3, Upload, ChevronLeft, BookOpen, CalendarDays, ChartColumn, Check, CircleCheck, Gem, Image, Lightbulb, Package, Pencil, Scale, Trash2, TriangleAlert, UserRound, X } from '@lucide/vue'

const props = defineProps({
    name: { type: String, required: true },
    size: { type: Number, default: 20 },
})
const icons = { clock: Clock3, upload: Upload, back: ChevronLeft, book: BookOpen, calendar: CalendarDays, chart: ChartColumn, check: Check, success: CircleCheck, gem: Gem, image: Image, hint: Lightbulb, package: Package, edit: Pencil, balance: Scale, delete: Trash2, warning: TriangleAlert, user: UserRound, close: X }
const icon = computed(() => icons[props.name] || Package)
</script>

<template>
    <component :is="icon" :size="size" :stroke-width="1.75" aria-hidden="true" focusable="false" class="app-icon" />
</template>

<style scoped>
.app-icon { display: inline-block; flex-shrink: 0; vertical-align: middle; }
</style>
