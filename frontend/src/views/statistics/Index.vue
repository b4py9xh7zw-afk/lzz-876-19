<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-900">数据统计</h1>
    </div>
    
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
      <div class="stat-card stat-card-blue group">
        <div class="flex items-center justify-between">
          <div>
            <div class="text-sm font-semibold text-gray-500 mb-1 uppercase tracking-wider">用户总数</div>
            <div class="text-4xl font-extrabold text-gray-900 tracking-tight mt-2 text-shadow-sm">{{ statistics.total_users }}</div>
          </div>
          <div class="w-14 h-14 bg-sky-100 rounded-2xl flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
            <svg class="w-7 h-7 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
          </div>
        </div>
      </div>
      
      <div class="stat-card stat-card-green group">
        <div class="flex items-center justify-between">
          <div>
            <div class="text-sm font-semibold text-gray-500 mb-1 uppercase tracking-wider">试卷总数</div>
            <div class="text-4xl font-extrabold text-gray-900 tracking-tight mt-2 text-shadow-sm">{{ statistics.total_exams }}</div>
          </div>
          <div class="w-14 h-14 bg-green-100 rounded-2xl flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
            <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
          </div>
        </div>
      </div>
      
      <div class="stat-card stat-card-orange group">
        <div class="flex items-center justify-between">
          <div>
            <div class="text-sm font-semibold text-gray-500 mb-1 uppercase tracking-wider">考试次数</div>
            <div class="text-4xl font-extrabold text-gray-900 tracking-tight mt-2 text-shadow-sm">{{ statistics.total_records }}</div>
          </div>
          <div class="w-14 h-14 bg-orange-100 rounded-2xl flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
            <svg class="w-7 h-7 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
        </div>
      </div>
      
      <div class="stat-card stat-card-purple group">
        <div class="flex items-center justify-between">
          <div>
            <div class="text-sm font-semibold text-gray-500 mb-1 uppercase tracking-wider">平均分</div>
            <div class="text-4xl font-extrabold text-gray-900 tracking-tight mt-2 text-shadow-sm">{{ statistics.avg_score }}</div>
          </div>
          <div class="w-14 h-14 bg-purple-100 rounded-2xl flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
            <svg class="w-7 h-7 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
            </svg>
          </div>
        </div>
      </div>
    </div>
    
    <div class="card-base p-6">
      <h3 class="text-lg font-bold text-gray-900 mb-6 flex items-center">
        <span class="w-1.5 h-6 bg-indigo-500 rounded-full mr-3 shadow-sm shadow-indigo-300"></span>
        最近考试记录
      </h3>
      <div class="overflow-x-auto">
        <table class="min-w-full">
          <thead>
            <tr class="table-header">
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider rounded-tl-lg">用户</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">试卷</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">得分</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider rounded-tr-lg">时间</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="record in recentRecords" :key="record.id" class="table-row-hover transition-colors duration-150">
              <td class="px-4 py-3">
                <div class="flex items-center">
                  <div class="w-8 h-8 bg-indigo-100 rounded-full flex items-center justify-center mr-3">
                    <span class="text-indigo-600 font-medium text-sm">{{ record.user?.username?.charAt(0)?.toUpperCase() || 'U' }}</span>
                  </div>
                  <span class="font-medium text-gray-900">{{ record.user?.username }}</span>
                </div>
              </td>
              <td class="px-4 py-3 text-gray-600">{{ record.exam_paper?.title }}</td>
              <td class="px-4 py-3">
                <span 
                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium"
                  :class="getScoreClass(record.score)"
                >
                  {{ record.score }}
                </span>
              </td>
              <td class="px-4 py-3 text-gray-500 text-sm">{{ formatDate(record.updated_at) }}</td>
            </tr>
            <tr v-if="recentRecords.length === 0">
              <td colspan="4" class="px-4 py-8 text-center text-gray-500">
                暂无考试记录
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '../../api'

const statistics = ref({
  total_users: 0,
  total_exams: 0,
  total_records: 0,
  avg_score: 0
})
const recentRecords = ref([])

const getScoreClass = (score) => {
  if (score >= 80) return 'bg-green-100 text-green-800'
  if (score >= 60) return 'bg-yellow-100 text-yellow-800'
  return 'bg-red-100 text-red-800'
}

const formatDate = (dateStr) => {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleString('zh-CN', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit'
  })
}

onMounted(async () => {
  try {
    const response = await api.get('/scores/statistics')
    statistics.value = response.data.statistics
    recentRecords.value = response.data.recent_records
  } catch (e) {
    console.error('Failed to fetch statistics:', e)
  }
})
</script>
