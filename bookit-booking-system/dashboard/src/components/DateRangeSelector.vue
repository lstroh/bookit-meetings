<template>
  <div class="bg-white rounded-lg border border-gray-200 p-4">
    <div class="flex flex-wrap gap-2 mb-4">
      <button
        v-for="filter in quickFilters"
        :key="filter.key"
        @click="applyQuickFilter(filter.key)"
        class="px-3 py-1.5 text-xs font-medium rounded-full border transition-colors"
        :class="activeQuickFilter === filter.key
          ? 'bg-primary-600 text-white border-primary-600'
          : 'bg-white text-gray-600 border-gray-300 hover:border-primary-400'"
      >
        {{ filter.label }}
      </button>
    </div>

    <div class="flex flex-col sm:flex-row gap-3 items-end">
      <div class="flex-1">
        <label class="block text-xs text-gray-500 mb-1">From</label>
        <input
          v-model="localFrom"
          type="date"
          @change="onDateChange"
          class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
        />
      </div>
      <div class="flex-1">
        <label class="block text-xs text-gray-500 mb-1">To</label>
        <input
          v-model="localTo"
          type="date"
          @change="onDateChange"
          class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
        />
      </div>
      <button
        @click="applyCustomRange"
        :disabled="!!dateError || !localFrom || !localTo"
        class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700"
      >
        Apply
      </button>
    </div>
    <p v-if="dateError" class="text-xs text-red-600 mt-1">{{ dateError }}</p>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'

const emit = defineEmits(['change', 'update:activeFilter'])

const props = defineProps({
  modelFrom: { type: String, default: '' },
  modelTo: { type: String, default: '' },
  activeFilter: { type: String, default: 'this_month' }
})

const localFrom = ref(props.modelFrom)
const localTo = ref(props.modelTo)
const activeQuickFilter = computed({
  get: () => props.activeFilter,
  set: (val) => emit('update:activeFilter', val)
})
const dateError = ref('')

function toLocalDateString(date) {
  return new Intl.DateTimeFormat('en-CA', { timeZone: 'Europe/London' }).format(date)
}

const quickFilters = [
  { key: 'today', label: 'Today' },
  { key: 'yesterday', label: 'Yesterday' },
  { key: 'this_week', label: 'This Week' },
  { key: 'last_week', label: 'Last Week' },
  { key: 'this_month', label: 'This Month' },
  { key: 'last_month', label: 'Last Month' },
  { key: 'custom', label: 'Custom' }
]

function applyQuickFilter(key) {
  activeQuickFilter.value = key
  if (key === 'custom') return

  const now = new Date()
  let from
  let to

  if (key === 'today') {
    from = toLocalDateString(now)
    to = from
  } else if (key === 'yesterday') {
    const y = new Date(now)
    y.setDate(y.getDate() - 1)
    from = toLocalDateString(y)
    to = from
  } else if (key === 'this_week') {
    const d = new Date(now)
    const day = d.getDay() || 7
    d.setDate(d.getDate() - day + 1)
    from = toLocalDateString(d)
    const end = new Date(d)
    end.setDate(d.getDate() + 6)
    to = toLocalDateString(end)
  } else if (key === 'last_week') {
    const d = new Date(now)
    const day = d.getDay() || 7
    d.setDate(d.getDate() - day - 6)
    from = toLocalDateString(d)
    const end = new Date(d)
    end.setDate(d.getDate() + 6)
    to = toLocalDateString(end)
  } else if (key === 'this_month') {
    from = toLocalDateString(new Date(now.getFullYear(), now.getMonth(), 1))
    to = toLocalDateString(new Date(now.getFullYear(), now.getMonth() + 1, 0))
  } else if (key === 'last_month') {
    from = toLocalDateString(new Date(now.getFullYear(), now.getMonth() - 1, 1))
    to = toLocalDateString(new Date(now.getFullYear(), now.getMonth(), 0))
  }

  localFrom.value = from
  localTo.value = to
  emit('change', { from, to })
}

function applyCustomRange() {
  if (!localFrom.value || !localTo.value) return

  if (localFrom.value > localTo.value) {
    alert('The start date must be before the end date.')
    return
  }

  activeQuickFilter.value = 'custom'
  emit('change', { from: localFrom.value, to: localTo.value })
}

function onDateChange() {
  activeQuickFilter.value = 'custom'
  if (localFrom.value && localTo.value && localFrom.value > localTo.value) {
    dateError.value = 'Start date must be before end date.'
  } else {
    dateError.value = ''
  }
}
</script>
