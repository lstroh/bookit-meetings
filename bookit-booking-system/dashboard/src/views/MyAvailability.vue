<template>
  <div class="p-4 sm:p-6 space-y-6">
    <div>
      <h1 class="text-xl sm:text-2xl font-bold text-gray-900">My Availability</h1>
      <p class="text-sm text-gray-600 mt-1">Block time off and manage your schedule</p>
    </div>

    <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
      <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
        <h2 class="text-base sm:text-lg font-semibold text-gray-900">Block Time Off</h2>
      </div>

      <form class="px-4 sm:px-6 py-4 space-y-4" @submit.prevent="handleSubmit">
        <div>
          <p class="text-sm font-medium text-gray-700 mb-2">Date Range</p>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs text-gray-600 mb-1">Start Date</label>
              <input
                v-model="form.date_from"
                type="date"
                :min="todayLondon"
                class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-2 focus:ring-primary-500"
                :class="fieldErrors.date_from ? 'border-red-300' : 'border-gray-300'"
              />
              <p v-if="fieldErrors.date_from" class="text-xs text-red-600 mt-1">{{ fieldErrors.date_from }}</p>
            </div>

            <div>
              <label class="block text-xs text-gray-600 mb-1">End Date</label>
              <input
                v-model="form.date_to"
                type="date"
                :min="form.date_from || todayLondon"
                class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-2 focus:ring-primary-500"
                :class="fieldErrors.date_to ? 'border-red-300' : 'border-gray-300'"
              />
              <p v-if="fieldErrors.date_to" class="text-xs text-red-600 mt-1">{{ fieldErrors.date_to }}</p>
            </div>
          </div>
          <p class="text-xs text-gray-500 mt-2">For a single day, set both dates the same</p>
        </div>

        <div>
          <label class="inline-flex items-center cursor-pointer">
            <input v-model="form.all_day" type="checkbox" class="sr-only peer" />
            <div class="relative w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-primary-600 transition-colors">
              <span class="absolute top-0.5 left-0.5 h-5 w-5 bg-white rounded-full transition-transform peer-checked:translate-x-5"></span>
            </div>
            <span class="ml-3 text-sm font-medium text-gray-700">All Day</span>
          </label>
        </div>

        <div v-if="!form.all_day" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs text-gray-600 mb-1">Start Time</label>
            <input
              v-model="form.start_time"
              type="time"
              class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-2 focus:ring-primary-500"
              :class="fieldErrors.start_time ? 'border-red-300' : 'border-gray-300'"
            />
            <p v-if="fieldErrors.start_time" class="text-xs text-red-600 mt-1">{{ fieldErrors.start_time }}</p>
          </div>

          <div>
            <label class="block text-xs text-gray-600 mb-1">End Time</label>
            <input
              v-model="form.end_time"
              type="time"
              class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-2 focus:ring-primary-500"
              :class="fieldErrors.end_time ? 'border-red-300' : 'border-gray-300'"
            />
            <p v-if="fieldErrors.end_time" class="text-xs text-red-600 mt-1">{{ fieldErrors.end_time }}</p>
          </div>
        </div>

        <div>
          <label class="block text-xs text-gray-600 mb-1">Reason</label>
          <select
            v-model="form.reason"
            class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-2 focus:ring-primary-500"
            :class="fieldErrors.reason ? 'border-red-300' : 'border-gray-300'"
          >
            <option value="">Select reason</option>
            <option value="vacation">Vacation</option>
            <option value="sick_leave">Sick Leave</option>
            <option value="lunch_break">Lunch Break</option>
            <option value="personal">Personal</option>
            <option value="other">Other</option>
          </select>
          <p v-if="fieldErrors.reason" class="text-xs text-red-600 mt-1">{{ fieldErrors.reason }}</p>
        </div>

        <div>
          <label class="block text-xs text-gray-600 mb-1">Notes (optional)</label>
          <textarea
            v-model="form.notes"
            rows="3"
            placeholder="Add any notes (optional)"
            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
          />
        </div>

        <div>
          <label class="block text-xs text-gray-600 mb-1">Repeat</label>
          <select
            v-model="form.repeat"
            class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-2 focus:ring-primary-500"
            :class="fieldErrors.repeat ? 'border-red-300' : 'border-gray-300'"
          >
            <option value="none">Does not repeat</option>
            <option value="daily">Repeats daily (one block per day in range)</option>
            <option value="weekly">Repeats weekly (same day of week, 8 weeks)</option>
          </select>
          <p v-if="fieldErrors.repeat" class="text-xs text-red-600 mt-1">{{ fieldErrors.repeat }}</p>
          <p v-if="form.repeat === 'weekly' && weeklyHelperText" class="text-xs text-gray-500 mt-2">
            {{ weeklyHelperText }}
          </p>
        </div>

        <div
          v-if="conflictMessage"
          class="bg-red-50 border border-red-200 rounded-lg p-3"
        >
          <p class="text-sm text-red-800">{{ conflictMessage }}</p>
        </div>

        <div>
          <button
            type="submit"
            :disabled="saving"
            class="w-full sm:w-auto px-4 py-2.5 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 disabled:opacity-50"
          >
            {{ saving ? 'Saving...' : 'Block Time Off' }}
          </button>
        </div>
      </form>
    </div>

    <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
      <div class="px-4 sm:px-6 py-4 border-b border-gray-200 flex items-center gap-2">
        <h2 class="text-base sm:text-lg font-semibold text-gray-900">Upcoming Time Off</h2>
        <span class="px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-700 rounded-full">{{ upcomingBlocks.length }}</span>
      </div>

      <div v-if="loadingBlocks" class="p-4 sm:p-6 space-y-3">
        <CardSkeleton />
        <CardSkeleton />
      </div>

      <div v-else-if="upcomingBlocks.length === 0" class="p-2">
        <EmptyState
          icon="🏖️"
          title="No time off scheduled. Use the form above to block time."
          description=""
        />
      </div>

      <div v-else class="p-4 sm:p-6 space-y-3">
        <div
          v-for="block in upcomingBlocks"
          :key="block.id"
          class="border border-gray-200 rounded-lg p-4"
        >
          <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
            <div class="min-w-0">
              <div class="flex flex-wrap items-center gap-2 mb-1">
                <p class="text-sm font-semibold text-gray-900">{{ formatDate(block.specific_date) }}</p>
                <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-blue-100 text-blue-800">
                  {{ reasonLabel(block.reason) }}
                </span>
              </div>
              <p class="text-sm text-gray-700">{{ formatBlockTime(block) }}</p>
              <p v-if="block.notes" class="text-xs text-gray-500 mt-1">{{ block.notes }}</p>
            </div>

            <button
              class="text-sm text-red-600 hover:text-red-800"
              @click="deleteBlock(block)"
            >
              Delete
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useApi } from '../composables/useApi'
import { useToast } from '../composables/useToast'
import CardSkeleton from '../components/CardSkeleton.vue'
import EmptyState from '../components/EmptyState.vue'

const api = useApi()
const { success: toastSuccess, error: toastError } = useToast()

const loadingBlocks = ref(true)
const saving = ref(false)
const conflictMessage = ref('')
const blocks = ref([])
const fieldErrors = ref({})

const form = ref(getDefaultForm())

function getDefaultForm() {
  return {
    date_from: '',
    date_to: '',
    all_day: true,
    start_time: '09:00',
    end_time: '17:00',
    reason: '',
    notes: '',
    repeat: 'none'
  }
}

const londonDateFormatter = new Intl.DateTimeFormat('en-GB', {
  timeZone: 'Europe/London',
  year: 'numeric',
  month: '2-digit',
  day: '2-digit'
})

const todayLondon = computed(() => {
  const parts = londonDateFormatter.formatToParts(new Date())
  const year = parts.find(p => p.type === 'year').value
  const month = parts.find(p => p.type === 'month').value
  const day = parts.find(p => p.type === 'day').value
  return `${year}-${month}-${day}`
})

const weeklyHelperText = computed(() => {
  if (!form.value.date_from) return ''
  const date = new Date(`${form.value.date_from}T12:00:00`)
  const dayName = date.toLocaleDateString('en-GB', { weekday: 'long' })
  return `Will create blocks every ${dayName} for 8 weeks from your start date`
})

const upcomingBlocks = computed(() => {
  return [...blocks.value]
    .filter(block => block.specific_date >= todayLondon.value)
    .sort((a, b) => a.specific_date.localeCompare(b.specific_date))
})

watch(
  () => [form.value.date_from, form.value.date_to, form.value.start_time, form.value.end_time, form.value.all_day],
  () => {
    conflictMessage.value = ''
  }
)

function validateForm() {
  const errors = {}

  if (!form.value.date_from) {
    errors.date_from = 'Start date is required'
  } else if (form.value.date_from < todayLondon.value) {
    errors.date_from = 'Start date cannot be in the past'
  }

  if (!form.value.date_to) {
    errors.date_to = 'End date is required'
  } else if (form.value.date_from && form.value.date_to < form.value.date_from) {
    errors.date_to = 'End date must be on or after start date'
  }

  if (!form.value.all_day) {
    if (!form.value.start_time) {
      errors.start_time = 'Start time is required'
    }
    if (!form.value.end_time) {
      errors.end_time = 'End time is required'
    }
    if (form.value.start_time && form.value.end_time && form.value.start_time >= form.value.end_time) {
      errors.end_time = 'End time must be after start time'
    }
  }

  if (!form.value.reason) {
    errors.reason = 'Reason is required'
  }

  if (!form.value.repeat) {
    errors.repeat = 'Repeat is required'
  }

  fieldErrors.value = errors
  return Object.keys(errors).length === 0
}

function reasonLabel(reason) {
  const labels = {
    vacation: 'Vacation',
    sick_leave: 'Sick Leave',
    lunch_break: 'Lunch Break',
    personal: 'Personal',
    other: 'Other'
  }
  return labels[reason] || 'Other'
}

function formatDate(dateString) {
  return new Date(`${dateString}T12:00:00`).toLocaleDateString('en-GB')
}

function formatBlockTime(block) {
  if (block.is_all_day) return 'All Day'
  const start = (block.start_time || '').slice(0, 5)
  const end = (block.end_time || '').slice(0, 5)
  return `${start} - ${end}`
}

async function fetchBlocks() {
  loadingBlocks.value = true
  try {
    const response = await api.get('/my-availability')
    blocks.value = response.data?.blocks || []
  } catch (err) {
    console.error('Error loading availability blocks:', err)
    toastError(err.message || 'Failed to load availability')
  } finally {
    loadingBlocks.value = false
  }
}

async function handleSubmit() {
  conflictMessage.value = ''
  if (!validateForm()) return

  saving.value = true
  try {
    const payload = {
      date_from: form.value.date_from,
      date_to: form.value.date_to,
      all_day: form.value.all_day,
      reason: form.value.reason,
      notes: form.value.notes,
      repeat: form.value.repeat
    }

    if (!form.value.all_day) {
      payload.start_time = form.value.start_time
      payload.end_time = form.value.end_time
    }

    const response = await api.post('/my-availability', payload)
    if (response.data?.success) {
      await fetchBlocks()
      form.value = getDefaultForm()
      fieldErrors.value = {}
      conflictMessage.value = ''
      toastSuccess('Time off blocked successfully')
    }
  } catch (err) {
    console.error('Error blocking time off:', err)
    if (err.status === 409) {
      conflictMessage.value = err.message || 'You have conflicting bookings during this time.'
      return
    }
    toastError(err.message || 'Failed to block time off')
  } finally {
    saving.value = false
  }
}

async function deleteBlock(block) {
  const confirmed = confirm(`Remove this time-off block for ${formatDate(block.specific_date)}?`)
  if (!confirmed) return

  try {
    const response = await api.delete(`/my-availability/${block.id}`)
    if (response.data?.success) {
      blocks.value = blocks.value.filter(item => item.id !== block.id)
      toastSuccess('Time-off block removed')
    }
  } catch (err) {
    console.error('Error deleting time-off block:', err)
    toastError(err.message || 'Failed to remove time-off block')
  }
}

onMounted(() => {
  fetchBlocks()
})
</script>
