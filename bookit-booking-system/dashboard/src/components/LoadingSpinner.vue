<template>
  <div class="flex flex-col items-center justify-center" :class="containerClass">
    <div
      class="animate-spin rounded-full border-b-2"
      :class="[sizeClass, colorClass]"
    ></div>
    <p v-if="message" class="mt-3 text-sm text-gray-600">{{ message }}</p>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  size: {
    type: String,
    default: 'md',
    validator: (value) => ['sm', 'md', 'lg'].includes(value)
  },
  message: {
    type: String,
    default: ''
  },
  color: {
    type: String,
    default: 'primary'
  },
  fullHeight: {
    type: Boolean,
    default: false
  }
})

const sizeClass = computed(() => {
  const sizes = {
    sm: 'h-4 w-4',
    md: 'h-8 w-8',
    lg: 'h-12 w-12'
  }
  return sizes[props.size]
})

const colorClass = computed(() => {
  return props.color === 'white'
    ? 'border-white'
    : 'border-primary-600'
})

const containerClass = computed(() => {
  return props.fullHeight ? 'py-12' : 'py-6'
})
</script>
