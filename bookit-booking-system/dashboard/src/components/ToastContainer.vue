<template>
  <div class="fixed top-4 right-4 z-[60] space-y-2" aria-live="polite">
    <TransitionGroup name="list">
      <div
        v-for="toast in toasts"
        :key="toast.id"
        class="min-w-[300px] max-w-md px-4 py-3 rounded-lg shadow-lg border flex items-start gap-3"
        :class="toastClass(toast.type)"
        role="status"
      >
        <span class="text-lg" aria-hidden="true">{{ toastIcon(toast.type) }}</span>
        <p class="text-sm font-medium flex-1">{{ toast.message }}</p>
        <button
          @click="removeToast(toast.id)"
          class="text-gray-400 hover:text-gray-600"
          aria-label="Dismiss"
        >
          <svg aria-hidden="true" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>

<script setup>
import { useToast } from '../composables/useToast'

const { toasts, removeToast } = useToast()

const toastClass = (type) => {
  const classes = {
    success: 'bg-green-50 border-green-200 text-green-800',
    error: 'bg-red-50 border-red-200 text-red-800',
    info: 'bg-blue-50 border-blue-200 text-blue-800'
  }
  return classes[type] || classes.info
}

const toastIcon = (type) => {
  const icons = { success: '\u2713', error: '\u2717', info: '\u2139' }
  return icons[type] || icons.info
}
</script>
