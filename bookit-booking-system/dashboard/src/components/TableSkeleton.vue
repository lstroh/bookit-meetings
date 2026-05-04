<template>
  <div class="animate-pulse">
    <div class="bg-gray-50 px-6 py-3 border-b border-gray-200">
      <div class="flex gap-4">
        <div v-for="i in columns" :key="'h-' + i" class="h-4 bg-gray-300 rounded" :class="getColumnWidth(i)"></div>
      </div>
    </div>

    <div v-for="i in rows" :key="'r-' + i" class="px-6 py-4 border-b border-gray-200">
      <div class="flex gap-4 items-center">
        <div v-for="j in columns" :key="'c-' + j" class="h-4 bg-gray-200 rounded" :class="getColumnWidth(j)"></div>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  rows: {
    type: Number,
    default: 5
  },
  columns: {
    type: Number,
    default: 5
  }
})

const getColumnWidth = (index) => {
  const widths = ['flex-1', 'flex-1', 'w-32', 'w-24', 'w-20']
  return widths[index - 1] || 'flex-1'
}
</script>
