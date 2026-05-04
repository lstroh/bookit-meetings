<template>
  <Transition name="fade">
    <div
      v-if="show"
      :role="type === 'error' ? 'alert' : 'status'"
      :aria-live="type === 'error' ? 'assertive' : 'polite'"
      class="rounded-lg p-4 mb-4"
      :class="alertClass"
    >
    <div class="flex items-start gap-3">
      <span class="text-xl" aria-hidden="true">{{ icon }}</span>
      <div class="flex-1">
        <p class="text-sm font-medium" :class="textClass">
          {{ title }}
        </p>
        <p v-if="message" class="text-sm mt-1" :class="textClass">
          {{ message }}
        </p>
      </div>
      <button
        v-if="dismissible"
        @click="$emit('close')"
        class="text-gray-400 hover:text-gray-600"
        aria-label="Dismiss alert"
      >
        <svg aria-hidden="true" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>
  </div>
  </Transition>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  type: {
    type: String,
    default: 'info',
    validator: (value) => ['success', 'error', 'warning', 'info'].includes(value)
  },
  title: {
    type: String,
    required: true
  },
  message: {
    type: String,
    default: ''
  },
  show: {
    type: Boolean,
    default: true
  },
  dismissible: {
    type: Boolean,
    default: true
  }
})

defineEmits(['close'])

const icon = computed(() => {
  const icons = { success: '\u2713', error: '\u26A0', warning: '\u26A0', info: '\u2139' }
  return icons[props.type]
})

const alertClass = computed(() => {
  const classes = {
    success: 'bg-green-50 border border-green-200',
    error: 'bg-red-50 border border-red-200',
    warning: 'bg-yellow-50 border border-yellow-200',
    info: 'bg-blue-50 border border-blue-200'
  }
  return classes[props.type]
})

const textClass = computed(() => {
  const classes = {
    success: 'text-green-800',
    error: 'text-red-800',
    warning: 'text-yellow-800',
    info: 'text-blue-800'
  }
  return classes[props.type]
})
</script>
