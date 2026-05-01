<script setup>
import { RouterView } from 'vue-router'
</script>

<template>
  <div class="bm-app">
    <RouterView />
  </div>
</template>

<style scoped>
.bm-app {
  padding: 24px;
  color: var(--bookit-text-primary);
}
</style>

