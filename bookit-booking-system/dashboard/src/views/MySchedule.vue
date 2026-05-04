<template>
  <div>
    <!-- Header -->
    <div class="mb-6">
      <h2 class="text-lg font-semibold text-gray-900">
        My Schedule
      </h2>
      <p v-if="!loading && !error" class="text-sm text-gray-600 mt-1">
        {{ staffName }} &mdash; {{ weekRangeLabel }}
      </p>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="space-y-3">
      <CardSkeleton v-for="i in 3" :key="i" />
    </div>

    <!-- Error State -->
    <ErrorState
      v-else-if="error"
      :title="errorTitle"
      :message="errorMessage"
      :details="errorDetails"
      :show-home="false"
      @retry="fetchSchedule"
    />

    <!-- Schedule Content -->
    <div v-else>
      <!-- Week Navigation -->
      <div class="flex items-center gap-3 mb-6">
        <button
          class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
          @click="prevWeek"
        >
          &larr; Prev Week
        </button>
        <button
          class="px-3 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition-colors"
          @click="goToday"
        >
          Today
        </button>
        <button
          class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
          @click="nextWeek"
        >
          Next Week &rarr;
        </button>
      </div>

      <!-- TODAY SECTION -->
      <div v-if="todayBookings !== null" class="mb-8">
        <h3 class="text-base font-semibold text-gray-900 mb-3">
          Today &mdash; {{ formatDayHeading(todayDate) }}
        </h3>

        <EmptyState
          v-if="todayBookings.length === 0"
          icon="🗓️"
          title="No appointments scheduled for today"
          description=""
        />

        <div v-else class="space-y-3">
          <div
            v-for="booking in todayBookings"
            :key="booking.id"
            :data-booking-id="booking.id"
            class="bg-white rounded-lg shadow p-4"
          >
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
              <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center gap-2 mb-1">
                  <span class="text-sm font-semibold text-gray-900">{{ booking.start_time }} – {{ booking.end_time }}</span>
                  <span
                    class="px-2 py-0.5 text-xs font-medium rounded-full"
                    :class="getStatusClass(booking.status)"
                  >
                    {{ formatStatus(booking.status) }}
                  </span>
                </div>
                <p class="text-sm font-semibold text-gray-900">{{ booking.service_name }}</p>
                <p class="text-sm text-gray-600">{{ booking.customer_name }}</p>
                <p class="text-xs text-gray-500">{{ booking.duration }} min</p>
              </div>
              <div
                v-if="booking.status === 'confirmed' && booking.booking_date >= todayDate"
                class="flex gap-2 flex-shrink-0"
              >
                <button
                  class="px-3 py-1.5 text-xs font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                  :disabled="!!actionLoading[booking.id]"
                  @click="handleMarkComplete(booking)"
                >
                  {{ actionLoading[booking.id] ? 'Updating...' : '✓ Mark Complete' }}
                </button>
                <button
                  class="px-3 py-1.5 text-xs font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                  :disabled="!!actionLoading[booking.id]"
                  @click="handleMarkNoShow(booking)"
                >
                  {{ actionLoading[booking.id] ? 'Updating...' : '✗ No-Show' }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- THIS WEEK SECTION -->
      <div class="mb-8">
        <h3 class="text-base font-semibold text-gray-900 mb-3">
          This Week
        </h3>

        <div class="space-y-4">
          <div
            v-for="day in weekDays"
            :key="day.date"
            class="rounded-lg overflow-hidden"
            :class="day.isToday ? 'bg-blue-50 border-l-4 border-blue-500' : 'bg-white border border-gray-200'"
          >
            <div class="px-4 py-3" :class="{ 'opacity-60': day.isPast && !day.isToday }">
              <p class="text-sm font-medium" :class="day.isToday ? 'text-blue-800' : 'text-gray-700'">
                {{ formatDayHeading(day.date) }}
              </p>

              <div v-if="day.bookings.length === 0" class="mt-2">
                <p class="text-xs text-gray-400">No appointments</p>
              </div>

              <div v-else class="mt-3 space-y-3">
                <div
                  v-for="booking in day.bookings"
                  :key="booking.id"
                  :data-booking-id="booking.id"
                  class="bg-white rounded-lg shadow p-4"
                >
                  <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                    <div class="flex-1 min-w-0">
                      <div class="flex flex-wrap items-center gap-2 mb-1">
                        <span class="text-sm font-semibold text-gray-900">{{ booking.start_time }} – {{ booking.end_time }}</span>
                        <span
                          class="px-2 py-0.5 text-xs font-medium rounded-full"
                          :class="getStatusClass(booking.status)"
                        >
                          {{ formatStatus(booking.status) }}
                        </span>
                      </div>
                      <p class="text-sm font-semibold text-gray-900">{{ booking.service_name }}</p>
                      <p class="text-sm text-gray-600">{{ booking.customer_name }}</p>
                      <p class="text-xs text-gray-500">{{ booking.duration }} min</p>
                    </div>
                    <div
                      v-if="booking.status === 'confirmed' && booking.booking_date >= todayDate"
                      class="flex gap-2 flex-shrink-0"
                    >
                      <button
                        class="px-3 py-1.5 text-xs font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        :disabled="!!actionLoading[booking.id]"
                        @click="handleMarkComplete(booking)"
                      >
                        {{ actionLoading[booking.id] ? 'Updating...' : '✓ Mark Complete' }}
                      </button>
                      <button
                        class="px-3 py-1.5 text-xs font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        :disabled="!!actionLoading[booking.id]"
                        @click="handleMarkNoShow(booking)"
                      >
                        {{ actionLoading[booking.id] ? 'Updating...' : '✗ No-Show' }}
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- UPCOMING SECTION -->
      <div>
        <div class="flex items-center gap-2 mb-3">
          <h3 class="text-base font-semibold text-gray-900">
            Upcoming &mdash; Next 7 Days
          </h3>
          <span
            v-if="upcomingBookings.length > 0"
            class="px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-700 rounded-full"
          >
            {{ upcomingBookings.length }}
          </span>
        </div>

        <EmptyState
          v-if="upcomingBookings.length === 0"
          icon="📆"
          title="No upcoming appointments"
          description=""
        />

        <div v-else class="space-y-3">
          <div
            v-for="booking in upcomingBookings"
            :key="booking.id"
            :data-booking-id="booking.id"
            class="bg-white rounded-lg shadow p-4"
          >
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
              <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center gap-2 mb-1">
                  <span class="text-sm font-semibold text-gray-900">{{ booking.start_time }} – {{ booking.end_time }}</span>
                  <span class="text-xs text-gray-500">{{ formatDayHeading(booking.booking_date) }}</span>
                  <span
                    class="px-2 py-0.5 text-xs font-medium rounded-full"
                    :class="getStatusClass(booking.status)"
                  >
                    {{ formatStatus(booking.status) }}
                  </span>
                </div>
                <p class="text-sm font-semibold text-gray-900">{{ booking.service_name }}</p>
                <p class="text-sm text-gray-600">{{ booking.customer_name }}</p>
                <p class="text-xs text-gray-500">{{ booking.duration }} min</p>
              </div>
              <div
                v-if="booking.status === 'confirmed'"
                class="flex gap-2 flex-shrink-0"
              >
                <button
                  class="px-3 py-1.5 text-xs font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                  :disabled="!!actionLoading[booking.id]"
                  @click="handleMarkComplete(booking)"
                >
                  {{ actionLoading[booking.id] ? 'Updating...' : '✓ Mark Complete' }}
                </button>
                <button
                  class="px-3 py-1.5 text-xs font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                  :disabled="!!actionLoading[booking.id]"
                  @click="handleMarkNoShow(booking)"
                >
                  {{ actionLoading[booking.id] ? 'Updating...' : '✗ No-Show' }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useApi } from '../composables/useApi'
import { useToast } from '../composables/useToast'
import ErrorState from '../components/ErrorState.vue'
import CardSkeleton from '../components/CardSkeleton.vue'
import EmptyState from '../components/EmptyState.vue'

const api = useApi()
const { success: toastSuccess, error: toastError } = useToast()

const loading = ref(true)
const error = ref(null)
const errorTitle = ref('')
const errorMessage = ref('')
const errorDetails = ref('')

const weekStart = ref(null)
const weekEnd = ref(null)
const todayDate = ref('')
const staffName = ref('')
const weekBookings = ref({})
const upcomingBookings = ref([])
const actionLoading = ref({})

// --- Date helpers (Europe/London) ---

const londonFormatter = new Intl.DateTimeFormat('en-GB', {
  timeZone: 'Europe/London',
  year: 'numeric',
  month: '2-digit',
  day: '2-digit'
})

function toLondonYMD(date) {
  const parts = londonFormatter.formatToParts(date)
  const y = parts.find(p => p.type === 'year').value
  const m = parts.find(p => p.type === 'month').value
  const d = parts.find(p => p.type === 'day').value
  return `${y}-${m}-${d}`
}

function getMondayOfWeek(date) {
  const londonDate = toLondonYMD(date)
  const local = new Date(londonDate + 'T00:00:00')
  const dow = local.getDay()
  const diff = dow === 0 ? -6 : 1 - dow
  local.setDate(local.getDate() + diff)
  return local
}

function addDays(date, days) {
  const d = new Date(date.getTime())
  d.setDate(d.getDate() + days)
  return d
}

function dateToYMD(date) {
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, '0')
  const d = String(date.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}

// --- Formatting helpers ---

const dayHeadingFormatter = new Intl.DateTimeFormat('en-GB', {
  timeZone: 'Europe/London',
  weekday: 'long',
  day: 'numeric',
  month: 'long',
  year: 'numeric'
})

function formatDayHeading(dateStr) {
  const d = new Date(dateStr + 'T12:00:00')
  return dayHeadingFormatter.format(d)
}

const getStatusClass = (status) => {
  const classes = {
    'confirmed': 'bg-green-100 text-green-800',
    'pending': 'bg-yellow-100 text-yellow-800',
    'pending_payment': 'bg-orange-100 text-orange-800',
    'completed': 'bg-blue-100 text-blue-800',
    'cancelled': 'bg-red-100 text-red-800',
    'no_show': 'bg-gray-100 text-gray-800'
  }
  return classes[status] || 'bg-gray-100 text-gray-800'
}

const formatStatus = (status) => {
  const labels = {
    'confirmed': 'Confirmed',
    'pending': 'Pending',
    'pending_payment': 'Pending Payment',
    'completed': 'Completed',
    'cancelled': 'Cancelled',
    'no_show': 'No Show'
  }
  return labels[status] || status
}

const weekRangeLabel = computed(() => {
  if (!weekStart.value || !weekEnd.value) return ''

  const startParts = new Intl.DateTimeFormat('en-GB', {
    timeZone: 'Europe/London',
    day: 'numeric'
  }).formatToParts(new Date(weekStart.value + 'T12:00:00'))
  const startDay = startParts.find(p => p.type === 'day').value

  const endDate = new Date(weekEnd.value + 'T12:00:00')
  const endParts = new Intl.DateTimeFormat('en-GB', {
    timeZone: 'Europe/London',
    day: 'numeric',
    month: 'short',
    year: 'numeric'
  }).formatToParts(endDate)

  const endDay = endParts.find(p => p.type === 'day').value
  const endMonth = endParts.find(p => p.type === 'month').value
  const endYear = endParts.find(p => p.type === 'year').value

  return `${startDay}–${endDay} ${endMonth} ${endYear}`
})

// --- Computed views ---

const todayBookings = computed(() => {
  if (!todayDate.value || !weekBookings.value[todayDate.value]) return null
  return weekBookings.value[todayDate.value]
})

const weekDays = computed(() => {
  if (!weekStart.value) return []
  const days = []
  for (let i = 0; i < 7; i++) {
    const d = addDays(new Date(weekStart.value + 'T00:00:00'), i)
    const dateStr = dateToYMD(d)
    const isToday = dateStr === todayDate.value
    const isPast = dateStr < todayDate.value
    days.push({
      date: dateStr,
      isToday,
      isPast,
      bookings: weekBookings.value[dateStr] || []
    })
  }
  return days
})

// --- Week navigation ---

const currentMonday = ref(null)

function initWeek() {
  currentMonday.value = getMondayOfWeek(new Date())
}

function prevWeek() {
  currentMonday.value = addDays(currentMonday.value, -7)
  fetchSchedule()
}

function nextWeek() {
  currentMonday.value = addDays(currentMonday.value, 7)
  fetchSchedule()
}

function goToday() {
  currentMonday.value = getMondayOfWeek(new Date())
  fetchSchedule()
}

// --- API ---

async function fetchSchedule() {
  loading.value = true
  error.value = null

  try {
    const ws = dateToYMD(currentMonday.value)
    const response = await api.get('/my-schedule', {
      params: { week_start: ws, include_upcoming: true }
    })

    if (response.data.success) {
      weekStart.value = response.data.week_start
      weekEnd.value = response.data.week_end
      todayDate.value = response.data.today
      staffName.value = response.data.staff_name
      weekBookings.value = response.data.week_bookings
      upcomingBookings.value = response.data.upcoming_bookings || []
    } else {
      throw new Error(response.data.message || 'Failed to load schedule')
    }
  } catch (err) {
    console.error('Error loading schedule:', err)
    error.value = true

    if (err.response?.status >= 500) {
      errorTitle.value = 'Server error'
      errorMessage.value = 'Our servers are experiencing issues. Please try again in a few moments.'
    } else if (!navigator.onLine) {
      errorTitle.value = 'No internet connection'
      errorMessage.value = 'Please check your internet connection and try again.'
    } else {
      errorTitle.value = 'Failed to load schedule'
      errorMessage.value = err.response?.data?.message || err.message || 'An unexpected error occurred.'
    }

    errorDetails.value = `Error: ${err.message}\nStatus: ${err.response?.status || 'N/A'}`
  } finally {
    loading.value = false
  }
}

// --- Actions ---

async function handleMarkComplete(booking) {
  const confirmed = confirm(
    `Mark this booking as complete?\n\nCustomer: ${booking.customer_name}\nService: ${booking.service_name}\nTime: ${booking.start_time}`
  )
  if (!confirmed) return

  actionLoading.value = { ...actionLoading.value, [booking.id]: true }

  try {
    const response = await api.post(`/bookings/${booking.id}/complete`)

    if (response.data.success) {
      updateBookingStatus(booking.id, 'completed')
      toastSuccess('Booking marked as complete!')
    } else {
      throw new Error(response.data.message || 'Failed to mark complete')
    }
  } catch (err) {
    console.error('Error marking complete:', err)
    toastError(err.message || 'Failed to mark booking as complete')
  } finally {
    const next = { ...actionLoading.value }
    delete next[booking.id]
    actionLoading.value = next
  }
}

async function handleMarkNoShow(booking) {
  const confirmed = confirm(
    `Mark ${booking.customer_name} as a no-show?\n\nThis will be logged. You can undo this from the Bookings list.`
  )
  if (!confirmed) return

  actionLoading.value = { ...actionLoading.value, [booking.id]: true }

  try {
    const response = await api.post(`/bookings/${booking.id}/no-show`)

    if (response.data.success) {
      updateBookingStatus(booking.id, 'no_show')
      toastSuccess('Booking marked as no-show.')
    } else {
      throw new Error(response.data.message || 'Failed to mark no-show')
    }
  } catch (err) {
    console.error('Error marking no-show:', err)
    toastError(err.message || 'Failed to mark booking as no-show')
  } finally {
    const next = { ...actionLoading.value }
    delete next[booking.id]
    actionLoading.value = next
  }
}

function updateBookingStatus(bookingId, newStatus) {
  for (const dateKey of Object.keys(weekBookings.value)) {
    const list = weekBookings.value[dateKey]
    const idx = list.findIndex(b => b.id === bookingId)
    if (idx !== -1) {
      list[idx] = { ...list[idx], status: newStatus }
      return
    }
  }
  const upIdx = upcomingBookings.value.findIndex(b => b.id === bookingId)
  if (upIdx !== -1) {
    upcomingBookings.value[upIdx] = { ...upcomingBookings.value[upIdx], status: newStatus }
  }
}

// --- Init ---

onMounted(() => {
  initWeek()
  fetchSchedule()
})
</script>
