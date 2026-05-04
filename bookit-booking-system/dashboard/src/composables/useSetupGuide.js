import { ref, computed } from 'vue'
import { useApi } from './useApi'

const setupGuideStatus = ref(null)
const currentStep = ref(1)
const stepsCompleted = ref([])
const isLoading = ref(false)

const showGuide = computed(() => setupGuideStatus.value === 'pending')

const normalizeStep = (value, fallback = 1) => {
  const parsed = Number(value)
  if (!Number.isInteger(parsed) || parsed < 1 || parsed > 4) {
    return fallback
  }
  return parsed
}

const applyStatus = (payload = {}) => {
  const status = ['pending', 'completed', 'dismissed'].includes(payload.status)
    ? payload.status
    : 'pending'

  const completed = Array.isArray(payload.steps_completed)
    ? payload.steps_completed
      .map(step => normalizeStep(step, 0))
      .filter(step => step >= 1 && step <= 4)
    : []

  setupGuideStatus.value = status
  currentStep.value = normalizeStep(payload.current_step, 1)
  stepsCompleted.value = [...new Set(completed)]
}

export const useSetupGuide = () => {
  const api = useApi()
  const endpoint = `${window.BOOKIT_DASHBOARD.restBase}setup-guide/status`

  const fetchStatus = async () => {
    isLoading.value = true

    try {
      const response = await api.get(endpoint)
      applyStatus(response?.data || {})
      return response?.data || null
    } catch {
      // Never block dashboard load if setup guide status fails.
      setupGuideStatus.value = 'dismissed'
      currentStep.value = 1
      stepsCompleted.value = []
      return null
    } finally {
      isLoading.value = false
    }
  }

  const markComplete = async () => {
    try {
      const response = await api.post(endpoint, { action: 'complete' })
      applyStatus(response?.data || {})
      return response?.data || null
    } catch {
      return null
    }
  }

  const dismiss = async () => {
    try {
      const response = await api.post(endpoint, { action: 'dismiss' })
      applyStatus(response?.data || {})
      return response?.data || null
    } catch {
      return null
    }
  }

  const updateStep = async (step, stepDone) => {
    const payload = {
      action: 'update_step'
    }

    if (Number.isInteger(step) && step >= 1 && step <= 4) {
      payload.current_step = step
    }

    if (Number.isInteger(stepDone) && stepDone >= 1 && stepDone <= 4) {
      payload.step_done = stepDone
    }

    try {
      const response = await api.post(endpoint, payload)
      applyStatus(response?.data || {})
      return response?.data || null
    } catch {
      return null
    }
  }

  return {
    setupGuideStatus,
    currentStep,
    stepsCompleted,
    isLoading,
    showGuide,
    fetchStatus,
    markComplete,
    dismiss,
    updateStep
  }
}
