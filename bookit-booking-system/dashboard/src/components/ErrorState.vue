<template>
  <div class="flex flex-col items-center justify-center py-12 px-4">
    <div class="w-16 h-16 mb-4 text-5xl" aria-hidden="true">
      &#x26A0;&#xFE0F;
    </div>

    <h3 class="text-lg font-semibold text-gray-900 mb-2">
      {{ title }}
    </h3>

    <p class="text-sm text-gray-600 text-center max-w-md mb-4">
      {{ message }}
    </p>

    <details v-if="details" class="mb-6">
      <summary class="text-xs text-gray-500 cursor-pointer hover:text-gray-700 select-none">
        Show technical details
      </summary>
      <pre class="mt-2 p-3 bg-gray-100 rounded text-xs text-gray-700 overflow-x-auto max-w-md whitespace-pre-wrap">{{ details }}</pre>
    </details>

    <div class="flex flex-col sm:flex-row gap-3">
      <button
        @click="$emit('retry')"
        class="px-4 py-2.5 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition-colors"
      >
        Try Again
      </button>
      <button
        v-if="showHome"
        @click="router.push('/')"
        class="px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
      >
        Go to Dashboard
      </button>
    </div>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router'

const router = useRouter()

defineProps({
  title: {
    type: String,
    default: 'Something went wrong'
  },
  message: {
    type: String,
    default: 'An unexpected error occurred. Please try again or contact support if the problem persists.'
  },
  details: {
    type: String,
    default: ''
  },
  showHome: {
    type: Boolean,
    default: true
  }
})

defineEmits(['retry'])
</script>
