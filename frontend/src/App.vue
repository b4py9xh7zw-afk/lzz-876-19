<template>
  <div class="min-h-screen page-bg-gradient overflow-x-hidden">
    <ToastContainer />
    <!-- System Alert Modal (High Priority: backdrop z-[90], panel z-[100]) -->
    <Teleport to="body">
      <Transition
        enter-active-class="ease-out duration-200"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="ease-in duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div v-if="modalState.show" class="fixed inset-0 z-[90]" aria-labelledby="modal-title" role="dialog" aria-modal="true">
          <!-- Backdrop -->
          <div class="fixed inset-0 z-[90] bg-gray-600/75 backdrop-blur-sm transition-opacity" @click="modalState.showCancel ? handleCancel() : handleConfirm()"></div>

          <!-- Modal Panel -->
          <div class="fixed inset-0 z-[100] overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
              <Transition
                enter-active-class="ease-out duration-200"
                enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                enter-to-class="opacity-100 translate-y-0 sm:scale-100"
                leave-active-class="ease-in duration-150"
                leave-from-class="opacity-100 translate-y-0 sm:scale-100"
                leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
              >
                <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-gray-100">
                  <div class="bg-white px-6 pb-6 pt-6">
                    <div class="flex items-start space-x-4">
                      <!-- Icon -->
                      <div class="flex-shrink-0">
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full" :class="iconBgClass">
                          <svg class="h-6 w-6" :class="iconClass" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path v-if="modalState.type === 'success'" stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            <path v-else-if="modalState.type === 'error'" stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                            <path v-else-if="modalState.type === 'warning'" stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126z" />
                            <path v-else stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                          </svg>
                        </div>
                      </div>
                      <!-- Content -->
                      <div class="flex-1 min-w-0 mt-1">
                        <h3 id="modal-title" class="text-lg font-bold leading-6 text-gray-900 tracking-tight">
                          {{ modalState.title }}
                        </h3>
                        <div class="mt-2">
                          <p class="text-sm text-gray-500 leading-relaxed text-left break-words">
                            {{ modalState.message }}
                          </p>
                        </div>
                      </div>
                    </div>
                  </div>
                  <!-- Actions -->
                  <div class="bg-gray-50/50 px-6 py-4 flex flex-col sm:flex-row-reverse sm:gap-3">
                    <button
                      v-if="modalState.showConfirm"
                      type="button"
                      class="inline-flex w-full justify-center rounded-lg px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-all focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 sm:w-auto min-w-[80px]"
                      :class="confirmButtonClass"
                      @click="handleConfirm"
                    >
                      {{ modalState.confirmText || '确定' }}
                    </button>
                    <button
                      v-if="modalState.showCancel"
                      type="button"
                      class="mt-3 inline-flex w-full justify-center rounded-lg bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 transition-all sm:mt-0 sm:w-auto min-w-[80px]"
                      @click="handleCancel"
                    >
                      {{ modalState.cancelText || '取消' }}
                    </button>
                  </div>
                </div>
              </Transition>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
    <nav v-if="authStore.isLoggedIn" class="bg-white/80 backdrop-blur-md sticky top-0 z-40 border-b border-gray-100/50 shadow-sm transition-all duration-300">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
          <div class="flex items-center">
            <router-link to="/" class="flex items-center space-x-3 group">
              <div class="w-9 h-9 bg-indigo-600 rounded-xl shadow-lg shadow-indigo-200 flex items-center justify-center transform group-hover:scale-110 transition-transform duration-200">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
              </div>
              <span class="text-xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-indigo-600 to-indigo-500">考试系统</span>
            </router-link>
            <div class="hidden md:flex ml-10 space-x-2">
              <router-link 
                to="/exams" 
                class="nav-link"
                :class="{ 'nav-link-active': $route.path === '/exams' }"
              >
                在线考试
              </router-link>
              <router-link 
                to="/records" 
                class="nav-link"
                :class="{ 'nav-link-active': $route.path === '/records' }"
              >
                我的成绩
              </router-link>
              <router-link 
                v-if="authStore.isTeacher" 
                to="/questions" 
                class="nav-link"
                :class="{ 'nav-link-active': $route.path === '/questions' }"
              >
                题库管理
              </router-link>
              <router-link 
                v-if="authStore.isTeacher" 
                to="/exam-papers" 
                class="nav-link"
                :class="{ 'nav-link-active': $route.path === '/exam-papers' }"
              >
                试卷管理
              </router-link>
              <router-link 
                v-if="authStore.isAdmin" 
                to="/statistics" 
                class="nav-link"
                :class="{ 'nav-link-active': $route.path === '/statistics' }"
              >
                数据统计
              </router-link>
            </div>
          </div>
          <div class="flex items-center space-x-5">
            <div class="flex items-center space-x-3 bg-gray-50 rounded-full py-1 pl-1 pr-4 border border-gray-100">
              <div class="w-8 h-8 bg-indigo-100 rounded-full flex items-center justify-center shadow-inner">
                <span class="text-indigo-600 font-bold text-sm">{{ authStore.user?.username?.charAt(0)?.toUpperCase() }}</span>
              </div>
              <span class="text-gray-700 font-medium text-sm hidden sm:inline">{{ authStore.user?.username }}</span>
            </div>
            <button 
              @click="logout" 
              class="flex items-center space-x-1 px-3 py-2 text-gray-500 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all duration-200 group"
            >
              <svg class="w-5 h-5 group-hover:-translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
              </svg>
            </button>
          </div>
        </div>
      </div>
      <div class="md:hidden border-t border-gray-100">
        <div class="px-2 py-2 space-y-1">
          <router-link to="/exams" class="mobile-nav-link" :class="{ 'mobile-nav-link-active': $route.path === '/exams' }">在线考试</router-link>
          <router-link to="/records" class="mobile-nav-link" :class="{ 'mobile-nav-link-active': $route.path === '/records' }">我的成绩</router-link>
          <router-link v-if="authStore.isTeacher" to="/questions" class="mobile-nav-link" :class="{ 'mobile-nav-link-active': $route.path === '/questions' }">题库管理</router-link>
          <router-link v-if="authStore.isTeacher" to="/exam-papers" class="mobile-nav-link" :class="{ 'mobile-nav-link-active': $route.path === '/exam-papers' }">试卷管理</router-link>
          <router-link v-if="authStore.isAdmin" to="/statistics" class="mobile-nav-link" :class="{ 'mobile-nav-link-active': $route.path === '/statistics' }">数据统计</router-link>
        </div>
      </div>
    </nav>
    <main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
      <router-view />
    </main>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useAuthStore } from './stores/auth'
import ToastContainer from './components/ToastContainer.vue'
import { useModal } from './composables/useModal'

const authStore = useAuthStore()
const { modalState, handleConfirm, handleCancel } = useModal()

const logout = async () => {
  await authStore.logout()
  window.location.href = '/login'
}

const iconBgClass = computed(() => {
  switch (modalState.type) {
    case 'success': return 'bg-green-100'
    case 'warning': return 'bg-yellow-100'
    case 'error': return 'bg-red-100'
    default: return 'bg-blue-100'
  }
})

const iconClass = computed(() => {
  switch (modalState.type) {
    case 'success': return 'text-green-600'
    case 'warning': return 'text-yellow-600'
    case 'error': return 'text-red-600'
    default: return 'text-blue-600'
  }
})

const confirmButtonClass = computed(() => {
  switch (modalState.type) {
    case 'success': return 'bg-green-600 hover:bg-green-500'
    case 'warning': return 'bg-yellow-600 hover:bg-yellow-500'
    case 'error': return 'bg-red-600 hover:bg-red-500'
    default: return 'bg-indigo-600 hover:bg-indigo-500'
  }
})
</script>

<style scoped>
.nav-link {
  @apply px-4 py-2 text-sm text-gray-600 hover:text-indigo-600 font-medium rounded-lg transition-all duration-200 hover:bg-gray-50;
}

.nav-link-active {
  @apply text-indigo-600 bg-indigo-50 shadow-sm font-semibold;
}

.mobile-nav-link {
  @apply block px-4 py-2 text-gray-600 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg font-medium transition-colors duration-200;
}

.mobile-nav-link-active {
  @apply text-indigo-600 bg-indigo-50 border-l-4 border-indigo-500 rounded-l-none;
}
</style>
