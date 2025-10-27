import { ref } from 'vue'

interface Toast {
  show: boolean
  message: string
  color: 'success' | 'error' | 'warning' | 'info'
  timeout: number
}

const toast = ref<Toast>({
  show: false,
  message: '',
  color: 'error',
  timeout: 3000
})

export const useToast = () => {
  const showToast = (message: string, color: Toast['color'] = 'error', timeout = 3000) => {
    toast.value = {
      show: true,
      message,
      color,
      timeout
    }
  }

  const showSuccess = (message: string) => showToast(message, 'success')
  const showError = (message: string) => showToast(message, 'error')
  const showWarning = (message: string) => showToast(message, 'warning')
  const showInfo = (message: string) => showToast(message, 'info')

  const hideToast = () => {
    toast.value.show = false
  }

  return {
    toast,
    showToast,
    showSuccess,
    showError,
    showWarning,
    showInfo,
    hideToast
  }
}
