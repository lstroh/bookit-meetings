<template>
  <div class="p-4 sm:p-6">
    <!-- Header -->
    <div class="mb-4 lg:mb-6">
      <button
        @click="goBack"
        class="inline-flex items-center gap-1 text-gray-500 hover:text-gray-700 text-sm mb-3"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
        Back to Staff
      </button>

      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="flex items-center gap-3">
          <!-- Staff Avatar -->
          <div class="flex-shrink-0">
            <img
              v-if="staff?.photo_url"
              :src="staff.photo_url"
              :alt="staff?.full_name"
              class="h-10 w-10 rounded-full object-cover"
            />
            <div
              v-else-if="staff"
              class="h-10 w-10 rounded-full flex items-center justify-center text-white font-semibold text-sm"
              :style="{ backgroundColor: getColorForInitials(staff.full_name) }"
            >
              {{ getInitials(staff.full_name) }}
            </div>
          </div>
          <div class="min-w-0">
            <h1 class="text-lg sm:text-xl lg:text-2xl font-bold text-gray-900">
              Working Hours
            </h1>
            <p class="text-sm text-gray-600 truncate">
              {{ staff?.full_name || 'Loading...' }}
              <span v-if="staff?.title" class="text-gray-400">
                &middot; {{ staff.title }}
              </span>
            </p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <BookitTooltip
            content="Saves the weekly recurring schedule. Date exceptions are saved immediately when added and do not require clicking this button."
            position="top"
          />
          <button
            @click="saveSchedule"
            :disabled="saving"
            class="w-full sm:w-auto px-4 py-2.5 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 disabled:opacity-50"
          >
            {{ saving ? 'Saving...' : 'Save Schedule' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
      <p class="mt-2 text-sm text-gray-600">Loading working hours...</p>
    </div>

    <div v-else>
      <!-- Success/Error Messages -->
      <div v-if="saveSuccess" class="mb-4 bg-green-50 border border-green-200 rounded p-3">
        <p class="text-sm text-green-800">Working hours saved successfully.</p>
      </div>
      <div v-if="saveError" class="mb-4 bg-red-50 border border-red-200 rounded p-3">
        <p class="text-sm text-red-800">{{ saveError }}</p>
      </div>

      <!-- Weekly Schedule -->
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
        <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
          <h2 class="text-base sm:text-lg font-semibold text-gray-900">Weekly Schedule</h2>
          <p class="text-xs sm:text-sm text-gray-500 mt-1">
            Set regular working hours for each day of the week.
            These repeat every week unless a seasonal date range is set.
          </p>
        </div>

        <!-- Day Rows -->
        <div class="divide-y divide-gray-200">
          <template v-for="day in days" :key="day.number">
            <div
              v-if="schedule[day.number]"
              class="px-4 sm:px-6 py-4"
              :class="{ 'bg-gray-50': !schedule[day.number]?.is_working }"
            >
              <!-- Day Header -->
              <div class="flex items-center justify-between">
                <label class="flex items-center cursor-pointer">
                  <input
                    type="checkbox"
                    v-model="schedule[day.number].is_working"
                    @change="onDayToggle(day.number)"
                    class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500"
                  />
                  <span
                    class="ml-2 text-sm font-medium"
                    :class="schedule[day.number]?.is_working ? 'text-gray-900' : 'text-gray-400'"
                  >
                    {{ day.name }}
                  </span>
                </label>
                <span
                  v-if="!schedule[day.number]?.is_working"
                  class="text-xs font-medium text-gray-500 bg-gray-200 px-2 py-0.5 rounded"
                >
                  Day Off
                </span>
              </div>

              <!-- Working Hours Details -->
              <div v-if="schedule[day.number]?.is_working" class="mt-4 space-y-4">
                <!-- Working Hours -->
                <div>
                  <label class="block text-xs font-medium text-gray-600 mb-2">
                    <span class="inline-flex items-center gap-1">
                      Working Hours
                      <BookitTooltip
                        content="The time range this staff member accepts bookings."
                        position="top"
                      />
                    </span>
                  </label>
                  <div class="grid grid-cols-2 gap-3">
                    <div>
                      <label class="block text-xs text-gray-500 mb-1">From</label>
                      <input
                        type="time"
                        v-model="schedule[day.number].start_time"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                      />
                    </div>
                    <div>
                      <label class="block text-xs text-gray-500 mb-1">To</label>
                      <input
                        type="time"
                        v-model="schedule[day.number].end_time"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                      />
                    </div>
                  </div>
                </div>

                <!-- Break -->
                <div>
                  <label class="flex items-center cursor-pointer">
                    <input
                      type="checkbox"
                      v-model="schedule[day.number].has_break"
                      class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500"
                    />
                    <span class="ml-1.5 text-xs text-gray-600 flex items-center gap-1">
                      Break
                      <BookitTooltip
                        content="Allows a staff member to work two separate blocks in one day, e.g. 9am-1pm and 3pm-6pm, with a gap in between."
                        position="top"
                      />
                    </span>
                  </label>
                  <div v-if="schedule[day.number]?.has_break" class="mt-2 grid grid-cols-2 gap-3">
                    <div>
                      <label class="block text-xs text-gray-500 mb-1">Break Start</label>
                      <input
                        type="time"
                        v-model="schedule[day.number].break_start"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                      />
                    </div>
                    <div>
                      <label class="block text-xs text-gray-500 mb-1">Break End</label>
                      <input
                        type="time"
                        v-model="schedule[day.number].break_end"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                      />
                    </div>
                  </div>
                </div>

                <!-- Seasonal -->
                <div>
                  <label class="flex items-center cursor-pointer">
                    <input
                      type="checkbox"
                      v-model="schedule[day.number].has_seasonal"
                      class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500"
                    />
                    <span class="ml-1.5 text-xs text-gray-600 flex items-center gap-1">
                      Seasonal
                      <BookitTooltip
                        content="Seasonal schedules are only active between two dates. Useful for summer hours, holiday periods, or temporary schedule changes. Outside these dates, this day follows no schedule (treated as day off)."
                        position="top"
                      />
                    </span>
                  </label>
                  <div v-if="schedule[day.number]?.has_seasonal" class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                      <label class="block text-xs text-gray-500 mb-1">
                        <span class="inline-flex items-center gap-1">
                          Valid from
                          <BookitTooltip
                            content="The first date this schedule is active. Before this date, this day is treated as a day off."
                            position="top"
                          />
                        </span>
                      </label>
                      <input
                        type="date"
                        v-model="schedule[day.number].valid_from"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                      />
                    </div>
                    <div>
                      <label class="block text-xs text-gray-500 mb-1">
                        <span class="inline-flex items-center gap-1">
                          Valid until
                          <BookitTooltip
                            content="The last date this schedule is active. After this date, this day is treated as a day off."
                            position="top"
                          />
                        </span>
                      </label>
                      <input
                        type="date"
                        v-model="schedule[day.number].valid_until"
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                      />
                    </div>
                  </div>
                </div>

                <!-- Validation Error -->
                <p
                  v-if="validationErrors[day.number]"
                  class="text-xs text-red-600"
                >
                  {{ validationErrors[day.number] }}
                </p>
              </div>
            </div>
          </template>
        </div>

        <!-- Save Button (bottom) -->
        <div class="px-4 sm:px-6 py-4 border-t border-gray-200 bg-gray-50 flex flex-col sm:flex-row justify-between items-stretch sm:items-center gap-3">
          <p class="text-xs text-gray-500">
            Changes are saved immediately when you click "Save Schedule"
          </p>
          <button
            @click="saveSchedule"
            :disabled="saving"
            class="w-full sm:w-auto px-4 py-2.5 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 disabled:opacity-50"
          >
            {{ saving ? 'Saving...' : 'Save Schedule' }}
          </button>
        </div>
      </div>

      <!-- Date Exceptions -->
      <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
              <div class="flex items-center gap-2">
                <h2 class="text-base sm:text-lg font-semibold text-gray-900">Date Exceptions</h2>
                <BookitTooltip
                  content="Date exceptions override the weekly schedule for a specific date. Use them for bank holidays, staff holidays, training days, or any day with different hours. Exceptions always take priority over the weekly schedule."
                  position="top"
                />
              </div>
              <p class="text-xs sm:text-sm text-gray-500 mt-1">
                Override working hours for specific dates. Exceptions always take priority over the weekly schedule.
              </p>
            </div>
            <button
              @click="showAddException = true"
              class="w-full sm:w-auto px-3 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 flex-shrink-0"
            >
              + Add Exception
            </button>
          </div>
        </div>

        <!-- Add Exception Form -->
        <div v-if="showAddException" class="px-4 sm:px-6 py-4 bg-blue-50 border-b border-blue-200">
          <h3 class="text-sm font-medium text-gray-900 mb-3">Add Date Exception</h3>
          <div class="space-y-3">
            <!-- Date & Type -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="block text-xs text-gray-600 mb-1">Date *</label>
                <input
                  type="date"
                  v-model="newException.specific_date"
                  :min="today"
                  class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                />
              </div>
              <div>
                <label class="flex items-center gap-1 text-xs text-gray-600 mb-1">
                  Type *
                  <BookitTooltip
                    content="Day Off: Staff member is completely unavailable. No bookings possible.\n\nSpecial Hours: Staff works different hours than usual - set a custom start and end time."
                    position="top"
                  />
                </label>
                <select
                  v-model="newException.is_working"
                  class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                >
                  <option :value="false">Day Off</option>
                  <option :value="true">Special Hours</option>
                </select>
              </div>
            </div>

            <!-- Times (if working) -->
            <template v-if="newException.is_working">
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs text-gray-600 mb-1">Start *</label>
                  <input
                    type="time"
                    v-model="newException.start_time"
                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                  />
                </div>
                <div>
                  <label class="block text-xs text-gray-600 mb-1">End *</label>
                  <input
                    type="time"
                    v-model="newException.end_time"
                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                  />
                </div>
              </div>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs text-gray-600 mb-1">Break Start</label>
                  <input
                    type="time"
                    v-model="newException.break_start"
                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                  />
                </div>
                <div>
                  <label class="block text-xs text-gray-600 mb-1">Break End</label>
                  <input
                    type="time"
                    v-model="newException.break_end"
                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                  />
                </div>
              </div>
            </template>

            <!-- Notes -->
            <div>
              <label class="flex items-center gap-1 text-xs text-gray-600 mb-1">
                Notes
                <BookitTooltip
                  content="Optional internal note for this exception. Only visible to admins - not shown to customers. Example: 'Annual leave', 'Bank holiday', 'Team training day'."
                  position="top"
                />
              </label>
              <input
                type="text"
                v-model="newException.notes"
                placeholder="e.g., Holiday, Training day"
                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
              />
            </div>
          </div>

          <!-- Error -->
          <p v-if="exceptionError" class="text-xs text-red-600 mt-2">
            {{ exceptionError }}
          </p>

          <!-- Buttons -->
          <div class="flex flex-col-reverse sm:flex-row gap-2 mt-3">
            <button
              @click="cancelAddException"
              class="w-full sm:w-auto px-3 py-2 text-sm font-medium text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50"
            >
              Cancel
            </button>
            <button
              @click="addException"
              :disabled="addingException"
              class="w-full sm:w-auto px-3 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 disabled:opacity-50"
            >
              {{ addingException ? 'Adding...' : 'Add Exception' }}
            </button>
          </div>
        </div>

        <!-- Exceptions List -->
        <div v-if="exceptions.length === 0 && !showAddException" class="px-4 sm:px-6 py-8 text-center">
          <p class="text-sm text-gray-500">No date exceptions configured.</p>
          <p class="text-xs text-gray-400 mt-1">
            Add exceptions for holidays, time off, or special hours.
          </p>
        </div>

        <div v-else-if="exceptions.length > 0" class="divide-y divide-gray-200">
          <div
            v-for="exception in exceptions"
            :key="exception.id"
            class="px-4 sm:px-6 py-3 hover:bg-gray-50"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center gap-2 mb-1">
                  <p class="text-sm font-medium text-gray-900">
                    {{ formatDate(exception.specific_date) }}
                  </p>
                  <span class="text-xs text-gray-500">
                    {{ getDayName(exception.specific_date) }}
                  </span>
                  <span
                    class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full"
                    :class="exception.is_working
                      ? 'bg-blue-100 text-blue-800'
                      : 'bg-red-100 text-red-800'"
                  >
                    {{ exception.is_working ? 'Special Hours' : 'Day Off' }}
                  </span>
                </div>

                <div v-if="exception.is_working" class="text-sm text-gray-600">
                  {{ formatTime(exception.start_time) }} &ndash; {{ formatTime(exception.end_time) }}
                  <span v-if="exception.break_start" class="text-gray-400 text-xs ml-1">
                    (break {{ formatTime(exception.break_start) }}&ndash;{{ formatTime(exception.break_end) }})
                  </span>
                </div>

                <p v-if="exception.notes" class="text-xs text-gray-500 italic mt-1 truncate">
                  "{{ exception.notes }}"
                </p>
              </div>

              <button
                @click="deleteException(exception)"
                class="text-red-600 hover:text-red-800 text-sm flex-shrink-0"
              >
                Remove
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useApi } from '../composables/useApi'

// Day definitions MUST be first (ISO-8601: 1=Mon...7=Sun).
const days = [
  { number: 1, name: 'Monday' },
  { number: 2, name: 'Tuesday' },
  { number: 3, name: 'Wednesday' },
  { number: 4, name: 'Thursday' },
  { number: 5, name: 'Friday' },
  { number: 6, name: 'Saturday' },
  { number: 7, name: 'Sunday' },
]

const route  = useRoute()
const router = useRouter()
const api    = useApi()

// Staff ID from route params.
const staffId = computed(() => parseInt(route.params.staff_id))

// State.
const loading     = ref(false)
const saving      = ref(false)
const saveSuccess = ref(false)
const saveError   = ref('')
const staff       = ref(null)
const exceptions  = ref([])

// Schedule state - one entry per day (1-7).
// Pre-initialize all days to prevent template errors before load.
const schedule = ref(
  Object.fromEntries(
    [1,2,3,4,5,6,7].map(day => [day, {
      day_of_week: day,
      is_working: false,
      start_time: '09:00',
      end_time: '17:00',
      has_break: false,
      break_start: '12:00',
      break_end: '13:00',
      has_seasonal: false,
      valid_from: '',
      valid_until: ''
    }])
  )
)
const validationErrors = ref({})

// Exception state.
const showAddException = ref(false)
const addingException  = ref(false)
const exceptionError   = ref('')
const newException     = ref(getDefaultException())

// Today's date for min attribute.
const today = computed(() => new Date().toISOString().split('T')[0])

function getDefaultException() {
  return {
    specific_date: '',
    is_working: false,
    start_time: '09:00',
    end_time: '17:00',
    break_start: '',
    break_end: '',
    notes: ''
  }
}

function initSchedule() {
  for (let day = 1; day <= 7; day++) {
    schedule.value[day] = {
      day_of_week: day,
      is_working: false,
      start_time: '09:00',
      end_time: '17:00',
      has_break: false,
      break_start: '12:00',
      break_end: '13:00',
      has_seasonal: false,
      valid_from: '',
      valid_until: ''
    }
  }
}

function populateSchedule(apiSchedule) {
  for (let day = 1; day <= 7; day++) {
    const dayData = apiSchedule[day]

    if (!dayData || !dayData.is_working || dayData.records.length === 0) {
      schedule.value[day] = {
        day_of_week: day,
        is_working: false,
        start_time: '09:00',
        end_time: '17:00',
        has_break: false,
        break_start: '12:00',
        break_end: '13:00',
        has_seasonal: false,
        valid_from: '',
        valid_until: ''
      }
    } else {
      const record = dayData.records[0]

      schedule.value[day] = {
        day_of_week: day,
        is_working: true,
        start_time: formatTimeForInput(record.start_time),
        end_time: formatTimeForInput(record.end_time),
        has_break: !!(record.break_start && record.break_end),
        break_start: record.break_start ? formatTimeForInput(record.break_start) : '12:00',
        break_end: record.break_end ? formatTimeForInput(record.break_end) : '13:00',
        has_seasonal: !!(record.valid_from || record.valid_until),
        valid_from: record.valid_from || '',
        valid_until: record.valid_until || ''
      }
    }
  }
}

function formatTimeForInput(time) {
  if (!time) return ''
  return time.substring(0, 5)
}

function formatTime(time) {
  if (!time) return ''
  const [hours, minutes] = time.split(':')
  const hour = parseInt(hours)
  const ampm = hour >= 12 ? 'PM' : 'AM'
  const displayHour = hour % 12 || 12
  return `${displayHour}:${minutes} ${ampm}`
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  const date = new Date(dateStr + 'T00:00:00')
  return date.toLocaleDateString('en-GB', {
    day: 'numeric',
    month: 'short',
    year: 'numeric'
  })
}

function getDayName(dateStr) {
  if (!dateStr) return ''
  const date = new Date(dateStr + 'T00:00:00')
  return date.toLocaleDateString('en-GB', { weekday: 'long' })
}

function onDayToggle(dayNumber) {
  delete validationErrors.value[dayNumber]
}

function validateSchedule() {
  validationErrors.value = {}
  let valid = true

  for (let day = 1; day <= 7; day++) {
    const dayData = schedule.value[day]
    if (!dayData.is_working) continue

    if (dayData.start_time >= dayData.end_time) {
      validationErrors.value[day] = 'End time must be after start time'
      valid = false
      continue
    }

    if (dayData.has_break) {
      if (!dayData.break_start || !dayData.break_end) {
        validationErrors.value[day] = 'Break start and end times are required'
        valid = false
        continue
      }
      if (dayData.break_start >= dayData.break_end) {
        validationErrors.value[day] = 'Break end must be after break start'
        valid = false
        continue
      }
      if (dayData.break_start <= dayData.start_time ||
          dayData.break_end >= dayData.end_time) {
        validationErrors.value[day] = 'Break must be within working hours'
        valid = false
        continue
      }
    }

    if (dayData.has_seasonal) {
      if (!dayData.valid_from || !dayData.valid_until) {
        validationErrors.value[day] = 'Both seasonal start and end dates are required'
        valid = false
        continue
      }
      if (dayData.valid_from >= dayData.valid_until) {
        validationErrors.value[day] = 'Seasonal end date must be after start date'
        valid = false
      }
    }
  }

  return valid
}

const loadHours = async () => {
  loading.value = true

  try {
    const response = await api.get(`staff/${staffId.value}/hours`)

    if (response.data.success) {
      staff.value = response.data.staff
      initSchedule()
      populateSchedule(response.data.schedule)
    }

    const exceptionsResponse = await api.get(`staff/${staffId.value}/hours/exceptions`)
    if (exceptionsResponse.data.success) {
      exceptions.value = exceptionsResponse.data.exceptions
    }
  } catch (err) {
    console.error('Error loading hours:', err)
  } finally {
    loading.value = false
  }
}

const saveSchedule = async () => {
  if (!validateSchedule()) return

  saving.value      = true
  saveSuccess.value = false
  saveError.value   = ''

  try {
    const scheduleData = []

    for (let day = 1; day <= 7; day++) {
      const dayData = schedule.value[day]

      scheduleData.push({
        day_of_week: day,
        is_working:  dayData.is_working,
        start_time:  dayData.start_time,
        end_time:    dayData.end_time,
        break_start: dayData.is_working && dayData.has_break ? dayData.break_start : null,
        break_end:   dayData.is_working && dayData.has_break ? dayData.break_end : null,
        valid_from:  dayData.is_working && dayData.has_seasonal ? dayData.valid_from : null,
        valid_until: dayData.is_working && dayData.has_seasonal ? dayData.valid_until : null,
      })
    }

    const response = await api.post(`staff/${staffId.value}/hours`, {
      schedule: scheduleData
    })

    if (response.data.success) {
      saveSuccess.value = true
      await loadHours()
      setTimeout(() => { saveSuccess.value = false }, 3000)
    } else {
      saveError.value = response.data.message || 'Failed to save schedule'
    }
  } catch (err) {
    console.error('Error saving schedule:', err)
    saveError.value = err.message || 'Failed to save schedule'
  } finally {
    saving.value = false
  }
}

const addException = async () => {
  exceptionError.value = ''

  if (!newException.value.specific_date) {
    exceptionError.value = 'Date is required'
    return
  }
  if (newException.value.is_working) {
    if (!newException.value.start_time || !newException.value.end_time) {
      exceptionError.value = 'Start and end times are required for special hours'
      return
    }
  }

  addingException.value = true

  try {
    const payload = {
      specific_date: newException.value.specific_date,
      is_working:    newException.value.is_working,
      notes:         newException.value.notes || null
    }

    if (newException.value.is_working) {
      payload.start_time  = newException.value.start_time
      payload.end_time    = newException.value.end_time
      payload.break_start = newException.value.break_start || null
      payload.break_end   = newException.value.break_end || null
    }

    const response = await api.post(
      `staff/${staffId.value}/hours/exceptions`,
      payload
    )

    if (response.data.success) {
      const exceptionsResponse = await api.get(
        `staff/${staffId.value}/hours/exceptions`
      )
      if (exceptionsResponse.data.success) {
        exceptions.value = exceptionsResponse.data.exceptions
      }
      showAddException.value = false
      newException.value     = getDefaultException()
    } else {
      exceptionError.value = response.data.message || 'Failed to add exception'
    }
  } catch (err) {
    console.error('Error adding exception:', err)
    if (err.response?.data?.code === 'duplicate_exception') {
      exceptionError.value = 'An exception already exists for this date'
    } else {
      exceptionError.value = err.message || 'Failed to add exception'
    }
  } finally {
    addingException.value = false
  }
}

const deleteException = async (exception) => {
  if (!confirm(`Remove exception for ${formatDate(exception.specific_date)}?`)) return

  try {
    const response = await api.delete(
      `staff/${staffId.value}/hours/exceptions/${exception.id}`
    )

    if (response.data.success) {
      exceptions.value = exceptions.value.filter(e => e.id !== exception.id)
    }
  } catch (err) {
    console.error('Error deleting exception:', err)
    alert('Failed to remove exception')
  }
}

const cancelAddException = () => {
  showAddException.value = false
  exceptionError.value   = ''
  newException.value     = getDefaultException()
}

const goBack = () => {
  router.push('/staff')
}

const getInitials = (fullName) => {
  if (!fullName) return '??'
  const names = fullName.trim().split(' ').filter(n => n)
  if (names.length === 1) return names[0].substring(0, 2).toUpperCase()
  return (names[0][0] + names[names.length - 1][0]).toUpperCase()
}

const getColorForInitials = (name) => {
  const colors = [
    '#3B82F6', '#8B5CF6', '#EC4899', '#10B981',
    '#F59E0B', '#EF4444', '#6366F1', '#14B8A6'
  ]
  let hash = 0
  for (let i = 0; i < name.length; i++) {
    hash = name.charCodeAt(i) + ((hash << 5) - hash)
  }
  return colors[Math.abs(hash) % colors.length]
}

onMounted(() => {
  loadHours()
})
</script>
