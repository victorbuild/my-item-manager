<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { buildItemUsageCycle } from '../utils/itemLifecycle'
import { buildItemUsageTimeline } from '../utils/itemUsageTimeline'
import { getTodayDate } from '../utils/itemDates'
import { getItemShowMetrics } from '../utils/itemShowState'

const props = defineProps({ item: { type: Object, required: true } })
const emit = defineEmits(['edit-date'])
const today = ref(getTodayDate())
const timeline = computed(() => buildItemUsageTimeline(props.item, today.value))
const metrics = computed(() => getItemShowMetrics(props.item, today.value))
const lifecycle = computed(() => buildItemUsageCycle(props.item, today.value))
const costText = computed(() => {
    if (metrics.value.cost != null) return `${metrics.value.cost}／天`
    if (props.item.price == null || props.item.price === '') return '尚未填寫單價'
    if (!props.item.used_at) return '尚未記錄開始使用日期'
    if (props.item.used_at > today.value) return '開始使用後計算'
    return '請確認單價與使用日期'
})
let timer
onMounted(() => { timer = window.setInterval(() => { today.value = getTodayDate() }, 60000) })
onBeforeUnmount(() => window.clearInterval(timer))
</script>

<template>
    <section class="item-lifecycle" aria-labelledby="lifecycle-heading">
        <header class="lifecycle-heading"><h2 id="lifecycle-heading">物品歷程</h2><p class="today">今天 <time :datetime="today">{{ today }}</time></p></header>
        <p class="lifecycle-help">可透過右側按鈕補記或修改日期。</p>
        <ul v-if="lifecycle.warnings.length" class="date-warnings" aria-label="日期順序提醒"><li v-for="warning in lifecycle.warnings" :key="warning">{{ warning }}</li></ul>
        <ol class="usage-timeline" aria-label="物品日期與相隔天數">
            <li v-for="event in timeline" :key="event.key" :class="{ recorded: event.completed }">
                <div class="event-content">
                    <div class="event-data">
                        <div class="event-heading"><h3>{{ event.label }}</h3><span v-if="event.future" class="planned-label">預計</span></div>
                        <time v-if="event.date" :datetime="event.date">{{ event.date }}</time><span v-else class="missing-date">尚未記錄</span>
                    </div>
                    <button type="button" class="date-action" :aria-label="`${event.date ? '修改' : '記錄'}${event.label}日期${event.date ? '，' + event.date : ''}`" @click="emit('edit-date', event.key)">{{ event.date ? '修改日期' : `記錄${event.label}` }}</button>
                </div>
                <div v-if="event.interval" class="stage-duration"><span>{{ event.interval.label }}</span><span v-if="event.interval.detail" class="duration-detail">{{ event.interval.detail }}</span></div>
            </li>
        </ol>
        <dl class="cost-summary"><div><dt>平均每日成本</dt><dd>{{ costText }}</dd></div></dl>
        <p class="lifecycle-help">使用天數含開始使用當天，棄用後停止累計。</p>
    </section>
</template>

<style scoped>
.item-lifecycle { min-width: 0; padding: 24px; border: 1px solid var(--border-subtle); border-radius: var(--radius-panel); background: var(--surface); color: var(--text); }
.lifecycle-heading { display: flex; align-items: baseline; justify-content: space-between; flex-wrap: wrap; gap: 8px 16px; }
h2 { margin: 0; font-size: 18px; font-weight: 600; }
.today { margin: 0; font-size: 14px; color: var(--text-muted); }
.lifecycle-help { margin: 8px 0 0; font-size: 14px; line-height: 1.5; color: var(--text-muted); }
.date-warnings { margin: 12px 0; padding-left: 20px; font-size: 14px; color: var(--danger); }
.usage-timeline { list-style: none; padding: 0; margin: 24px 0; }
.usage-timeline li { position: relative; min-width: 0; padding: 0 0 0 28px; }
.usage-timeline li::before { content: ''; position: absolute; left: 0; top: 8px; width: 12px; height: 12px; border: 2px solid var(--border-control); border-radius: 50%; background: var(--surface); z-index: 1; }
.usage-timeline li:not(:last-child)::after { content: ''; position: absolute; left: 5px; top: 20px; bottom: -8px; border-left: 2px solid var(--border-subtle); }
.usage-timeline li.recorded::before { background: var(--brand); border-color: var(--brand); }
.event-content { display: flex; align-items: center; justify-content: space-between; gap: 12px 24px; min-height: 56px; }
.event-data { min-width: 0; }
.event-heading { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
h3 { margin: 0; font-size: 16px; font-weight: 600; line-height: 1.5; }
.planned-label { font-size: 14px; color: var(--brand-ink); background: var(--brand-soft); border-radius: var(--radius-control); padding: 0 8px; }
.event-data time, .missing-date { display: block; margin-top: 4px; font-size: 16px; font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
.missing-date { color: var(--text-muted); }
.date-action { flex-shrink: 0; min-height: 44px; padding: 8px 12px; border: 1px solid var(--border-control); border-radius: var(--radius-control); background: var(--surface); color: var(--brand-ink); font-size: 14px; cursor: pointer; }
.date-action:hover { background: var(--brand-soft); }
.date-action:focus-visible { outline: 2px solid var(--brand-ink); outline-offset: 2px; }
.stage-duration { display: flex; flex-wrap: wrap; gap: 4px 12px; padding: 20px 0; font-size: 14px; color: var(--text-muted); line-height: 1.5; }
.duration-detail { color: var(--text-muted); }
.cost-summary { border-top: 1px solid var(--border-subtle); padding-top: 16px; margin: 24px 0 0; }
.cost-summary > div { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 8px 16px; }
.cost-summary dt { font-size: 14px; color: var(--text-muted); }
.cost-summary dd { margin: 0; font-size: 16px; }
@media (max-width: 479px) { .item-lifecycle { padding: 16px; } .event-content { gap: 8px; } .date-action { padding: 8px; } }
</style>
