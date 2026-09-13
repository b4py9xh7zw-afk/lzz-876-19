import { ref } from 'vue'

const toasts = ref([])

let toastId = 0

export function useToast() {
    const add = (message, type = 'info', duration = 3000) => {
        const id = toastId++
        const toast = {
            id,
            message,
            type,
            duration
        }

        toasts.value.push(toast)

        if (duration > 0) {
            setTimeout(() => {
                remove(id)
            }, duration)
        }

        return id
    }

    const remove = (id) => {
        const index = toasts.value.findIndex(t => t.id === id)
        if (index !== -1) {
            toasts.value.splice(index, 1)
        }
    }

    const success = (message, duration) => add(message, 'success', duration)
    const error = (message, duration) => add(message, 'error', duration)
    const warning = (message, duration) => add(message, 'warning', duration)
    const info = (message, duration) => add(message, 'info', duration)

    return {
        toasts,
        add,
        remove,
        success,
        error,
        warning,
        info
    }
}
