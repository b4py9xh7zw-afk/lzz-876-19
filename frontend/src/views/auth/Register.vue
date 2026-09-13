<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
      <div>
        <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">注册考试系统</h2>
      </div>
      <form class="mt-8 space-y-6" @submit.prevent="handleRegister">
        <div class="rounded-md shadow-sm -space-y-px">
          <div>
            <label for="username" class="sr-only">用户名</label>
            <input v-model="form.username" id="username" name="username" type="text" required class="appearance-none rounded-none relative block w-full px-3 py-2 border placeholder-gray-500 text-gray-900 rounded-t-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm" :class="errors.username ? 'border-red-500' : 'border-gray-300'" placeholder="用户名" />
            <p v-if="errors.username" class="mt-1 text-sm text-red-600">{{ errors.username }}</p>
          </div>
          <div>
            <label for="email" class="sr-only">邮箱</label>
            <input v-model="form.email" id="email" name="email" type="email" required class="appearance-none rounded-none relative block w-full px-3 py-2 border placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm" :class="errors.email ? 'border-red-500' : 'border-gray-300'" placeholder="邮箱" />
            <p v-if="errors.email" class="mt-1 text-sm text-red-600">{{ errors.email }}</p>
          </div>
          <div>
            <label for="real_name" class="sr-only">真实姓名</label>
            <input v-model="form.real_name" id="real_name" name="real_name" type="text" class="appearance-none rounded-none relative block w-full px-3 py-2 border placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm" :class="errors.real_name ? 'border-red-500' : 'border-gray-300'" placeholder="真实姓名（可选）" />
            <p v-if="errors.real_name" class="mt-1 text-sm text-red-600">{{ errors.real_name }}</p>
          </div>
          <div>
            <label for="password" class="sr-only">密码</label>
            <input v-model="form.password" id="password" name="password" type="password" required class="appearance-none rounded-none relative block w-full px-3 py-2 border placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm" :class="errors.password ? 'border-red-500' : 'border-gray-300'" placeholder="密码" />
            <p v-if="errors.password" class="mt-1 text-sm text-red-600">{{ errors.password }}</p>
          </div>
          <div>
            <label for="password_confirmation" class="sr-only">确认密码</label>
            <input v-model="form.password_confirmation" id="password_confirmation" name="password_confirmation" type="password" required class="appearance-none rounded-none relative block w-full px-3 py-2 border placeholder-gray-500 text-gray-900 rounded-b-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm" :class="errors.password_confirmation ? 'border-red-500' : 'border-gray-300'" placeholder="确认密码" />
            <p v-if="errors.password_confirmation" class="mt-1 text-sm text-red-600">{{ errors.password_confirmation }}</p>
          </div>
        </div>
        <div v-if="error" class="text-red-600 text-sm text-center">{{ error }}</div>
        <div>
          <button type="submit" :disabled="loading" class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50">
            {{ loading ? '注册中...' : '注册' }}
          </button>
        </div>
      </form>
      <div class="text-center">
        <router-link to="/login" class="text-indigo-600 hover:text-indigo-500">已有账号？立即登录</router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { z } from 'zod'

const router = useRouter()
const authStore = useAuthStore()

const form = reactive({
  username: '',
  email: '',
  real_name: '',
  password: '',
  password_confirmation: ''
})

const loading = ref(false)
const error = ref('')
const errors = ref({})

const registerSchema = z.object({
  username: z.string().min(2, '用户名至少2个字符').max(50, '用户名最多50个字符'),
  email: z.string().email('请输入有效的邮箱地址'),
  real_name: z.string().max(50, '真实姓名最多50个字符').optional().or(z.literal('')),
  password: z.string().min(6, '密码至少6个字符'),
  password_confirmation: z.string()
}).refine(data => data.password === data.password_confirmation, {
  message: '两次输入的密码不一致',
  path: ['password_confirmation'],
})

const handleRegister = async () => {
  errors.value = {}
  error.value = ''

  const result = registerSchema.safeParse(form)
  if (!result.success) {
    const fieldErrors = result.error.flatten().fieldErrors
    errors.value.username = fieldErrors.username?.[0] || ''
    errors.value.email = fieldErrors.email?.[0] || ''
    errors.value.real_name = fieldErrors.real_name?.[0] || ''
    errors.value.password = fieldErrors.password?.[0] || ''
    errors.value.password_confirmation = fieldErrors.password_confirmation?.[0] || ''
    return
  }

  loading.value = true
  try {
    await authStore.register(form)
    router.push('/')
  } catch (e) {
    const errorData = e.response?.data
    if (errorData?.message) {
      error.value = errorData.message
    } else if (errorData?.errors) {
      const firstError = Object.values(errorData.errors)[0]
      error.value = Array.isArray(firstError) ? firstError[0] : firstError
    } else {
      error.value = '注册失败，请稍后重试'
    }
  } finally {
    loading.value = false
  }
}
</script>
