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

