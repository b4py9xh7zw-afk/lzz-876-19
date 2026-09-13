<template>
  <div class="space-y-6">
    <div class="flex flex-wrap justify-between items-center gap-3">
      <h1 class="text-2xl font-bold text-gray-900">{{ examPaper?.title }}</h1>
      <div class="flex items-center gap-4">
        <div class="text-sm text-gray-500">
          已答 <span class="font-semibold text-indigo-600">{{ answeredCount }}</span> / {{ questions.length }} 题
        </div>
        <div class="text-lg">
          剩余时间:
          <span class="font-mono font-bold" :class="{'text-red-600': timeRemaining < 60}">{{ formatTime(timeRemaining) }}</span>
        </div>
      </div>
    </div>

    <!-- 网络状态条 -->
    <div v-if="!online" class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 flex items-start gap-3">
      <svg class="w-5 h-5 text-amber-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
      </svg>
      <div class="text-sm text-amber-800">
        <p class="font-semibold">网络已中断，您可以继续作答</p>
        <p class="mt-0.5">答案、题目状态和本地时间已自动暂存在本机；网络恢复后将自动续考并同步。
          本次离线已 <span class="font-mono font-semibold">{{ formatTime(currentOfflineSeconds) }}</span>，
          超过 {{ Math.round(offlineGraceSeconds / 60) }} 分钟需监考老师处理。</p>
      </div>
    </div>
    <div v-else-if="reconnecting" class="rounded-lg border border-blue-300 bg-blue-50 px-4 py-3 text-sm text-blue-800">
      网络已恢复，正在续考并同步暂存答案…
    </div>
    <div v-else-if="resumeNotice" class="rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
      {{ resumeNotice }}
    </div>

    <div v-if="loading" class="text-center py-8">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
      <p class="mt-2 text-gray-500 text-sm">正在恢复考试会话…</p>
    </div>

    <template v-else>
      <div v-for="(question, index) in questions" :key="question.id" class="bg-white rounded-lg shadow p-6 border-l-4"
           :class="questionStatuses[question.id] === 'flagged' ? 'border-yellow-400' : 'border-transparent'">
        <div class="flex items-start mb-4">
          <span class="bg-indigo-100 text-indigo-800 text-sm font-medium px-2.5 py-0.5 rounded mr-3">{{ index + 1 }}</span>
          <div class="flex-1">
            <div class="flex items-start justify-between gap-3">
              <h3 class="text-lg font-medium text-gray-900 mb-2">{{ question.title }}</h3>
              <button type="button" @click="toggleFlag(question.id)"
                      class="flex-shrink-0 text-xs px-2 py-1 rounded-full border transition-colors"
                      :class="questionStatuses[question.id] === 'flagged'
                        ? 'bg-yellow-100 border-yellow-400 text-yellow-800'
                        : 'bg-gray-50 border-gray-300 text-gray-500 hover:bg-yellow-50'">
                {{ questionStatuses[question.id] === 'flagged' ? '★ 待复查' : '☆ 标记复查' }}
              </button>
            </div>
            <p class="text-sm text-gray-500 mb-3">分值: {{ question.score }}分 | 题型: {{ questionTypeLabel(question.type) }}</p>
            <div class="space-y-2">
              <template v-if="question.type === 'single_choice'">
                <label v-for="(label, key) in question.options" :key="key" class="flex items-center p-3 border rounded cursor-pointer hover:bg-gray-50" :class="{'border-indigo-500 bg-indigo-50': answers[question.id] === key}">
                  <input type="radio" :name="'question_' + question.id" :value="key"
                         :checked="answers[question.id] === key"
                         @change="markAnswer(question.id, key)"
                         class="h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500">
                  <span class="ml-3">{{ key }}. {{ label }}</span>
                </label>
              </template>
              <template v-else-if="question.type === 'true_false'">
                <label class="flex items-center p-3 border rounded cursor-pointer hover:bg-gray-50" :class="{'border-indigo-500 bg-indigo-50': answers[question.id] === 'true'}">
                  <input type="radio" :name="'question_' + question.id" value="true"
                         :checked="answers[question.id] === 'true'"
                         @change="markAnswer(question.id, 'true')"
                         class="h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500">
                  <span class="ml-3">正确</span>
                </label>
                <label class="flex items-center p-3 border rounded cursor-pointer hover:bg-gray-50" :class="{'border-indigo-500 bg-indigo-50': answers[question.id] === 'false'}">
                  <input type="radio" :name="'question_' + question.id" value="false"
                         :checked="answers[question.id] === 'false'"
                         @change="markAnswer(question.id, 'false')"
                         class="h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500">
                  <span class="ml-3">错误</span>
                </label>
              </template>
              <template v-else-if="question.type === 'multiple_choice'">
                <label v-for="(label, key) in question.options" :key="key" class="flex items-center p-3 border rounded cursor-pointer hover:bg-gray-50" :class="{'border-indigo-500 bg-indigo-50': (answers[question.id] || []).includes(key)}">
                  <input type="checkbox" :value="key"
                         :checked="(answers[question.id] || []).includes(key)"
                         @change="toggleMultipleChoice(question.id, key)"
                         class="h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500">
                  <span class="ml-3">{{ key }}. {{ label }}</span>
                </label>
              </template>
              <template v-else>
                <textarea :value="answers[question.id] || ''"
                          @input="markAnswer(question.id, $event.target.value)"
                          rows="3" class="w-full border border-gray-300 rounded-md p-3 focus:ring-indigo-500 focus:border-indigo-500" placeholder="请输入答案"></textarea>
              </template>
            </div>
          </div>
        </div>
      </div>

      <div class="flex justify-between items-center">
        <router-link to="/exams" class="bg-gray-300 text-gray-700 py-2 px-4 rounded hover:bg-gray-400">返回</router-link>
        <div class="flex items-center gap-4">
          <span class="text-xs text-gray-400">
            {{ !online ? '暂存于本机' : (lastSyncedAt ? `已同步 ${formatSyncTime(lastSyncedAt)}` : '') }}
          </span>
          <button @click="submitExam" :disabled="submitting" class="bg-indigo-600 text-white py-2 px-6 rounded hover:bg-indigo-700 disabled:opacity-50">
            {{ submitting ? '提交中...' : '提交答卷' }}
          </button>
        </div>
      </div>
    </template>

    <!-- 待监考处理遮罩 -->
    <Teleport to="body">
      <div v-if="awaitingReview && !loading" class="fixed inset-0 z-[80] bg-gray-600/70 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6">
          <div class="flex items-center gap-3 mb-4">
            <div class="w-12 h-12 rounded-full bg-yellow-100 flex items-center justify-center flex-shrink-0">
              <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
              </svg>
            </div>
            <div>
              <h3 class="text-lg font-bold text-gray-900">等待监考老师处理</h3>
              <p class="text-sm text-gray-500">系统已暂存您的全部答案，不会丢失</p>
            </div>
          </div>
          <p class="text-sm text-gray-600 mb-2">原因：{{ reviewReasonText }}</p>
          <p class="text-sm text-gray-500 mb-4">
            刷新页面、真实断网与换设备登录已由系统区分记录，<strong class="text-gray-700">不会直接按作弊处理</strong>。
            监考老师可批准延时让您继续考试，或按暂存答案直接交卷。请保持本页面打开。
          </p>
          <div v-if="lastDecision" class="rounded-lg p-3 text-sm" :class="lastDecision.approved ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800'">
            {{ lastDecision.message }}
          </div>
          <div v-else class="flex items-center gap-2 text-sm text-gray-400">
            <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-gray-400"></div>
            正在等待审核结果…
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useModal } from '../../composables/useModal'
import { useExamSession } from '../../composables/useExamSession'

const route = useRoute()
const router = useRouter()
const { alert } = useModal()
const session = useExamSession(route.params.id)

const {
  examPaper, questions, answers, questionStatuses, loading, submitting,
  online, reconnecting, awaitingReview, reviewInfo, resumedInfo, lastSyncedAt,
  timeRemaining, answeredCount, offlineSince, offlineGraceSeconds,
  markAnswer, toggleFlag,
} = session

const resumeNotice = ref('')
const lastDecision = ref(null)
const offlineTick = ref(Date.now())
let offlineTimer = null
let autoSubmitTimer = null

const currentOfflineSeconds = computed(() => {
  offlineTick.value // 依赖触发刷新
  return offlineSince.value ? Math.max(0, Math.floor((Date.now() - offlineSince.value) / 1000)) : 0
})

const reviewReasonText = computed(() => {
  const reason = reviewInfo.value?.reason
  const map = {
    offline_overtime: '断网时长超过允许的自动续考时限',
    device_switch: '检测到更换设备登录',
    heartbeat_gap: '心跳长时间缺失',
    clock_mismatch: '本地时间与服务端时间偏差过大',
  }
  return map[reason] || (typeof reason === 'string' && reason ? reason : '考试会话存在需确认的异常情况')
})

onMounted(async () => {
  try {
    const result = await session.init(route.query.start === '1' ? 'start' : 'resume')
    if (result?.notFound) {
      alert('未找到进行中的考试，请在考试列表点击"开始考试"。', '考试未开始', 'warning')
      router.push('/exams')
      return
    }
    if (resumedInfo.value) {
      resumeNotice.value = buildResumeNotice(resumedInfo.value)
      if (resumeNotice.value) setTimeout(() => (resumeNotice.value = ''), 8000)
    }
    session.onReviewUpdate(handleReviewUpdate)
    session.onExamFinished(handleFinished)
  } catch (e) {
    alert(e.response?.data?.message || '获取考试信息失败', '考试加载失败', 'error')
    router.push('/exams')
    return
  }

  offlineTimer = setInterval(() => { offlineTick.value = Date.now() }, 1000)
  autoSubmitTimer = setInterval(async () => {
    if (session.expired.value && !session.submitting.value && !session.finished.value
        && !awaitingReview.value && !loading.value && online) {
      clearInterval(autoSubmitTimer)
      await doSubmit(true)
    }
  }, 1000)
})

onUnmounted(() => {
  clearInterval(offlineTimer)
  clearInterval(autoSubmitTimer)
  session.cleanup()
})

function handleReviewUpdate(payload) {
  if (payload.continued) {
    lastDecision.value = { approved: true, message: '监考老师已批准延时，您可以继续考试。' }
    setTimeout(() => { lastDecision.value = null }, 5000)
  } else if (payload.graded) {
    lastDecision.value = { approved: false, message: `监考老师已按暂存答案交卷，得分：${payload.score} 分，即将跳转到成绩页。` }
    setTimeout(() => router.push('/records'), 3000)
  }
}

function handleFinished(payload) {
  clearInterval(autoSubmitTimer)
  const prefix = payload?.offline ? '检测到考试已到点，已按本机暂存答案自动交卷。' : ''
  alert(`${prefix}考试完成！得分: ${payload.score}`, '考试完成', 'success')
  router.push('/records')
}

function buildResumeNotice(info) {
  if (info.need_review) return ''
  const gap = info.server_gap_seconds
  if (info.detected_type === 'offline_resume' && gap) {
    return `检测到网络中断约 ${gap} 秒，已恢复考试，暂存答案已同步。`
  }
  if (info.detected_type === 'page_refresh') return '检测到页面刷新，已恢复您的答题进度。'
  if (info.detected_type === 'device_switch') return '检测到设备变更，已按换设备登录记录（不计作作弊）。'
  if (info.detected_type_label) return `会话已恢复（${info.detected_type_label}）。`
  return ''
}

const formatTime = (seconds) => {
  const mins = Math.floor(seconds / 60)
  const secs = seconds % 60
  return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`
}

const formatSyncTime = (ts) => new Date(ts).toLocaleTimeString()

const questionTypeLabel = (type) => ({
  single_choice: '单选题',
  multiple_choice: '多选题',
  true_false: '判断题',
  fill_blank: '填空题',
  essay: '问答题',
}[type] || type)

function toggleMultipleChoice(questionId, key) {
  const current = [...(answers.value[questionId] || [])]
  const idx = current.indexOf(key)
  if (idx === -1) current.push(key)
  else current.splice(idx, 1)
  markAnswer(questionId, current)
}

async function submitExam() {
  await doSubmit(false)
}

async function doSubmit(isAuto) {
  try {
    const result = await session.submitExam()
    if (!result) return
    if (result.offlinePending) {
      alert('当前处于断网状态，答案已暂存本机。请保持页面打开，网络恢复后将自动同步，也可在恢复后手动点击提交。', '网络未连接', 'warning')
      return
    }
    if (result.awaitingReview) return
    if (isAuto) {
      // 交卷完成统一由 onExamFinished 处理跳转
      return
    }
  } catch (e) {
    alert(e.response?.data?.message || '提交失败，答案已本地暂存，请稍后重试', '提交失败', 'error')
  }
}
</script>
