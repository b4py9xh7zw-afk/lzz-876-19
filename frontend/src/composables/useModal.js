import { reactive } from 'vue'

// 全局共享的 modal 状态
const modalState = reactive({
  show: false,
  title: '',
  message: '',
  type: 'info',
  showCancel: false,
  showConfirm: true,
  confirmText: '确定',
  cancelText: '取消',
  onConfirm: null,
  onCancel: null
})

export function useModal() {
  const showModal = (options) => {
    modalState.show = true
    modalState.title = options.title || ''
    modalState.message = options.message || ''
    modalState.type = options.type || 'info'
    modalState.showCancel = options.showCancel || false
    modalState.showConfirm = options.showConfirm !== false
    modalState.confirmText = options.confirmText || '确定'
    modalState.cancelText = options.cancelText || '取消'
    modalState.onConfirm = options.onConfirm || null
    modalState.onCancel = options.onCancel || null
  }

  const close = () => {
    modalState.show = false
    modalState.onConfirm = null
    modalState.onCancel = null
  }

  const alert = (message, title = '提示', type = 'info') => {
    showModal({
      title,
      message,
      type,
      showCancel: false,
      showConfirm: true,
      confirmText: '确定'
    })
  }

  const confirm = (message, title = '确认', type = 'warning') => {
    return new Promise((resolve) => {
      showModal({
        title,
        message,
        type,
        showCancel: true,
        showConfirm: true,
        confirmText: '确定',
        cancelText: '取消',
        onConfirm: () => resolve(true),
        onCancel: () => resolve(false)
      })
    })
  }

  const handleConfirm = () => {
    if (modalState.onConfirm) {
      modalState.onConfirm()
    }
    close()
  }

  const handleCancel = () => {
    if (modalState.onCancel) {
      modalState.onCancel()
    }
    close()
  }

  return {
    modalState,
    showModal,
    close,
    alert,
    confirm,
    handleConfirm,
    handleCancel
  }
}

// 导出全局状态供 App.vue 使用
export { modalState }
