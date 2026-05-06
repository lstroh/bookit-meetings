<script setup>
import { RouterView } from 'vue-router'
import BookingDetailView from './views/BookingDetailView.vue'
</script>

<template>
  <div class="bm-app">
    <RouterView />
    <BookingDetailView />
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

