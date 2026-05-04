<template>
  <span ref="triggerRef" class="inline-flex items-center">
    <button
      type="button"
      class="bookit-tooltip-trigger inline-flex h-4 w-4 items-center justify-center rounded-full bg-gray-300 text-[10px] font-bold leading-none text-gray-700 hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500"
      aria-label="Help"
      :aria-describedby="tooltipId"
      @mouseenter="show"
      @mouseleave="hideWithDelay"
      @focus="show"
      @blur="hide"
      @keydown.escape="hide"
    >
      ?
    </button>

    <Teleport to="body">
      <div
        v-if="isVisible"
        :id="tooltipId"
        ref="panelRef"
        role="tooltip"
        class="fixed z-[80] max-w-xs rounded-md bg-gray-900 px-3 py-2 text-xs text-white shadow-lg whitespace-pre-line"
        :style="panelStyle"
        @mouseenter="onPanelMouseEnter"
        @mouseleave="onPanelMouseLeave"
      >
        {{ content }}
      </div>
    </Teleport>
  </span>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref } from 'vue'

const props = defineProps({
  content: { type: String, required: true },
  position: {
    type: String,
    default: 'top',
    validator: (value) => ['top', 'bottom', 'left', 'right'].includes(value)
  }
})

const triggerRef = ref(null)
const panelRef = ref(null)
const isVisible = ref(false)
const panelHovered = ref(false)
const hideTimer = ref(null)
const panelStyle = ref({ top: '0px', left: '0px' })

const tooltipId = `bookit-tooltip-${Math.random().toString(36).slice(2, 11)}`

const clearHideTimer = () => {
  if (hideTimer.value) {
    clearTimeout(hideTimer.value)
    hideTimer.value = null
  }
}

const updatePosition = () => {
  if (!triggerRef.value || !panelRef.value) {
    return
  }

  const triggerRect = triggerRef.value.getBoundingClientRect()
  const panelRect = panelRef.value.getBoundingClientRect()
  const gap = 8

  let top = triggerRect.top - panelRect.height - gap
  let left = triggerRect.left + (triggerRect.width / 2) - (panelRect.width / 2)

  if (props.position === 'bottom') {
    top = triggerRect.bottom + gap
  } else if (props.position === 'left') {
    top = triggerRect.top + (triggerRect.height / 2) - (panelRect.height / 2)
    left = triggerRect.left - panelRect.width - gap
  } else if (props.position === 'right') {
    top = triggerRect.top + (triggerRect.height / 2) - (panelRect.height / 2)
    left = triggerRect.right + gap
  }

  const viewportPadding = 8
  const maxLeft = window.innerWidth - panelRect.width - viewportPadding
  const maxTop = window.innerHeight - panelRect.height - viewportPadding

  panelStyle.value = {
    top: `${Math.max(viewportPadding, Math.min(top, maxTop))}px`,
    left: `${Math.max(viewportPadding, Math.min(left, maxLeft))}px`
  }
}

const registerListeners = () => {
  window.addEventListener('scroll', updatePosition, true)
  window.addEventListener('resize', updatePosition)
}

const unregisterListeners = () => {
  window.removeEventListener('scroll', updatePosition, true)
  window.removeEventListener('resize', updatePosition)
}

const show = async () => {
  clearHideTimer()
  isVisible.value = true
  await nextTick()
  updatePosition()
  registerListeners()
}

const hide = () => {
  clearHideTimer()
  panelHovered.value = false
  isVisible.value = false
  unregisterListeners()
}

const hideWithDelay = () => {
  clearHideTimer()
  hideTimer.value = setTimeout(() => {
    if (!panelHovered.value) {
      hide()
    }
  }, 120)
}

const onPanelMouseEnter = () => {
  panelHovered.value = true
  clearHideTimer()
}

const onPanelMouseLeave = () => {
  panelHovered.value = false
  hide()
}

onBeforeUnmount(() => {
  clearHideTimer()
  unregisterListeners()
})

const content = computed(() => props.content)
</script>
