import { v4 as uuid } from './uuid'

const DEVICE_KEY = 'exam_device_id'
const LABEL_KEY = 'exam_device_label'

/**
 * 设备身份：持久化的设备指纹（localStorage，跨刷新/重开保持，换浏览器/设备会变化）。
 * 服务端用它区分"换设备登录"与"刷新页面"。
 */
export function getDeviceIdentity() {
  let deviceFingerprint = localStorage.getItem(DEVICE_KEY)
  if (!deviceFingerprint) {
    deviceFingerprint = uuid()
    localStorage.setItem(DEVICE_KEY, deviceFingerprint)
  }

  let deviceLabel = localStorage.getItem(LABEL_KEY)
  if (!deviceLabel) {
    const nav = navigator
    const os = nav.platform || nav.userAgentData?.platform || 'unknown'
    const screenPart = `${window.screen.width}x${window.screen.height}`
    const lang = nav.language || 'unknown'
    const ua = (nav.userAgent || '').slice(0, 80)
    deviceLabel = `${os}|${screenPart}|${lang}|${ua}`
    localStorage.setItem(LABEL_KEY, deviceLabel)
  }

  return { deviceFingerprint, deviceLabel }
}

/**
 * 标签页会话 ID：sessionStorage 保存，刷新页面仍在，关闭标签页/换设备后重新生成。
 */
export function getTabSessionId(paperId) {
  const key = `exam_tab_session_${paperId}`
  let sessionId = sessionStorage.getItem(key)
  if (!sessionId) {
    sessionId = uuid()
    sessionStorage.setItem(key, sessionId)
  }
  return sessionId
}
