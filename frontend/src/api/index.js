import axios from 'axios'
import { useToast } from '../composables/useToast'
import { modalState } from '../composables/useModal'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json'
  }
})

api.interceptors.request.use(
  config => {
    const token = localStorage.getItem('token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  },
  error => {
    return Promise.reject(error)
  }
)

const { error: toastError } = useToast()

api.interceptors.response.use(
  response => response,
  error => {
    const status = error.response?.status

    if (status === 401) {
      localStorage.removeItem('token')
      localStorage.removeItem('user')
      if (window.location.pathname !== '/login') {
        window.location.href = '/login'
      }
      return Promise.reject(error)
    }

    const message = error.response?.data?.message ||
      error.response?.data?.error ||
      error.message ||
      '网络请求失败，请稍后重试'

    if (status === 422 || status === 403 || status >= 500) {
      modalState.title = '错误'
      modalState.message = message
      modalState.type = 'error'
      modalState.showCancel = false
      modalState.showConfirm = true
      modalState.confirmText = '确定'
      modalState.show = true
    } else {
      toastError(message)
    }

    return Promise.reject(error)
  }
)

export default api
