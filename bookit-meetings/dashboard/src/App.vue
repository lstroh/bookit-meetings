<script setup>
import { computed } from 'vue'
import BookingDetailView from './views/BookingDetailView.vue'
import SettingsView from './views/SettingsView.vue'

const isMeetingsPage = computed( () =>
    window.location.pathname.includes( '/bookit-dashboard/app/meetings' )
)
</script>

<template>
  <div class="bm-app">
    <BookingDetailView />
    <SettingsView v-if="isMeetingsPage" />
  </div>
</template>

<style scoped>
.bm-app {
  padding: 24px;
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
</style>

