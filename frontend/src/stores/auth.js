import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '../api'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(JSON.parse(localStorage.getItem('user') || 'null'))
  const token = ref(localStorage.getItem('token') || null)

  const isLoggedIn = computed(() => !!token.value)
  const isAdmin = computed(() => user.value?.role === 'admin')
  const isTeacher = computed(() => user.value?.role === 'admin' || user.value?.role === 'teacher')

  async function login(email, password) {
    const response = await api.post('/auth/login', { email, password })
    user.value = response.data.user
    token.value = response.data.token
    localStorage.setItem('user', JSON.stringify(user.value))
    localStorage.setItem('token', token.value)
    return response.data
  }

  async function register(data) {
    const response = await api.post('/auth/register', data)
    user.value = response.data.user
    token.value = response.data.token
    localStorage.setItem('user', JSON.stringify(user.value))
    localStorage.setItem('token', token.value)
    return response.data
  }

  async function logout() {
    try {
      await api.post('/auth/logout')
    } catch (e) {
      console.error('Logout error:', e)
    }
    user.value = null
    token.value = null
    localStorage.removeItem('user')
    localStorage.removeItem('token')
  }

  async function fetchUser() {
    if (!token.value) return
    try {
      const response = await api.get('/auth/me')
      user.value = response.data.user
      localStorage.setItem('user', JSON.stringify(user.value))
    } catch (e) {
      if (e.response?.status === 401) {
        logout()
      }
    }
  }

  return {
    user,
    token,
    isLoggedIn,
    isAdmin,
    isTeacher,
    login,
    register,
    logout,
    fetchUser
  }
})
