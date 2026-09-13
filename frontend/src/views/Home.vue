<template>
  <div class="space-y-8">
    <div class="hero-section relative overflow-hidden shadow-xl shadow-indigo-200">
      <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between">
        <div>
          <h1 class="text-3xl font-bold mb-2 text-white">欢迎使用在线考试系统</h1>
          <p class="text-indigo-100 text-lg opacity-90">{{ greeting }}，{{ authStore.user?.username || '用户' }}</p>
          <div class="mt-4 inline-flex items-center px-3 py-1 rounded-full bg-white/20 border border-white/30 backdrop-blur-md text-sm text-white">
            <span class="w-2 h-2 rounded-full bg-green-400 mr-2 animate-pulse"></span>
            {{ roleText }}
          </div>
        </div>
        <div class="hidden md:block">
          <div class="w-24 h-24 bg-white/10 rounded-2xl border border-white/20 backdrop-blur-sm flex items-center justify-center shadow-inner hover:scale-105 transition-transform duration-300">
            <svg class="w-12 h-12 text-white opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
            </svg>
          </div>
        </div>
      </div>
    </div>

    <div>
      <h2 class="text-lg font-bold text-gray-800 mb-6 flex items-center">
        <span class="w-1.5 h-6 bg-indigo-500 rounded-full mr-3 shadow-sm shadow-indigo-300"></span>
        学生功能
      </h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <router-link to="/exams" class="card-base card-accent-left card-accent-blue p-6 group h-full hover:bg-blue-50/10 dark:hover:bg-blue-900/10">
          <div class="flex items-start h-full">
            <div class="flex-shrink-0 w-14 h-14 bg-blue-100/80 rounded-2xl flex items-center justify-center mr-5 group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300 shadow-sm">
              <svg class="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
            </div>
            <div class="flex-1 flex flex-col justify-between">
              <div>
                <h3 class="text-xl font-bold text-gray-900 mb-2 group-hover:text-blue-700 transition-colors">在线考试</h3>
                <p class="text-gray-500 text-sm leading-relaxed">浏览可选试卷，进入考场进行在线答题，系统自动评分交卷。</p>
              </div>
              <div class="mt-4 flex items-center text-sm font-medium text-blue-600 opacity-0 group-hover:opacity-100 transform translate-y-2 group-hover:translate-y-0 transition-all duration-300">
                进入考试 <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" /></svg>
              </div>
            </div>
          </div>
        </router-link>
        
        <router-link to="/records" class="card-base card-accent-left card-accent-green p-6 group h-full hover:bg-green-50/10 dark:hover:bg-green-900/10">
          <div class="flex items-start h-full">
            <div class="flex-shrink-0 w-14 h-14 bg-emerald-100/80 rounded-2xl flex items-center justify-center mr-5 group-hover:scale-110 group-hover:-rotate-3 transition-transform duration-300 shadow-sm">
              <svg class="w-7 h-7 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
              </svg>
            </div>
            <div class="flex-1 flex flex-col justify-between">
              <div>
                <h3 class="text-xl font-bold text-gray-900 mb-2 group-hover:text-emerald-700 transition-colors">我的成绩</h3>
                <p class="text-gray-500 text-sm leading-relaxed">查看历史考试记录、得分详情及个人能力分析报告。</p>
              </div>
              <div class="mt-4 flex items-center text-sm font-medium text-emerald-600 opacity-0 group-hover:opacity-100 transform translate-y-2 group-hover:translate-y-0 transition-all duration-300">
                查看成绩 <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" /></svg>
              </div>
            </div>
          </div>
        </router-link>
      </div>
    </div>

    <div v-if="authStore.isTeacher" class="pt-8 border-t border-gray-200/60">
      <h2 class="text-lg font-bold text-gray-800 mb-6 flex items-center">
        <span class="w-1.5 h-6 bg-purple-500 rounded-full mr-3 shadow-sm shadow-purple-300"></span>
        教师管理
      </h2>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <router-link to="/questions" class="card-base card-accent-left card-accent-purple p-6 group h-full hover:bg-purple-50/10">
          <div class="flex items-start h-full">
            <div class="flex-shrink-0 w-14 h-14 bg-purple-100/80 rounded-2xl flex items-center justify-center mr-5 group-hover:scale-110 group-hover:-rotate-3 transition-transform duration-300 shadow-sm">
              <svg class="w-7 h-7 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
              </svg>
            </div>
            <div class="flex-1 flex flex-col justify-between">
              <div>
                <h3 class="text-xl font-bold text-gray-900 mb-2 group-hover:text-purple-700 transition-colors">题库管理</h3>
                <p class="text-gray-500 text-sm leading-relaxed">创建、编辑和管理考试题目资源。</p>
              </div>
              <div class="mt-4 flex items-center text-sm font-medium text-purple-600 opacity-0 group-hover:opacity-100 transform translate-y-2 group-hover:translate-y-0 transition-all duration-300">
                管理题库 <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" /></svg>
              </div>
            </div>
          </div>
        </router-link>
        
        <router-link to="/exam-papers" class="card-base card-accent-left card-accent-orange p-6 group h-full hover:bg-orange-50/10">
          <div class="flex items-start h-full">
            <div class="flex-shrink-0 w-14 h-14 bg-orange-100/80 rounded-2xl flex items-center justify-center mr-5 group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300 shadow-sm">
              <svg class="w-7 h-7 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
              </svg>
            </div>
            <div class="flex-1 flex flex-col justify-between">
              <div>
                <h3 class="text-xl font-bold text-gray-900 mb-2 group-hover:text-orange-700 transition-colors">试卷管理</h3>
                <p class="text-gray-500 text-sm leading-relaxed">组卷、发布考试及管理试卷设置。</p>
              </div>
              <div class="mt-4 flex items-center text-sm font-medium text-orange-600 opacity-0 group-hover:opacity-100 transform translate-y-2 group-hover:translate-y-0 transition-all duration-300">
                管理试卷 <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" /></svg>
              </div>
            </div>
          </div>
        </router-link>
        
        <router-link v-if="authStore.isAdmin" to="/statistics" class="card-base card-accent-left card-accent-blue p-6 group h-full hover:bg-sky-50/10">
          <div class="flex items-start h-full">
            <div class="flex-shrink-0 w-14 h-14 bg-sky-100/80 rounded-2xl flex items-center justify-center mr-5 group-hover:scale-110 group-hover:-rotate-3 transition-transform duration-300 shadow-sm">
              <svg class="w-7 h-7 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
            </div>
            <div class="flex-1 flex flex-col justify-between">
              <div>
                <h3 class="text-xl font-bold text-gray-900 mb-2 group-hover:text-sky-700 transition-colors">数据统计</h3>
                <p class="text-gray-500 text-sm leading-relaxed">查看系统整体运行数据及分析图表。</p>
              </div>
              <div class="mt-4 flex items-center text-sm font-medium text-sky-600 opacity-0 group-hover:opacity-100 transform translate-y-2 group-hover:translate-y-0 transition-all duration-300">
                查看报表 <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" /></svg>
              </div>
            </div>
          </div>
        </router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useAuthStore } from '../stores/auth'

const authStore = useAuthStore()

const greeting = computed(() => {
  const hour = new Date().getHours()
  if (hour < 6) return '夜深了'
  if (hour < 12) return '早上好'
  if (hour < 14) return '中午好'
  if (hour < 18) return '下午好'
  return '晚上好'
})

const roleText = computed(() => {
  if (authStore.isAdmin) return '您拥有管理员权限，可以管理所有功能'
  if (authStore.isTeacher) return '您拥有教师权限，可以管理题库和试卷'
  return '您可以参加在线考试并查看成绩'
})
</script>
