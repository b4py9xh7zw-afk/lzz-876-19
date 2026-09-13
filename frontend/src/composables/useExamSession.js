import { ref, computed } from 'vue'
import api from '../api'
import { v4 as uuid } from './uuid'
import { getDeviceIdentity, getTabSessionId } from './useDeviceIdentity'

function currentUserId() {
  try {
    return JSON.parse(localStorage.getItem('user') || 'null')?.id || 'anon'
  } catch (e) {
    return 'anon'
  }
}

/**
 * 断网续考保护：
 *  - 答案 / 题目状态 / 本地修改时间实时写入 localStorage；
 *  - 心跳保活，网络中断时离线继续答题，恢复后自动续考并补传草稿；
 *  - 断网超过服务端允许时长，进入 awaiting_review，由监考老师决定是否延时。
 */
export function useExamSession(paperId) {
  const storageKey = `exam_draft_${paperId}_${currentUserId()}`

  const { deviceFingerprint, deviceLabel } = getDeviceIdentity()
  const tabSessionId = getTabSessionId(paperId)
  const bootId = uuid() // 每次页面加载一个新 ID：刷新/重开必然变化

  // ---- 会话状态 ----
  const examPaper = ref(null)
  const examRecord = ref(null)
  const questions = ref([])
  const answers = ref({}) // { [questionId]: string | string[] }
  const questionStatuses = ref({}) // unanswered | answered | flagged
  const updatedClientAt = ref({}) // { [questionId]: 毫秒时间戳 }
  const loading = ref(true)
  const submitting = ref(false)
  const finished = ref(false)
  const online = ref(navigator.onLine)
  const reconnecting = ref(false)
  const awaitingReview = ref(false)
  const reviewInfo = ref(null)
  const resumedInfo = ref(null)
  const lastSyncedAt = ref(null)
  const syncError = ref('')

  // ---- 计时（以服务端时间为基准，防止学生改本地时钟）----
  const serverNow = ref(Date.now())
  const deadline = ref(0)
  const heartbeatInterval = ref(15)
  const offlineGraceSeconds = ref(180)
  let timerHandle = null
  let heartbeatHandle = null
  let draftHandle = null
  let pollHandle = null

  const offlineSince = ref(null)
  const totalOfflineSeconds = ref(0)

  const timeRemaining = computed(() => Math.max(0, Math.round((deadline.value - serverNow.value) / 1000)))
  const expired = computed(() => serverNow.value >= deadline.value)
  const answeredCount = computed(() =>
    questions.value.filter(q => {
      const v = answers.value[q.id]
      return Array.isArray(v) ? v.length > 0 : v !== undefined && v !== null && v !== ''
    }).length
  )

  function persistLocal() {
    try {
      localStorage.setItem(storageKey, JSON.stringify({
        exam_record_id: examRecord.value?.id || null,
        answers: answers.value,
        statuses: questionStatuses.value,
        updated_client_at: updatedClientAt.value,
        saved_at: Date.now(),
      }))
    } catch (e) {
      // 存储不可用时静默降级到内存
    }
  }

  function loadLocal() {
    try {
      const raw = localStorage.getItem(storageKey)
      return raw ? JSON.parse(raw) : null
    } catch (e) {
      return null
    }
  }

  function clearLocal() {
    localStorage.removeItem(storageKey)
  }

  function sessionPayload(extra = {}) {
    return {
      client_session_id: tabSessionId,
      boot_id: bootId,
      device_fingerprint: deviceFingerprint,
      device_label: deviceLabel,
      client_now: Date.now(),
      ...extra,
    }
  }

  function applyTiming(timing) {
    if (!timing) return
    deadline.value = timing.deadline
    heartbeatInterval.value = timing.heartbeat_interval || 15
    offlineGraceSeconds.value = timing.offline_grace_seconds || 180
    totalOfflineSeconds.value = timing.offline_seconds_total || 0
    serverNow.value = timing.server_now
  }

  /** 初始化默认题目状态，并与服务端草稿按"客户端修改时间"合并。 */
  function mergeDrafts(serverDrafts) {
    for (const q of questions.value) {
      if (answers.value[q.id] === undefined) answers.value[q.id] = q.type === 'multiple_choice' ? [] : ''
      if (!questionStatuses.value[q.id]) questionStatuses.value[q.id] = 'unanswered'
    }

    if (!serverDrafts) return
    for (const [qid, draft] of Object.entries(serverDrafts)) {
      const localAt = updatedClientAt.value[qid] || 0
      const serverAt = draft.updated_client_at || 0
      if (serverAt >= localAt && draft.answer !== null && draft.answer !== undefined && draft.answer !== '') {
        const q = questions.value.find(x => String(x.id) === String(qid))
        if (q?.type === 'multiple_choice') {
          answers.value[qid] = String(draft.answer).split(',').filter(Boolean)
        } else {
          answers.value[qid] = draft.answer
        }
        questionStatuses.value[qid] = draft.status || 'answered'
        updatedClientAt.value[qid] = serverAt
      }
    }
  }

  /**
   * 进入考试：
   *  - mode='start'：学生显式点击"开始考试"，调用 start（幂等，已有记录则续考）；
   *  - mode='resume'：刷新/重进页面，先 resume；没有进行中的记录则返回 notFound。
   */
  async function init(mode = 'resume') {
    loading.value = true

    const local = loadLocal()
    if (local) {
      answers.value = local.answers || {}
      questionStatuses.value = local.statuses || {}
      updatedClientAt.value = local.updated_client_at || {}
    }

    let response
    try {
      if (mode === 'start') {
        response = await api.post(`/exams/${paperId}/start`, sessionPayload())
      } else {
        try {
          response = await api.post(`/exams/${paperId}/resume`, sessionPayload({
            reason: navigator.onLine ? 'page_refresh' : 'network_down',
            offline_seconds: offlineSince.value ? Math.round((Date.now() - offlineSince.value) / 1000) : 0,
          }))
        } catch (e) {
          if (e.response?.status === 404) {
            return { notFound: true }
          }
          throw e
        }
      }

      examPaper.value = response.data.exam_paper
      examRecord.value = response.data.exam_record
      questions.value = response.data.questions
      applyTiming(response.data.timing)
      mergeDrafts(response.data.drafts)
      resumedInfo.value = response.data.resume || null
      applyReviewState(response.data)
      lastSyncedAt.value = Date.now()
      persistLocal()

      if (awaitingReview.value) {
        startReviewPolling()
      } else {
        startLoops()
        syncDrafts(true)
        // 恢复时若考试已到点（例如断网期间超时），自动补交
        if (expired.value) {
          submitExam()
        }
      }
      return { ok: true }
    } finally {
      loading.value = false
    }
  }

  function applyReviewState(data) {
    const status = data.review?.status || data.exam_record?.status
    awaitingReview.value = status === 'awaiting_review'
    if (awaitingReview.value) {
      reviewInfo.value = data.review || { status: 'awaiting_review' }
      stopLoops()
    }
  }

  function markAnswer(questionId, value) {
    answers.value[questionId] = value
    const empty = Array.isArray(value) ? value.length === 0 : value === '' || value === undefined || value === null
    if (!empty && questionStatuses.value[questionId] !== 'flagged') {
      questionStatuses.value[questionId] = 'answered'
    }
    if (empty && questionStatuses.value[questionId] === 'answered') {
      questionStatuses.value[questionId] = 'unanswered'
    }
    touchQuestion(questionId)
  }

  function touchQuestion(questionId) {
    updatedClientAt.value[questionId] = Date.now()
    persistLocal()
  }

  function toggleFlag(questionId) {
    questionStatuses.value[questionId] =
      questionStatuses.value[questionId] === 'flagged' ? 'answered' : 'flagged'
    touchQuestion(questionId)
  }

  function buildDraftList() {
    return questions.value.map(q => {
      const v = answers.value[q.id]
      return {
        question_id: q.id,
        answer: Array.isArray(v) ? v.join(',') : (v ?? ''),
        status: questionStatuses.value[q.id] || 'unanswered',
        updated_client_at: updatedClientAt.value[q.id] || Date.now(),
      }
    }).filter(d => d.answer !== '' || d.status === 'flagged')
  }

  async function syncDrafts(silent = false) {
    if (!online.value || awaitingReview.value || !examRecord.value) return
    const drafts = buildDraftList()
    if (drafts.length === 0) return
    try {
      await api.put(`/exams/${paperId}/drafts`, sessionPayload({ drafts }))
      lastSyncedAt.value = Date.now()
      syncError.value = ''
    } catch (e) {
      if (e.response?.status && e.response.status < 500) return
      if (!silent) syncError.value = '草稿同步失败，已保存在本地'
      handleConnectionLost()
    }
  }

  async function sendHeartbeat() {
    if (!online.value || awaitingReview.value) return
    try {
      const { data } = await api.post(`/exams/${paperId}/heartbeat`, sessionPayload())
      online.value = true
      applyTiming(data.timing)
      offlineSince.value = null
      if (data.awaiting_review) {
        awaitingReview.value = true
        reviewInfo.value = data.review || { status: 'awaiting_review' }
        startReviewPolling()
      }
    } catch (e) {
      if (!e.response) handleConnectionLost()
    }
  }

  function handleConnectionLost() {
    if (!online.value) return
    online.value = false
    offlineSince.value = offlineSince.value || Date.now()
  }

  /** 网络恢复：先 resume 让后端分类（真实断网/刷新/换设备），再补传草稿。 */
  async function handleConnectionRestored() {
    if (online.value || reconnecting.value) return
    online.value = true
    reconnecting.value = true
    const offlineSeconds = offlineSince.value ? Math.round((Date.now() - offlineSince.value) / 1000) : 0

    try {
      const { data } = await api.post(`/exams/${paperId}/resume`, sessionPayload({
        reason: 'network_down',
        offline_seconds: offlineSeconds,
      }))

      examPaper.value = data.exam_paper
      examRecord.value = data.exam_record
      applyTiming(data.timing)
      mergeDrafts(data.drafts)
      resumedInfo.value = data.resume || resumedInfo.value
      totalOfflineSeconds.value = data.timing?.offline_seconds_total ?? totalOfflineSeconds.value
      applyReviewState(data)
      lastSyncedAt.value = Date.now()

      if (awaitingReview.value) {
        startReviewPolling()
      } else {
        persistLocal()
        await syncDrafts(true)
        startLoops()
        // 断网期间已到考试截止时间，恢复后立即补交
        if (expired.value) {
          submitExam()
        }
      }
    } catch (e) {
      online.value = false
    } finally {
      reconnecting.value = false
      if (online.value && !awaitingReview.value) offlineSince.value = null
    }
  }

  function startLoops() {
    stopLoops()
    timerHandle = setInterval(() => { serverNow.value += 1000 }, 1000)
    heartbeatHandle = setInterval(sendHeartbeat, Math.max(5, heartbeatInterval.value) * 1000)
    draftHandle = setInterval(() => syncDrafts(true), 5000)
  }

  function stopLoops() {
    clearInterval(timerHandle)
    clearInterval(heartbeatHandle)
    clearInterval(draftHandle)
    timerHandle = heartbeatHandle = draftHandle = null
  }

  const reviewListeners = new Set()
  function onReviewUpdate(fn) {
    reviewListeners.add(fn)
    return () => reviewListeners.delete(fn)
  }
  function emitReview(payload) {
    reviewListeners.forEach(fn => fn(payload))
  }

  const finishListeners = new Set()
  function onExamFinished(fn) {
    finishListeners.add(fn)
    return () => finishListeners.delete(fn)
  }
  function emitFinished(payload) {
    finishListeners.forEach(fn => fn(payload))
  }

  /** 待监考处理时轮询审核结果。 */
  function startReviewPolling() {
    stopLoops()
    clearInterval(pollHandle)
    pollHandle = setInterval(async () => {
      try {
        const { data } = await api.get(`/exams/${paperId}/questions`)
        examRecord.value = data.exam_record
        applyTiming(data.timing)
        mergeDrafts(data.drafts)
        const status = data.exam_record.status
        if (status !== 'awaiting_review') {
          clearInterval(pollHandle)
          awaitingReview.value = false
          if (status === 'graded') {
            emitReview({ graded: true, score: data.exam_record.score, record: data.exam_record })
          } else {
            startLoops()
            emitReview({ continued: true, record: data.exam_record })
          }
        } else {
          emitReview({ waiting: true, record: data.exam_record })
        }
      } catch (e) {
        // 轮询失败（可能又断网）忽略，下轮继续
      }
    }, 10000)
  }

  async function submitExam() {
    if (submitting.value || finished.value) return null
    submitting.value = true

    const answerData = buildDraftList().map(d => ({ question_id: d.question_id, answer: d.answer }))
    const payload = sessionPayload({
      exam_record_id: examRecord.value.id,
      answers: answerData,
      client_submitted_at: Date.now(),
      offline_seconds: offlineSince.value ? Math.round((Date.now() - offlineSince.value) / 1000) : 0,
    })

    try {
      const { data } = await api.post(`/exams/${paperId}/submit`, payload)
      stopLoops()
      clearInterval(pollHandle)
      clearLocal()
      finished.value = true
      emitFinished({ score: data.score, offline: payload.offline_seconds > 0 })
      return data
    } catch (e) {
      if (e.response?.status === 409 || e.response?.data?.awaiting_review) {
        awaitingReview.value = true
        reviewInfo.value = { status: 'awaiting_review', reason: e.response.data?.message }
        startReviewPolling()
        return { awaitingReview: true }
      }
      if (!e.response) {
        // 断网提交：本地暂存，恢复后重试/自动交卷
        persistLocal()
        return { offlinePending: true }
      }
      throw e
    } finally {
      submitting.value = false
    }
  }

  function cleanup() {
    stopLoops()
    clearInterval(pollHandle)
    window.removeEventListener('online', onOnline)
    window.removeEventListener('offline', onOffline)
    window.removeEventListener('beforeunload', persistLocal)
    window.removeEventListener('pagehide', persistLocal)
  }

  const onOnline = () => handleConnectionRestored()
  const onOffline = () => handleConnectionLost()
  window.addEventListener('online', onOnline)
  window.addEventListener('offline', onOffline)
  window.addEventListener('beforeunload', persistLocal)
  window.addEventListener('pagehide', persistLocal)

  return {
    examPaper, examRecord, questions, answers, questionStatuses, loading, submitting, finished,
    online, reconnecting, awaitingReview, reviewInfo, resumedInfo, lastSyncedAt, syncError,
    timeRemaining, expired, answeredCount, totalOfflineSeconds, offlineSince, offlineGraceSeconds,
    init, markAnswer, touchQuestion, toggleFlag, syncDrafts, sendHeartbeat,
    submitExam, onReviewUpdate, onExamFinished, cleanup, persistLocal,
  }
}
