<template>
  <div class="fixed top-4 right-4 z-[110] flex flex-col space-y-4 pointer-events-none">
    <TransitionGroup
      enter-active-class="transform ease-out duration-300 transition"
      enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
      enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
      leave-active-class="transition ease-in duration-100"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-for="toast in toasts"
        :key="toast.id"
        class="pointer-events-auto w-full max-w-sm overflow-hidden rounded-lg shadow-lg ring-1 ring-black ring-opacity-5 pointer-events-auto"
        :class="getToastClass(toast.type)"
      >
        <div class="p-4">
          <div class="flex items-start">
            <div class="flex-shrink-0">
              <component :is="getIcon(toast.type)" class="h-6 w-6" aria-hidden="true" />
            </div>
            <div class="ml-3 w-0 flex-1 pt-0.5">
              <p class="text-sm font-medium text-gray-900">
                {{ toast.message }}
              </p>
            </div>
            <div class="ml-4 flex flex-shrink-0">
              <button
                type="button"
                @click="remove(toast.id)"
                class="inline-flex rounded-md bg-transparent text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
              >
                <span class="sr-only">Close</span>
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                  <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                </svg>
              </button>
            </div>
          </div>
        </div>
      </div>
    </TransitionGroup>
  </div>
</template>

<script setup>
import { useToast } from '../composables/useToast'
import { h } from 'vue'

const { toasts, remove } = useToast()

const getToastClass = (type) => {
  switch (type) {
    case 'success': return 'bg-white'
    case 'error': return 'bg-white'
    case 'warning': return 'bg-white'
    default: return 'bg-white'
  }
}

// Simple internal icon components
const CheckCircleIcon = h('svg', { class: 'text-green-400', fill: 'none', viewBox: '0 0 24 24', strokeWidth: '1.5', stroke: 'currentColor' }, [
  h('path', { strokeLinecap: 'round', strokeLinejoin: 'round', d: 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z' })
])

const XCircleIcon = h('svg', { class: 'text-red-400', fill: 'none', viewBox: '0 0 24 24', strokeWidth: '1.5', stroke: 'currentColor' }, [
  h('path', { strokeLinecap: 'round', strokeLinejoin: 'round', d: 'M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z' })
])

const ExclamationTriangleIcon = h('svg', { class: 'text-yellow-400', fill: 'none', viewBox: '0 0 24 24', strokeWidth: '1.5', stroke: 'currentColor' }, [
  h('path', { strokeLinecap: 'round', strokeLinejoin: 'round', d: 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z' })
])

const InformationCircleIcon = h('svg', { class: 'text-blue-400', fill: 'none', viewBox: '0 0 24 24', strokeWidth: '1.5', stroke: 'currentColor' }, [
  h('path', { strokeLinecap: 'round', strokeLinejoin: 'round', d: 'M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0z' })
])

const getIcon = (type) => {
  switch (type) {
    case 'success': return CheckCircleIcon
    case 'error': return XCircleIcon
    case 'warning': return ExclamationTriangleIcon
    default: return InformationCircleIcon
  }
}
</script>
