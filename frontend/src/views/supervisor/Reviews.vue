<template>
  <div class="space-y-6">
    <div class="flex justify-between items-center">
      <h1 class="text-2xl font-bold text-gray-900">断网续考处理</h1>
      <button @click="load" class="text-sm bg-white border border-gray-300 px-3 py-1.5 rounded hover:bg-gray-50">刷新</button>
    </div>

    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-blue-800">
      系统根据设备指纹、标签页会话、页面启动标识和心跳间隔自动区分
      <span class="font-semibold">真实断网 / 刷新页面 / 换设备登录</span>，这些情况<strong>不会自动记为作弊</strong>。
      断网超过 {{ Math.round((config.offline_grace_seconds || 180) / 60) }} 分钟自动续考时限的场次会出现在下方待处理列表，由您决定是否延时。
    </div>

    <div v-if="loading" class="text-center py-8">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
    </div>

    <template v-else>
      <section>
        <h2 class="text-lg font-bold text-gray-800 mb-3 flex items-center">
          <span class="w-1.5 h-6 bg-red-500 rounded-full mr-3"></span>
          待处理申请
          <span v-if="pending.length" class="ml-2 bg-red-100 text-red-700 text-xs px-2 py-0.5 rounded-full">{{ pending.length }}</span>
        </h2>
        <div v-if="pending.length === 0" class="text-gray-500 text-sm bg-white rounded-lg p-6 shadow-sm">暂无待处理申请</div>
        <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-4">
          <div v-for="r in pending" :key="r.id" class="bg-white rounded-lg shadow p-5 border-l-4 border-red-400">
            <div class="flex justify-between items-start mb-2">
              <div>
                <p class="font-semibold text-gray-900">{{ r.user_name }} <span class="text-xs text-gray-400">({{ r.username }})</span></p>
                <p class="text-sm text-gray-600">{{ r.exam_paper_title }}</p>
              </div>
              <span class="text-xs px-2 py-1 rounded-full bg-yellow-100 text-yellow-800 font-medium">{{ r.review_reason_label }}</span>
            </div>
            <div class="text-xs text-gray-500 grid grid-cols-2 gap-1 mb-3">
              <span>开考：{{ fmt(r.start_time) }}</span>
              <span>申请：{{ fmt(r.review_requested_at) }}</span>
              <span>累计断网：{{ Math.round(r.offline_seconds_total) }} 秒 / {{ r.offline_event_count }} 次</span>
              <span>剩余：{{ r.remaining_seconds }} 秒</span>
              <span>暂存答案：{{ r.drafts_count }} 题</span>
              <span class="truncate" :title="r.device_label">设备：{{ r.device_label || '未知' }}</span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
              <button @click="openEvents(r)" class="text-xs px-3 py-1.5 rounded border border-gray-300 hover:bg-gray-50">查看事件时间线</button>
              <div class="flex items-center gap-1 ml-auto">
                <select v-model="extraMinutes[r.id]" class="text-sm border-gray-300 rounded-md border px-2 py-1.5 w-24">
                  <option :value="1">延时1分</option>
                  <option :value="3">延时3分</option>
                  <option :value="5">延时5分</option>
                  <option :value="10">延时10分</option>
                  <option :value="15">延时15分</option>
                  <option :value="30">延时30分</option>
                </select>
                <button @click="approve(r)" class="text-sm bg-green-600 text-white px-3 py-1.5 rounded hover:bg-green-700">批准延时</button>
                <button @click="reject(r)" class="text-sm bg-red-600 text-white px-3 py-1.5 rounded hover:bg-red-700">驳回并交卷</button>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section>
        <h2 class="text-lg font-bold text-gray-800 mb-3 flex items-center">
          <span class="w-1.5 h-6 bg-indigo-500 rounded-full mr-3"></span>进行中的考试
        </h2>
        <div v-if="active.length === 0" class="text-gray-500 text-sm bg-white rounded-lg p-6 shadow-sm">当前没有进行中的考试</div>
        <div v-else class="bg-white shadow overflow-hidden rounded-lg">
          <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">考生</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">试卷</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">心跳</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">剩余</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">累计断网</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">已延时</th>
                <th class="px-4 py-3"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="r in active" :key="r.id" :class="(r.heartbeat_gap_seconds || 0) > 60 ? 'bg-amber-50' : ''">
                <td class="px-4 py-3">{{ r.user_name }}</td>
                <td class="px-4 py-3">{{ r.exam_paper_title }}</td>
                <td class="px-4 py-3">{{ r.heartbeat_gap_seconds === null ? '—' : r.heartbeat_gap_seconds + ' 秒前' }}</td>
                <td class="px-4 py-3 font-mono">{{ r.remaining_seconds }} 秒</td>
                <td class="px-4 py-3">{{ r.offline_seconds_total }} 秒 / {{ r.offline_event_count }} 次</td>
                <td class="px-4 py-3">{{ r.granted_extra_seconds ? Math.round(r.granted_extra_seconds / 60) + ' 分钟' : '—' }}</td>
                <td class="px-4 py-3 text-right">
                  <button @click="openEvents(r)" class="text-indigo-600 text-xs hover:underline">事件时间线</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>

    <!-- 事件时间线弹窗 -->
    <Teleport to="body">
      <div v-if="eventsModal.record" class="fixed inset-0 z-[80] bg-gray-600/70 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[80vh] flex flex-col">
          <div class="p-5 border-b flex justify-between items-center">
            <div>
              <h3 class="font-bold text-gray-900">事件时间线 · {{ eventsModal.record.user_name }}</h3>
              <p class="text-xs text-gray-500">{{ eventsModal.record.exam_paper_title }}</p>
            </div>
            <button @click="eventsModal.record = null" class="text-gray-400 hover:text-gray-600 text-xl leading-none">×</button>
          </div>
          <div class="p-5 overflow-y-auto space-y-2">
            <div v-if="eventsModal.loading" class="text-center text-sm text-gray-400 py-6">加载中…</div>
            <div v-for="e in eventsModal.events" :key="e.id" class="border rounded-lg p-3 text-sm"
                 :class="riskBorder(e.risk_level)">
              <div class="flex items-center justify-between">
                <span class="font-medium text-gray-800">{{ e.event_type_label }}</span>
                <span class="text-xs px-2 py-0.5 rounded-full" :class="riskBadge(e.risk_level)">{{ e.risk_label }}</span>
              </div>
              <p class="text-gray-500 text-xs mt-1">{{ e.event_label }}</p>
              <p class="text-gray-400 text-xs mt-1">
                {{ fmt(e.server_event_time) }}
                <template v-if="e.server_gap_seconds"> · 心跳缺口 {{ e.server_gap_seconds }} 秒</template>
                <template v-if="e.client_offline_seconds"> · 自报断网 {{ e.client_offline_seconds }} 秒</template>
                <template v-if="e.ip"> · {{ e.ip }}</template>
              </p>
            </div>
            <div v-if="!eventsModal.loading && eventsModal.events.length === 0" class="text-center text-sm text-gray-400 py-6">暂无事件</div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import api from '../../api'
import { useModal } from '../../composables/useModal'

const { confirm, alert } = useModal()
const loading = ref(true)
const pending = ref([])
const active = ref([])
const config = ref({})
const extraMinutes = reactive({})

const eventsModal = reactive({ record: null, events: [], loading: false })

onMounted(load)

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/supervisor/reviews')
    pending.value = data.pending
    active.value = data.active
    config.value = data.config
    for (const r of pending.value) {
      if (!extraMinutes[r.id]) extraMinutes[r.id] = 5
    }
  } catch (e) {
    alert(e.response?.data?.message || '加载失败', '监考工作台', 'error')
  } finally {
    loading.value = false
  }
}

async function openEvents(record) {
  eventsModal.record = record
  eventsModal.events = []
  eventsModal.loading = true
  try {
    const { data } = await api.get(`/supervisor/records/${record.id}/events`)
    eventsModal.events = data.events
  } finally {
    eventsModal.loading = false
  }
}

async function approve(r) {
  const mins = extraMinutes[r.id] || 5
  const ok = await confirm(`确认批准给 ${r.user_name} 延时 ${mins} 分钟？学生可继续作答。`, '批准延时', 'warning')
  if (!ok) return
  try {
    await api.post(`/supervisor/records/${r.id}/approve`, { extra_minutes: mins })
    await load()
  } catch (e) {
    alert(e.response?.data?.message || '操作失败', '批准延时', 'error')
  }
}

async function reject(r) {
  const ok = await confirm(`确认驳回 ${r.user_name} 的续考申请？系统将按其本机暂存的最新答案自动交卷评分。`, '驳回并交卷', 'warning')
  if (!ok) return
  try {
    const { data } = await api.post(`/supervisor/records/${r.id}/reject`, {})
    await load()
    eventsModal.record = null
    alert(`已按暂存答案自动交卷，得分：${data.score}`, '处理完成', 'success')
  } catch (e) {
    alert(e.response?.data?.message || '操作失败', '驳回', 'error')
  }
}

const fmt = (t) => (t ? new Date(t).toLocaleString() : '—')

function riskBadge(level) {
  return {
    low: 'bg-green-100 text-green-700',
    medium: 'bg-yellow-100 text-yellow-800',
    high: 'bg-red-100 text-red-700',
  }[level] || 'bg-gray-100 text-gray-700'
}
function riskBorder(level) {
  return {
    low: 'border-green-200',
    medium: 'border-yellow-300',
    high: 'border-red-300',
  }[level] || 'border-gray-200'
}
</script>
