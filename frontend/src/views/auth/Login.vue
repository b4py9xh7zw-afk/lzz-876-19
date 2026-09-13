<template>
  <div class="min-h-screen flex items-center justify-center page-bg-gradient py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full">
      <div class="glass-card p-8">
        <div class="text-center mb-10">
          <div class="inline-flex items-center justify-center w-20 h-20 rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 shadow-lg shadow-indigo-200 mb-6 transform hover:rotate-3 transition-transform duration-300">
            <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
            </svg>
          </div>
          <h2 class="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-gray-900 to-gray-700">欢迎回来</h2>
          <p class="text-gray-500 mt-2 font-medium">876 在线考试与题库管理平台</p>
        </div>

        <form class="space-y-6" @submit.prevent="handleLogin">
          <div>
            <label for="email" class="block text-sm font-semibold text-gray-700 mb-1.5">邮箱</label>
            <input
              id="email"
              v-model="form.email"
              name="email"
              type="email"
              required
              class="input-base"
              :class="errors.email ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : ''"
              placeholder="请输入邮箱地址"
            />
            <p v-if="errors.email" class="mt-1 text-sm text-red-600">{{ errors.email }}</p>
          </div>

          <div>
            <label for="password" class="block text-sm font-semibold text-gray-700 mb-1.5">密码</label>
            <input
              id="password"
              v-model="form.password"
              name="password"
              type="password"
              required
              class="input-base"
              :class="errors.password ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : ''"
              placeholder="请输入密码"
            />
            <p v-if="errors.password" class="mt-1 text-sm text-red-600">{{ errors.password }}</p>
          </div>

          <div v-if="error" class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg p-3 flex items-center">
            <svg class="h-4 w-4 mr-2 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            {{ error }}
          </div>

          <button
            type="submit"
            :disabled="loading"
            class="btn-primary w-full shadow-lg shadow-indigo-200/50"
          >
            <span v-if="loading" class="flex items-center justify-center gap-2">
              <svg class="animate-spin h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              正在登录...
            </span>
            <span v-else>立即登录</span>
          </button>
        </form>

        <div class="mt-6 text-center">
          <router-link to="/register" class="text-sm text-indigo-600 hover:text-indigo-500 font-medium">
            没有账号？立即注册
          </router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { z } from 'zod'
import { useAuthStore } from '../../stores/auth'

const router = useRouter()
const authStore = useAuthStore()

const form = reactive({
  email: '',
  password: ''
})

const loading = ref(false)
const error = ref('')
const errors = ref({})

const loginSchema = z.object({
  email: z.string().email('请输入有效的邮箱地址'),
  password: z.string().min(6, '密码至少6个字符')
})

const handleLogin = async () => {
  errors.value = {}
  error.value = ''

  const result = loginSchema.safeParse(form)
  if (!result.success) {
    const fieldErrors = result.error.flatten().fieldErrors
    errors.value.email = fieldErrors.email?.[0] || ''
    errors.value.password = fieldErrors.password?.[0] || ''
    return
  }

  loading.value = true
  try {
    await authStore.login(form.email, form.password)
    router.push('/')
  } catch (e) {
    error.value = e.response?.data?.message || e.response?.data?.error?.[0] || '登录失败，请检查邮箱和密码'
  } finally {
    loading.value = false
  }
}
</script>
