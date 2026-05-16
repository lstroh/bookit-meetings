<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import BookingDetailView from './views/BookingDetailView.vue'
import SettingsView from './views/SettingsView.vue'

const isMeetingsPage = ref(
    window.location.pathname.includes('/bookit-dashboard/app/meetings')
)

function onPopState() {
    isMeetingsPage.value = window.location.pathname.includes('/bookit-dashboard/app/meetings')
}

// Core uses history.pushState for SPA navigation — patch it to detect changes
const originalPushState = history.pushState.bind(history)
history.pushState = function(...args) {
    originalPushState(...args)
    isMeetingsPage.value = window.location.pathname.includes('/bookit-dashboard/app/meetings')
}

const originalReplaceState = history.replaceState.bind(history)
history.replaceState = function(...args) {
    originalReplaceState(...args)
    isMeetingsPage.value = window.location.pathname.includes('/bookit-dashboard/app/meetings')
}

onMounted(() => window.addEventListener('popstate', onPopState))
onUnmounted(() => {
    window.removeEventListener('popstate', onPopState)
    history.pushState = originalPushState
    history.replaceState = originalReplaceState
})
</script>

<template>
  <div class="bm-app">
    <BookingDetailView />
    <SettingsView v-if="isMeetingsPage" />
  </div>
</template>

<style scoped>
.bm-app {
  color: var(--bookit-text-primary);
}
</style>

<style>
#bookit-meetings-app {
  /* Backgrounds */
  --bookit-bg-card: #ffffff;
  --bookit-bg-input: #ffffff;

  /* Text */
  --bookit-text-primary: #111827;
  --bookit-text-secondary: #6b7280;

  /* Borders */
  --bookit-border-color: #e5e7eb;

  /* Primary — map to core's already-defined variable */
  --bookit-color-primary: var(--bookit-primary);
}

/* Align with core main content (lg:ml-64); mount is outside #app so it lacks that offset */
@media (min-width: 1024px) {
  #bookit-meetings-app {
    margin-left: 16rem;
  }
}
</style>

