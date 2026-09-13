<template>
  <Teleport to="body">
    <Transition
      enter-active-class="ease-out duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="ease-in duration-150"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="modelValue"
        class="fixed inset-0 z-50 overflow-y-auto"
        aria-labelledby="modal-title"
        role="dialog"
        aria-modal="true"
      >
        <div class="fixed inset-0 bg-gray-600/75 backdrop-blur-sm transition-opacity" @click="handleOverlayClick"></div>

        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
          <Transition
            enter-active-class="ease-out duration-200"
            enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            enter-to-class="opacity-100 translate-y-0 sm:scale-100"
            leave-active-class="ease-in duration-150"
            leave-from-class="opacity-100 translate-y-0 sm:scale-100"
            leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
          >
            <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-gray-100">
              <div class="bg-white px-6 pb-6 pt-6">
                <div class="flex items-start space-x-4">
                  <div
                    class="flex-shrink-0 mx-auto flex h-12 w-12 items-center justify-center rounded-full sm:mx-0"
                    :class="iconBgClass"
                  >
                    <component :is="iconComponent" class="h-6 w-6" aria-hidden="true" />
                  </div>
                  <div class="flex-1 min-w-0 mt-1 text-center sm:text-left">
                    <h3 id="modal-title" class="text-lg font-bold leading-6 text-gray-900 tracking-tight">
                      {{ title }}
                    </h3>
                    <div class="mt-2">
                      <p class="text-sm text-gray-500 leading-relaxed text-left">
                        {{ message }}
                      </p>
                    </div>
                  </div>
                </div>
              </div>
              <div class="bg-gray-50/50 px-6 py-4 flex flex-col sm:flex-row-reverse sm:gap-3">
                <button
                  v-if="showConfirm"
                  type="button"
                  class="inline-flex w-full justify-center rounded-lg px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-all focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 sm:w-auto min-w-[80px]"
                  :class="confirmButtonClass"
                  @click="handleConfirm"
                >
                  {{ confirmText }}
                </button>
                <button
                  v-if="showCancel"
                  type="button"
                  class="mt-3 inline-flex w-full justify-center rounded-lg bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 transition-all sm:mt-0 sm:w-auto min-w-[80px]"
                  @click="handleCancel"
                >
                  {{ cancelText }}
                </button>
              </div>
            </div>
          </Transition>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed, h } from 'vue'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false
  },
  title: {
    type: String,
    default: ''
  },
  message: {
    type: String,
    default: ''
  },
  type: {
    type: String,
    default: 'info',
    validator: (value) => ['info', 'success', 'warning', 'error'].includes(value)
  },
  showCancel: {
    type: Boolean,
    default: false
  },
  showConfirm: {
    type: Boolean,
    default: true
  },
  confirmText: {
    type: String,
    default: '确定'
  },
  cancelText: {
    type: String,
    default: '取消'
  },
  closeOnOverlay: {
    type: Boolean,
    default: true
  }
})

const emit = defineEmits(['update:modelValue', 'confirm', 'cancel'])

const iconBgClass = computed(() => {
  switch (props.type) {
    case 'success': return 'bg-green-100'
    case 'warning': return 'bg-yellow-100'
    case 'error': return 'bg-red-100'
    default: return 'bg-blue-100'
  }
})

const confirmButtonClass = computed(() => {
  switch (props.type) {
    case 'success': return 'bg-green-600 hover:bg-green-500'
    case 'warning': return 'bg-yellow-600 hover:bg-yellow-500'
    case 'error': return 'bg-red-600 hover:bg-red-500'
    default: return 'bg-indigo-600 hover:bg-indigo-500'
  }
})

const SuccessIcon = h('svg', { class: 'text-green-600', fill: 'none', viewBox: '0 0 24 24', strokeWidth: '1.5', stroke: 'currentColor' }, [
  h('path', { strokeLinecap: 'round', strokeLinejoin: 'round', d: 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z' })
])

const WarningIcon = h('svg', { class: 'text-yellow-600', fill: 'none', viewBox: '0 0 24 24', strokeWidth: '1.5', stroke: 'currentColor' }, [
  h('path', { strokeLinecap: 'round', strokeLinejoin: 'round', d: 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z' })
])

const ErrorIcon = h('svg', { class: 'text-red-600', fill: 'none', viewBox: '0 0 24 24', strokeWidth: '1.5', stroke: 'currentColor' }, [
  h('path', { strokeLinecap: 'round', strokeLinejoin: 'round', d: 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z' })
])

const InfoIcon = h('svg', { class: 'text-blue-600', fill: 'none', viewBox: '0 0 24 24', strokeWidth: '1.5', stroke: 'currentColor' }, [
  h('path', { strokeLinecap: 'round', strokeLinejoin: 'round', d: 'M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0z' })
])

const iconComponent = computed(() => {
  switch (props.type) {
    case 'success': return SuccessIcon
    case 'warning': return WarningIcon
    case 'error': return ErrorIcon
    default: return InfoIcon
  }
})

const handleConfirm = () => {
  emit('confirm')
  emit('update:modelValue', false)
}

const handleCancel = () => {
  emit('cancel')
  emit('update:modelValue', false)
}

const handleOverlayClick = () => {
  if (props.closeOnOverlay) {
    emit('update:modelValue', false)
  }
}
</script>
