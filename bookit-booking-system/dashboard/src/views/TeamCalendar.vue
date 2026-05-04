<template>
  <div>
    <div class="mb-6">
      <h2 class="text-lg font-semibold text-gray-900">
        Team Calendar
      </h2>
    </div>

    <div v-if="loading" class="space-y-3">
      <CardSkeleton v-for="i in 4" :key="i" />
    </div>

    <ErrorState
      v-else-if="error"
      :title="errorTitle"
      :message="errorMessage"
      :details="errorDetails"
      :show-home="false"
      @retry="fetchCalendar"
    />

    <EmptyState
      v-else-if="staff.length === 0"
      icon="👥"
      title="No staff members found"
      description="Add at least one active staff member to view the team calendar."
    />

    <div v-else>
      <div class="flex flex-wrap items-center gap-3 mb-4">
        <button
          class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
          aria-label="Go to previous period"
          @click="prevPeriod"
        >
          &larr; Prev
        </button>
        <button
          class="px-3 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition-colors"
          aria-label="Go to today"
          @click="goToday"
        >
          Today
        </button>
        <button
          class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
          aria-label="Go to next period"
          @click="nextPeriod"
        >
          Next &rarr;
        </button>

        <p class="text-sm font-medium text-gray-700 min-w-0">
          {{ periodLabel }}
        </p>

        <div class="ml-auto flex items-center gap-2">
          <button
            class="px-3 py-1.5 text-sm font-medium border rounded-lg transition-colors"
            :class="currentView === 'day' ? 'bg-primary-600 text-white border-primary-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
            :aria-pressed="currentView === 'day'"
            @click="currentView = 'day'"
          >
            Day
          </button>
          <button
            class="px-3 py-1.5 text-sm font-medium border rounded-lg transition-colors"
            :class="currentView === 'week' ? 'bg-primary-600 text-white border-primary-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
            :aria-pressed="currentView === 'week'"
            @click="currentView = 'week'"
          >
            Week
          </button>
          <button
            class="px-3 py-1.5 text-sm font-medium border rounded-lg transition-colors"
            :class="currentView === 'month' ? 'bg-primary-600 text-white border-primary-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
            :aria-pressed="currentView === 'month'"
            @click="currentView = 'month'"
          >
            Month
          </button>
        </div>
      </div>

      <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
        <div v-if="currentView !== 'month'" class="max-h-[72vh] overflow-auto">
          <div :style="{ minWidth: `${80 + columns.length * minColumnWidth}px` }">
            <div class="grid sticky top-0 z-30 bg-white border-b border-gray-200" :style="gridTemplateStyle">
              <div class="sticky left-0 z-40 bg-white border-r border-gray-200 p-2 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                Time
              </div>
              <div
                v-for="column in columns"
                :key="column.key"
                class="p-2 border-r border-gray-200"
                :class="column.isToday ? 'bg-primary-50' : 'bg-white'"
              >
                <div v-if="currentView === 'day'" class="flex items-center gap-2 min-w-0">
                  <img
                    v-if="column.photoUrl"
                    :src="column.photoUrl"
                    :alt="column.label"
                    class="w-8 h-8 rounded-full object-cover flex-shrink-0"
                  />
                  <div
                    v-else
                    class="w-8 h-8 rounded-full text-white text-xs font-semibold flex items-center justify-center flex-shrink-0"
                    :style="{ backgroundColor: column.colour }"
                  >
                    {{ column.initials }}
                  </div>
                  <span class="text-sm font-medium text-gray-900 truncate">{{ column.label }}</span>
                </div>
                <div v-else>
                  <p class="text-sm font-semibold text-gray-900 truncate">
                    {{ column.label }}
                  </p>
                  <p class="text-xs text-gray-500">
                    {{ formatShortDate(column.date) }}
                  </p>
                </div>
              </div>
            </div>

            <div class="grid" :style="gridTemplateStyle">
              <div class="sticky left-0 z-20 bg-white border-r border-gray-200" :style="{ height: `${gridHeight}px` }">
                <div
                  v-for="slot in slots"
                  :key="`time-${slot.minutes}`"
                  class="absolute left-0 right-0 border-t"
                  :class="slot.isHour ? 'border-gray-300' : 'border-gray-100'"
                  :style="{ top: `${slot.offset}px` }"
                >
                  <span
                    v-if="slot.isHour"
                    class="absolute -top-2 left-2 bg-white px-1 text-xs font-medium text-gray-500"
                  >
                    {{ slot.label }}
                  </span>
                </div>
              </div>

              <div
                v-for="column in columns"
                :key="`grid-${column.key}`"
                class="relative border-r border-gray-200"
                :class="column.isToday ? 'bg-primary-50/50' : 'bg-white'"
                :style="{ height: `${gridHeight}px` }"
              >
                <div
                  v-for="slot in slots"
                  :key="`${column.key}-slot-${slot.minutes}`"
                  class="absolute left-0 right-0 border-t pointer-events-none"
                  :class="slot.isHour ? 'border-gray-300' : 'border-gray-100'"
                  :style="{ top: `${slot.offset}px` }"
                />

                <button
                  v-for="block in column.timeOff"
                  :key="`timeoff-${column.key}-${block.staff_id}-${block.start_time || 'all'}-${block.label}`"
                  type="button"
                  class="absolute left-1 right-1 rounded-md border border-dashed border-gray-400 bg-gray-100/80 p-2 text-left text-xs text-gray-700 overflow-hidden"
                  :style="getTimeOffStyle(block)"
                >
                  <p class="font-semibold truncate">{{ block.label || 'Time Off' }}</p>
                  <p v-if="!block.all_day && block.start_time && block.end_time" class="text-[11px] text-gray-600">
                    {{ block.start_time }} - {{ block.end_time }}
                  </p>
                </button>

                <button
                  v-for="booking in column.bookings"
                  :key="`booking-${column.key}-${booking.id}`"
                  type="button"
                  class="absolute left-1 right-1 rounded-md p-2 text-left text-xs overflow-hidden border-l-4 focus:outline-none focus:ring-2 focus:ring-primary-500"
                  :style="getBookingStyle(booking)"
                  @click="openBookingDetails(booking, column.date)"
                >
                  <div class="flex items-start justify-between gap-2">
                    <p class="font-semibold text-xs text-gray-900 truncate">{{ booking.customer_name }}</p>
                    <span
                      class="w-2 h-2 rounded-full flex-shrink-0 mt-0.5"
                      :class="getStatusDotClass(booking.status)"
                      :title="formatStatus(booking.status)"
                      aria-hidden="true"
                    />
                  </div>

                  <p v-if="showServiceLine(booking)" class="text-xs text-gray-600 truncate mt-0.5">
                    {{ booking.service_name }}
                  </p>
                  <p v-if="showWeekStaffLine(booking)" class="text-xs italic text-gray-500 truncate mt-0.5">
                    {{ getStaffName(booking.staff_id) }}
                  </p>
                  <p v-if="showTimeFooter(booking)" class="text-[10px] text-gray-500 mt-1 truncate">
                    {{ booking.start_time }}–{{ booking.end_time }}
                  </p>
                </button>
              </div>
            </div>
          </div>
        </div>

        <div v-else class="overflow-hidden">
          <div class="grid grid-cols-7 border-b border-gray-200 bg-gray-50">
            <div
              v-for="dayName in monthWeekdays"
              :key="dayName"
              class="p-2 text-xs font-semibold uppercase tracking-wide text-gray-500 text-center"
            >
              {{ dayName }}
            </div>
          </div>

          <div class="grid grid-cols-7">
            <template v-for="(week, weekIndex) in monthGrid" :key="`week-${weekIndex}`">
              <div
                v-for="cell in week"
                :key="`month-cell-${cell.date || `${weekIndex}-${cell.dayNumber}`}`"
                class="border-r border-b border-gray-200 p-2 min-h-[60px] md:min-h-[96px]"
                :class="[
                  cell.isCurrentMonth ? 'bg-white' : 'bg-gray-50',
                  cell.isToday && cell.isCurrentMonth ? 'bg-primary-50 ring-1 ring-inset ring-primary-300' : ''
                ]"
              >
                <button
                  v-if="cell.isCurrentMonth"
                  type="button"
                  class="w-full h-full text-left rounded focus:outline-none focus:ring-2 focus:ring-primary-500"
                  :aria-label="`Open day view for ${formatLongDate(cell.date)}`"
                  @click="openMonthDay(cell.date)"
                >
                  <div class="flex items-start justify-between gap-2">
                    <span
                      class="text-sm font-semibold"
                      :class="cell.isToday ? 'text-primary-700' : 'text-gray-900'"
                    >
                      {{ cell.dayNumber }}
                    </span>
                    <span
                      v-if="cell.bookingCount > 0"
                      class="inline-flex md:hidden items-center rounded-full bg-primary-100 px-1.5 py-0.5 text-[10px] font-medium text-primary-800"
                    >
                      {{ cell.bookingCount }}
                    </span>
                  </div>

                  <div class="mt-2 hidden md:block space-y-1">
                    <div v-if="cell.staffWithBookings.length > 0" class="flex items-center gap-1 flex-wrap">
                      <span
                        v-for="staffDot in cell.staffWithBookings.slice(0, 4)"
                        :key="`dot-${cell.date}-${staffDot}`"
                        class="w-2 h-2 rounded-full"
                        :style="{ backgroundColor: getStaffColour(staffDot) }"
                      />
                      <span
                        v-if="cell.staffWithBookings.length > 4"
                        class="text-[10px] text-gray-500"
                      >
                        +{{ cell.staffWithBookings.length - 4 }} more
                      </span>
                    </div>

                    <div
                      v-if="cell.bookingCount > 0"
                      class="text-[11px] font-medium text-gray-700"
                    >
                      {{ cell.bookingCount }} booking{{ cell.bookingCount === 1 ? '' : 's' }}
                    </div>

                    <div
                      v-if="cell.timeOff.length > 0"
                      class="text-[10px] text-gray-500"
                    >
                      {{ cell.timeOff.length }} off
                    </div>
                  </div>
                </button>

                <div v-else class="h-full">
                  <span class="text-sm font-medium text-gray-400">{{ cell.dayNumber }}</span>
                </div>
              </div>
            </template>
          </div>
        </div>
      </div>
    </div>

    <div v-if="selectedBooking" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/30" @click.self="selectedBooking = null">
      <div class="w-full max-w-md bg-white rounded-lg shadow-xl border border-gray-200 p-4">
        <div class="flex items-start justify-between gap-3 mb-3">
          <h3 class="text-base font-semibold text-gray-900">Booking Details</h3>
          <button
            type="button"
            class="text-gray-500 hover:text-gray-700"
            aria-label="Close booking details"
            @click="selectedBooking = null"
          >
            &times;
          </button>
        </div>
        <div class="space-y-2 text-sm">
          <p><span class="font-medium text-gray-700">Customer:</span> {{ selectedBooking.customer_name }}</p>
          <p><span class="font-medium text-gray-700">Service:</span> {{ selectedBooking.service_name }}</p>
          <p><span class="font-medium text-gray-700">Staff:</span> {{ getStaffName(selectedBooking.staff_id) }}</p>
          <p><span class="font-medium text-gray-700">Date:</span> {{ selectedBooking.booking_date }}</p>
          <p><span class="font-medium text-gray-700">Time:</span> {{ selectedBooking.start_time }} - {{ selectedBooking.end_time }}</p>
          <p><span class="font-medium text-gray-700">Status:</span> {{ formatStatus(selectedBooking.status) }}</p>
          <p><span class="font-medium text-gray-700">Payment:</span> {{ selectedBooking.payment_status }}</p>
          <p><span class="font-medium text-gray-700">Price:</span> £{{ Number(selectedBooking.total_price || 0).toFixed(2) }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useApi } from '../composables/useApi'
import ErrorState from '../components/ErrorState.vue'
import CardSkeleton from '../components/CardSkeleton.vue'
import EmptyState from '../components/EmptyState.vue'

const SLOT_HEIGHT = 40
const MINUTES_PER_SLOT = 30

const api = useApi()

const currentView = ref('day')
const currentDate = ref(new Date())

const loading = ref(true)
const error = ref(null)
const errorTitle = ref('')
const errorMessage = ref('')
const errorDetails = ref('')

const dateStart = ref('')
const dateEnd = ref('')
const staff = ref([])
const days = ref([])
const selectedBooking = ref(null)

const minColumnWidth = computed(() => (currentView.value === 'day' ? 220 : 240))
const gridTemplateStyle = computed(() => ({
  gridTemplateColumns: `80px repeat(${columns.value.length}, minmax(${minColumnWidth.value}px, 1fr))`
}))

const staffMap = computed(() => {
  const map = {}
  for (const member of staff.value) {
    map[member.id] = member
  }
  return map
})

const dayLookup = computed(() => {
  const map = {}
  for (const day of days.value) {
    map[day.date] = day
  }
  return map
})

const periodLabel = computed(() => {
  if (!dateStart.value || !dateEnd.value) return ''
  if (currentView.value === 'day') {
    return formatLongDate(dateStart.value)
  }
  if (currentView.value === 'month') {
    const monthDate = new Date(`${dateStart.value}T12:00:00`)
    return new Intl.DateTimeFormat('en-GB', {
      month: 'long',
      year: 'numeric'
    }).format(monthDate)
  }

  const start = new Date(`${dateStart.value}T12:00:00`)
  const end = new Date(`${dateEnd.value}T12:00:00`)
  const isCrossMonth = start.getMonth() !== end.getMonth()
  const startDay = new Intl.DateTimeFormat('en-GB', {
    day: 'numeric',
    ...(isCrossMonth ? { month: 'short' } : {})
  }).format(start)
  const endPart = new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'long', year: 'numeric' }).format(end)
  return `${startDay}–${endPart}`
})

const currentDateKey = computed(() => dateToYMD(currentDate.value))

const columns = computed(() => {
  if (currentView.value === 'day') {
    const day = days.value[0] || { bookings: [], time_off: [] }
    return staff.value.map(member => ({
      key: `staff-${member.id}`,
      date: day.date || dateStart.value,
      label: member.full_name,
      initials: member.initials,
      colour: member.colour,
      photoUrl: member.photo_url,
      isToday: !!day.is_today,
      bookings: day.bookings.filter(b => Number(b.staff_id) === Number(member.id)),
      timeOff: day.time_off.filter(t => Number(t.staff_id) === Number(member.id))
    }))
  }

  return days.value.map(day => ({
    key: `day-${day.date}`,
    label: day.label,
    date: day.date,
    isToday: day.is_today,
    bookings: day.bookings,
    timeOff: day.time_off
  }))
})

const monthWeekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']

const monthGrid = computed(() => {
  if (currentView.value !== 'month' || !dateStart.value || !dateEnd.value) {
    return []
  }

  const monthStart = new Date(`${dateStart.value}T12:00:00`)
  const monthEnd = new Date(`${dateEnd.value}T12:00:00`)
  const monthStartDow = (monthStart.getDay() + 6) % 7
  const monthEndDow = (monthEnd.getDay() + 6) % 7
  const gridStart = addDays(monthStart, -monthStartDow)
  const gridEnd = addDays(monthEnd, 6 - monthEndDow)
  const monthKey = `${monthStart.getFullYear()}-${monthStart.getMonth()}`
  const todayKey = dateToYMD(new Date())
  const weeks = []

  let cursor = new Date(gridStart.getTime())
  while (cursor <= gridEnd) {
    const week = []
    for (let i = 0; i < 7; i++) {
      const cellDate = dateToYMD(cursor)
      const dayData = dayLookup.value[cellDate]
      const isCurrentMonth = `${cursor.getFullYear()}-${cursor.getMonth()}` === monthKey
      const bookings = Array.isArray(dayData?.bookings) ? dayData.bookings : []
      const timeOff = Array.isArray(dayData?.time_off) ? dayData.time_off : []
      const uniqueStaff = Array.from(
        new Set(bookings.map(booking => Number(booking.staff_id)).filter(staffId => Number.isInteger(staffId) && staffId > 0))
      )

      week.push({
        date: isCurrentMonth ? cellDate : null,
        dayNumber: cursor.getDate(),
        isCurrentMonth,
        isToday: dayData ? !!dayData.is_today : cellDate === todayKey,
        bookings,
        timeOff,
        bookingCount: Number(dayData?.booking_count ?? bookings.length),
        staffWithBookings: uniqueStaff
      })

      cursor = addDays(cursor, 1)
    }
    weeks.push(week)
  }

  return weeks
})

const timeBounds = computed(() => {
  let minMinutes = Number.POSITIVE_INFINITY
  let maxMinutes = Number.NEGATIVE_INFINITY

  for (const column of columns.value) {
    for (const booking of column.bookings) {
      minMinutes = Math.min(minMinutes, timeToMinutes(booking.start_time))
      maxMinutes = Math.max(maxMinutes, timeToMinutes(booking.end_time))
    }
    for (const block of column.timeOff) {
      if (!block.all_day && block.start_time && block.end_time) {
        minMinutes = Math.min(minMinutes, timeToMinutes(block.start_time))
        maxMinutes = Math.max(maxMinutes, timeToMinutes(block.end_time))
      }
    }
  }

  if (!Number.isFinite(minMinutes) || !Number.isFinite(maxMinutes)) {
    return { start: 8 * 60, end: 20 * 60 }
  }

  const start = Math.floor(minMinutes / 30) * 30
  const end = Math.ceil(maxMinutes / 30) * 30
  const adjustedEnd = end <= start ? start + 60 : end
  return { start, end: adjustedEnd }
})

const slots = computed(() => {
  const result = []
  const start = timeBounds.value.start
  const end = timeBounds.value.end
  for (let minutes = start; minutes <= end; minutes += MINUTES_PER_SLOT) {
    result.push({
      minutes,
      offset: ((minutes - start) / MINUTES_PER_SLOT) * SLOT_HEIGHT,
      label: minutesToTime(minutes),
      isHour: minutes % 60 === 0
    })
  }
  return result
})

const gridHeight = computed(() => {
  const totalSlots = (timeBounds.value.end - timeBounds.value.start) / MINUTES_PER_SLOT
  return Math.max(totalSlots * SLOT_HEIGHT, SLOT_HEIGHT * 4)
})

function dateToYMD(date) {
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, '0')
  const d = String(date.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}

function addDays(date, daysToAdd) {
  const next = new Date(date.getTime())
  next.setDate(next.getDate() + daysToAdd)
  return next
}

function addMonths(date, monthsToAdd) {
  const next = new Date(date.getFullYear(), date.getMonth(), 1, 12, 0, 0, 0)
  next.setMonth(next.getMonth() + monthsToAdd)
  return next
}

function formatLongDate(dateString) {
  const date = new Date(`${dateString}T12:00:00`)
  return new Intl.DateTimeFormat('en-GB', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric'
  }).format(date)
}

function formatShortDate(dateString) {
  const date = new Date(`${dateString}T12:00:00`)
  return new Intl.DateTimeFormat('en-GB', {
    day: 'numeric',
    month: 'short'
  }).format(date)
}

function prevPeriod() {
  if (currentView.value === 'month') {
    currentDate.value = addMonths(currentDate.value, -1)
    return
  }
  currentDate.value = addDays(currentDate.value, currentView.value === 'day' ? -1 : -7)
}

function nextPeriod() {
  if (currentView.value === 'month') {
    currentDate.value = addMonths(currentDate.value, 1)
    return
  }
  currentDate.value = addDays(currentDate.value, currentView.value === 'day' ? 1 : 7)
}

function goToday() {
  currentDate.value = new Date()
}

async function fetchCalendar() {
  loading.value = true
  error.value = null

  try {
    const response = await api.get(`${window.BOOKIT_DASHBOARD.restBase}team-calendar`, {
      params: {
        view_type: currentView.value,
        date: currentDateKey.value
      }
    })

    if (!response.data?.success) {
      throw new Error(response.data?.message || 'Failed to load team calendar')
    }

    dateStart.value = response.data.date_start
    dateEnd.value = response.data.date_end
    staff.value = Array.isArray(response.data.staff) ? response.data.staff : []
    days.value = Array.isArray(response.data.days) ? response.data.days : []
  } catch (err) {
    console.error('Error loading team calendar:', err)
    error.value = true

    if (err.status === 403) {
      errorTitle.value = 'Access denied'
      errorMessage.value = 'Only administrators can access the team calendar.'
    } else if (err.status >= 500) {
      errorTitle.value = 'Server error'
      errorMessage.value = 'Our servers are experiencing issues. Please try again in a few moments.'
    } else if (!navigator.onLine) {
      errorTitle.value = 'No internet connection'
      errorMessage.value = 'Please check your internet connection and try again.'
    } else {
      errorTitle.value = 'Failed to load team calendar'
      errorMessage.value = err.message || 'An unexpected error occurred.'
    }

    errorDetails.value = `Error: ${err.message}\nStatus: ${err.status || 'N/A'}`
  } finally {
    loading.value = false
  }
}

function timeToMinutes(timeString) {
  const [h, m] = String(timeString).split(':').map(Number)
  return (h * 60) + m
}

function minutesToTime(totalMinutes) {
  const h = String(Math.floor(totalMinutes / 60)).padStart(2, '0')
  const m = String(totalMinutes % 60).padStart(2, '0')
  return `${h}:${m}`
}

function toRgba(hex, alpha) {
  const normal = String(hex || '').replace('#', '')
  if (!/^[0-9a-fA-F]{6}$/.test(normal)) return `rgba(79, 70, 229, ${alpha})`
  const r = parseInt(normal.slice(0, 2), 16)
  const g = parseInt(normal.slice(2, 4), 16)
  const b = parseInt(normal.slice(4, 6), 16)
  return `rgba(${r}, ${g}, ${b}, ${alpha})`
}

function getBookingStyle(booking) {
  const staffColour = staffMap.value[booking.staff_id]?.colour || '#4F46E5'
  const startMinutes = timeToMinutes(booking.start_time)
  const endMinutes = timeToMinutes(booking.end_time)
  const top = (startMinutes - timeBounds.value.start) * (SLOT_HEIGHT / MINUTES_PER_SLOT)
  const height = Math.max((endMinutes - startMinutes) * (SLOT_HEIGHT / MINUTES_PER_SLOT), 24)

  return {
    top: `${top}px`,
    height: `${height}px`,
    backgroundColor: toRgba(staffColour, 0.18),
    borderLeftColor: staffColour
  }
}

function getTimeOffStyle(block) {
  if (block.all_day) {
    return { top: '0px', height: `${gridHeight.value}px` }
  }

  const startMinutes = timeToMinutes(block.start_time)
  const endMinutes = timeToMinutes(block.end_time)
  const top = (startMinutes - timeBounds.value.start) * (SLOT_HEIGHT / MINUTES_PER_SLOT)
  const height = Math.max((endMinutes - startMinutes) * (SLOT_HEIGHT / MINUTES_PER_SLOT), 24)
  return {
    top: `${top}px`,
    height: `${height}px`
  }
}

function getStatusDotClass(status) {
  const classes = {
    confirmed: 'bg-green-600',
    pending: 'bg-amber-600',
    pending_payment: 'bg-amber-600',
    completed: 'bg-gray-400',
    cancelled: 'bg-red-600',
    no_show: 'bg-red-900'
  }
  return classes[status] || 'bg-gray-400'
}

function formatStatus(status) {
  const labels = {
    pending: 'Pending',
    pending_payment: 'Pending Payment',
    confirmed: 'Confirmed',
    completed: 'Completed',
    cancelled: 'Cancelled',
    no_show: 'No Show'
  }
  return labels[status] || status
}

function getStaffName(staffId) {
  return staffMap.value[staffId]?.full_name || 'Unknown staff'
}

function getStaffColour(staffId) {
  return staffMap.value[staffId]?.colour || '#9CA3AF'
}

function bookingDurationMinutes(booking) {
  return Math.max(timeToMinutes(booking.end_time) - timeToMinutes(booking.start_time), 0)
}

function bookingHeightPx(booking) {
  return Math.max(bookingDurationMinutes(booking) * (SLOT_HEIGHT / MINUTES_PER_SLOT), 24)
}

function isCompactCard(booking) {
  return bookingHeightPx(booking) < 60
}

function showServiceLine(booking) {
  return !isCompactCard(booking)
}

function showWeekStaffLine(booking) {
  return currentView.value === 'week' && !isCompactCard(booking)
}

function showTimeFooter(booking) {
  return bookingHeightPx(booking) > 80
}

function openBookingDetails(booking, bookingDate) {
  selectedBooking.value = {
    ...booking,
    booking_date: bookingDate || dateStart.value
  }
}

function openMonthDay(dateString) {
  if (!dateString) return
  currentDate.value = new Date(`${dateString}T12:00:00`)
  currentView.value = 'day'
}

watch([currentView, currentDateKey], fetchCalendar)

onMounted(fetchCalendar)
</script>
