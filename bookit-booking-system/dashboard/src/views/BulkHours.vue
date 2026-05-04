<template>
  <div class="p-4 sm:p-6 max-w-6xl mx-auto">
    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900">Bulk Working Hours</h1>
      <p class="text-sm text-gray-600 mt-1">
        Manage working hours for multiple staff members at once
      </p>
    </div>

    <!-- Success/Error Messages -->
    <div v-if="operationSuccess" class="mb-4 bg-green-50 border border-green-200 rounded p-3">
      <p class="text-sm text-green-800">{{ operationSuccess }}</p>
    </div>
    <div v-if="operationError" class="mb-4 bg-red-50 border border-red-200 rounded p-3">
      <p class="text-sm text-red-800">{{ operationError }}</p>
    </div>

    <!-- Staff Selection Card -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
      <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-lg font-semibold text-gray-900">Select Staff Members</h2>
            <p class="text-sm text-gray-500 mt-1">
              Choose which staff to apply bulk operations to
            </p>
          </div>
          <div class="text-sm text-gray-600">
            {{ selectedStaffIds.length }} selected
          </div>
        </div>
      </div>

      <div v-if="loadingStaff" class="px-6 py-8 text-center">
        <div class="inline-block animate-spin rounded-full h-6 w-6 border-b-2 border-primary-600"></div>
        <p class="mt-2 text-sm text-gray-600">Loading staff...</p>
      </div>

      <div v-else class="px-4 sm:px-6 py-4">
        <!-- Select All -->
        <div class="mb-3 pb-3 border-b border-gray-200">
          <label class="flex items-center cursor-pointer">
            <input
              type="checkbox"
              :checked="allSelected"
              @change="toggleSelectAll"
              class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500"
            />
            <span class="ml-2 text-sm font-medium text-gray-900">
              Select All Staff ({{ staffList.length }})
            </span>
          </label>
        </div>

        <!-- Staff Checkboxes -->
        <div class="space-y-1">
          <label
            v-for="staff in staffList"
            :key="staff.id"
            class="flex items-center gap-3 p-3 hover:bg-gray-50 rounded-lg cursor-pointer border border-transparent hover:border-gray-200 transition-colors"
          >
            <input
              type="checkbox"
              :value="staff.id"
              v-model="selectedStaffIds"
              class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500 flex-shrink-0"
            />
            <div class="flex-shrink-0">
              <img
                v-if="staff.photo_url"
                :src="staff.photo_url"
                :alt="staff.full_name"
                class="w-10 h-10 rounded-full object-cover"
              />
              <div
                v-else
                class="w-10 h-10 rounded-full flex items-center justify-center text-white text-sm font-semibold"
                :style="{ backgroundColor: getColorForInitials(staff.full_name) }"
              >
                {{ getInitials(staff.full_name) }}
              </div>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-gray-900 truncate">{{ staff.full_name }}</p>
              <p v-if="staff.title" class="text-xs text-gray-500 truncate">{{ staff.title }}</p>
            </div>
          </label>
        </div>

        <div v-if="staffList.length === 0" class="text-center py-6 text-gray-500 text-sm">
          No active staff members found.
        </div>
      </div>
    </div>

    <!-- Operation Selection -->
    <div v-if="selectedStaffIds.length > 0" class="space-y-6">
      <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
          <h2 class="text-lg font-semibold text-gray-900">Bulk Operations</h2>
          <p class="text-sm text-gray-500 mt-1">
            Choose an operation to apply to {{ selectedStaffIds.length }} selected staff
          </p>
        </div>

        <div class="p-4 sm:p-6 space-y-4">
          <!-- Operation Type Selection -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <button
              @click="operationType = 'exception'"
              class="p-4 border-2 rounded-lg text-left transition-all"
              :class="operationType === 'exception'
                ? 'border-primary-600 bg-primary-50'
                : 'border-gray-200 hover:border-gray-300'"
            >
              <div class="text-lg mb-1">📅</div>
              <div class="font-semibold text-gray-900">Add Date Exception</div>
              <div class="text-xs text-gray-600 mt-1">
                Set day off or special hours for a specific date
              </div>
            </button>

            <button
              @click="operationType = 'schedule'"
              class="p-4 border-2 rounded-lg text-left transition-all"
              :class="operationType === 'schedule'
                ? 'border-primary-600 bg-primary-50'
                : 'border-gray-200 hover:border-gray-300'"
            >
              <div class="text-lg mb-1">🗓️</div>
              <div class="font-semibold text-gray-900">Update Weekly Schedule</div>
              <div class="text-xs text-gray-600 mt-1">
                Change regular hours or break times for a day of the week
              </div>
            </button>
          </div>

          <!-- Exception Form -->
          <div v-if="operationType === 'exception'" class="mt-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-3">Date Exception Details</h3>

            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date *</label>
                <input
                  v-model="exceptionForm.specific_date"
                  type="date"
                  :min="today"
                  required
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Exception Type *</label>
                <select
                  v-model="exceptionForm.is_working"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                >
                  <option :value="false">Day Off</option>
                  <option :value="true">Special Hours</option>
                </select>
              </div>

              <!-- Times (if working) -->
              <div v-if="exceptionForm.is_working">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Start Time *</label>
                    <input
                      v-model="exceptionForm.start_time"
                      type="time"
                      required
                      class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary-500"
                      :class="exceptionValidation.times
                        ? 'border-red-500 focus:border-red-500'
                        : 'border-gray-300 focus:border-primary-500'"
                    />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">End Time *</label>
                    <input
                      v-model="exceptionForm.end_time"
                      type="time"
                      required
                      class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary-500"
                      :class="exceptionValidation.times
                        ? 'border-red-500 focus:border-red-500'
                        : 'border-gray-300 focus:border-primary-500'"
                    />
                  </div>
                </div>
                <p v-if="exceptionValidation.times" class="text-xs text-red-600 mt-1">
                  {{ exceptionValidation.times }}
                </p>
              </div>

              <!-- Break Times (if working) -->
              <div v-if="exceptionForm.is_working">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Break Start</label>
                    <input
                      v-model="exceptionForm.break_start"
                      type="time"
                      class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary-500"
                      :class="exceptionValidation.break
                        ? 'border-red-500 focus:border-red-500'
                        : 'border-gray-300 focus:border-primary-500'"
                    />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Break End</label>
                    <input
                      v-model="exceptionForm.break_end"
                      type="time"
                      class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary-500"
                      :class="exceptionValidation.break
                        ? 'border-red-500 focus:border-red-500'
                        : 'border-gray-300 focus:border-primary-500'"
                    />
                  </div>
                </div>
                <p v-if="exceptionValidation.break" class="text-xs text-red-600 mt-1">
                  {{ exceptionValidation.break }}
                </p>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <input
                  v-model="exceptionForm.notes"
                  type="text"
                  placeholder="e.g., Bank Holiday, Team Training"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                />
              </div>
            </div>
          </div>

          <!-- Schedule Update Form -->
          <div v-if="operationType === 'schedule'" class="mt-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-3">Schedule Update Details</h3>

            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Day of Week *</label>
                <select
                  v-model.number="scheduleForm.day_of_week"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                >
                  <option :value="1">Monday</option>
                  <option :value="2">Tuesday</option>
                  <option :value="3">Wednesday</option>
                  <option :value="4">Thursday</option>
                  <option :value="5">Friday</option>
                  <option :value="6">Saturday</option>
                  <option :value="7">Sunday</option>
                </select>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">What to Update</label>
                <div class="space-y-2">
                  <label class="flex items-center">
                    <input
                      v-model="updateFields.working_hours"
                      type="checkbox"
                      class="w-4 h-4 text-primary-600 border-gray-300 rounded"
                    />
                    <span class="ml-2 text-sm text-gray-700">Working Hours (Start/End Time)</span>
                  </label>
                  <label class="flex items-center">
                    <input
                      v-model="updateFields.break_times"
                      type="checkbox"
                      class="w-4 h-4 text-primary-600 border-gray-300 rounded"
                    />
                    <span class="ml-2 text-sm text-gray-700">Break Times</span>
                  </label>
                </div>
              </div>

              <div v-if="updateFields.working_hours">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Start Time *</label>
                    <input
                      v-model="scheduleForm.start_time"
                      type="time"
                      required
                      class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary-500"
                      :class="scheduleValidation.times
                        ? 'border-red-500 focus:border-red-500'
                        : 'border-gray-300 focus:border-primary-500'"
                    />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">End Time *</label>
                    <input
                      v-model="scheduleForm.end_time"
                      type="time"
                      required
                      class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary-500"
                      :class="scheduleValidation.times
                        ? 'border-red-500 focus:border-red-500'
                        : 'border-gray-300 focus:border-primary-500'"
                    />
                  </div>
                </div>
                <p v-if="scheduleValidation.times" class="text-xs text-red-600 mt-1">
                  {{ scheduleValidation.times }}
                </p>
              </div>

              <div v-if="updateFields.break_times">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Break Start</label>
                    <input
                      v-model="scheduleForm.break_start"
                      type="time"
                      class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary-500"
                      :class="scheduleValidation.break
                        ? 'border-red-500 focus:border-red-500'
                        : 'border-gray-300 focus:border-primary-500'"
                    />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Break End</label>
                    <input
                      v-model="scheduleForm.break_end"
                      type="time"
                      class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary-500"
                      :class="scheduleValidation.break
                        ? 'border-red-500 focus:border-red-500'
                        : 'border-gray-300 focus:border-primary-500'"
                    />
                  </div>
                </div>
                <p v-if="scheduleValidation.break" class="text-xs text-red-600 mt-1">
                  {{ scheduleValidation.break }}
                </p>
              </div>
            </div>
          </div>

          <!-- Preview Button -->
          <div class="flex justify-end pt-4 border-t border-gray-200">
            <button
              @click="previewChanges"
              :disabled="!canPreview"
              class="w-full sm:w-auto px-4 py-2.5 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Preview Changes
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Preview Modal -->
    <div
      v-if="showPreview"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
      @click.self="showPreview = false"
    >
      <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-hidden">
        <!-- Modal Header -->
        <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
          <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900">Preview Changes</h3>
            <button
              @click="showPreview = false"
              class="text-gray-400 hover:text-gray-600"
            >
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>

        <!-- Modal Body -->
        <div class="px-4 sm:px-6 py-4 overflow-y-auto max-h-[calc(90vh-140px)]">
          <div v-if="checkingConflicts" class="text-center py-8">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
            <p class="mt-2 text-sm text-gray-600">Checking for conflicts...</p>
          </div>

          <div v-else>
            <!-- Summary -->
            <div class="mb-4 bg-blue-50 border border-blue-200 rounded p-4">
              <p class="text-sm font-medium text-blue-900 mb-2">
                {{ operationType === 'exception' ? 'Add Date Exception' : 'Update Weekly Schedule' }}
              </p>
              <div class="text-sm text-blue-800">
                <template v-if="operationType === 'exception'">
                  <p>
                    <strong>Date:</strong> {{ formatDate(exceptionForm.specific_date) }}<br />
                    <strong>Type:</strong> {{ exceptionForm.is_working ? 'Special Hours' : 'Day Off' }}
                    <template v-if="exceptionForm.is_working">
                      <br /><strong>Hours:</strong> {{ exceptionForm.start_time }} - {{ exceptionForm.end_time }}
                    </template>
                    <template v-if="exceptionForm.notes">
                      <br /><strong>Notes:</strong> {{ exceptionForm.notes }}
                    </template>
                  </p>
                </template>
                <template v-else>
                  <p>
                    <strong>Day:</strong> {{ getDayName(scheduleForm.day_of_week) }}<br />
                    <strong>Updates:</strong>
                    <span v-if="updateFields.working_hours">
                      {{ scheduleForm.start_time }} - {{ scheduleForm.end_time }}
                    </span>
                    <span v-if="updateFields.break_times">
                      {{ updateFields.working_hours ? ', ' : '' }}Break: {{ scheduleForm.break_start }} - {{ scheduleForm.break_end }}
                    </span>
                  </p>
                </template>
              </div>
            </div>

            <!-- Conflicts -->
            <div v-if="conflicts.length > 0" class="mb-4">
              <h4 class="text-sm font-semibold text-amber-900 mb-2">
                Conflicts Detected ({{ conflicts.length }})
              </h4>
              <div class="space-y-2">
                <div
                  v-for="conflict in conflicts"
                  :key="conflict.staff_id"
                  class="bg-amber-50 border border-amber-200 rounded p-3"
                >
                  <div class="flex items-start justify-between">
                    <div class="flex-1">
                      <p class="text-sm font-medium text-amber-900">
                        {{ conflict.staff_name }}
                      </p>
                      <p class="text-xs text-amber-700 mt-1">
                        Already has: {{ conflict.is_working ? 'Special Hours' : 'Day Off' }}
                        <span v-if="conflict.is_working">
                          ({{ conflict.start_time }} - {{ conflict.end_time }})
                        </span>
                        <span v-if="conflict.notes" class="block mt-1">
                          Note: "{{ conflict.notes }}"
                        </span>
                      </p>
                    </div>
                    <label class="flex items-center ml-3">
                      <input
                        type="checkbox"
                        :value="conflict.staff_id"
                        v-model="overwriteConflicts"
                        class="w-4 h-4 text-amber-600 border-gray-300 rounded"
                      />
                      <span class="ml-2 text-xs text-amber-900 whitespace-nowrap">Overwrite</span>
                    </label>
                  </div>
                </div>
              </div>
            </div>

            <!-- Staff List -->
            <div>
              <h4 class="text-sm font-semibold text-gray-900 mb-2">
                Will be applied to {{ selectedStaffIds.length }} staff:
              </h4>
              <div class="bg-gray-50 border border-gray-200 rounded p-3 max-h-40 overflow-y-auto">
                <div class="flex flex-wrap gap-2">
                  <span
                    v-for="staffId in selectedStaffIds"
                    :key="staffId"
                    class="inline-flex items-center px-2 py-1 text-xs font-medium rounded"
                    :class="isConflictSkipped(staffId)
                      ? 'bg-amber-100 text-amber-800'
                      : 'bg-blue-100 text-blue-800'"
                  >
                    {{ getStaffName(staffId) }}
                    <span v-if="isConflictSkipped(staffId)" class="ml-1">(will skip)</span>
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="px-4 sm:px-6 py-4 border-t border-gray-200">
          <div class="flex flex-col-reverse sm:flex-row justify-end gap-2">
            <button
              @click="showPreview = false"
              class="w-full sm:w-auto px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
            >
              Cancel
            </button>
            <button
              @click="applyChanges"
              :disabled="applying"
              class="w-full sm:w-auto px-4 py-2.5 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 disabled:opacity-50"
            >
              {{ applying ? 'Applying...' : 'Apply Changes' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useApi } from '../composables/useApi'

const api = useApi()

const loadingStaff = ref(false)
const checkingConflicts = ref(false)
const applying = ref(false)
const staffList = ref([])
const selectedStaffIds = ref([])
const operationType = ref('exception')
const showPreview = ref(false)
const conflicts = ref([])
const overwriteConflicts = ref([])
const operationSuccess = ref('')
const operationError = ref('')

const updateFields = ref({
  working_hours: false,
  break_times: false
})

const exceptionForm = ref({
  specific_date: '',
  is_working: false,
  start_time: '09:00',
  end_time: '17:00',
  break_start: '',
  break_end: '',
  notes: ''
})

const scheduleForm = ref({
  day_of_week: 1,
  start_time: '09:00',
  end_time: '17:00',
  break_start: '12:00',
  break_end: '13:00'
})

const today = computed(() => new Date().toISOString().split('T')[0])

const allSelected = computed(() => {
  return staffList.value.length > 0 && selectedStaffIds.value.length === staffList.value.length
})

const exceptionValidation = computed(() => {
  const errors = {}
  if (exceptionForm.value.is_working) {
    if (exceptionForm.value.start_time && exceptionForm.value.end_time) {
      if (exceptionForm.value.start_time >= exceptionForm.value.end_time) {
        errors.times = 'End time must be after start time'
      }
    }
    if (exceptionForm.value.break_start && exceptionForm.value.break_end) {
      if (exceptionForm.value.break_start >= exceptionForm.value.break_end) {
        errors.break = 'Break end must be after break start'
      } else if (exceptionForm.value.start_time && exceptionForm.value.break_start <= exceptionForm.value.start_time) {
        errors.break = 'Break must start after work start time'
      } else if (exceptionForm.value.end_time && exceptionForm.value.break_end >= exceptionForm.value.end_time) {
        errors.break = 'Break must end before work end time'
      }
    }
  }
  return errors
})

const scheduleValidation = computed(() => {
  const errors = {}
  if (updateFields.value.working_hours) {
    if (scheduleForm.value.start_time && scheduleForm.value.end_time) {
      if (scheduleForm.value.start_time >= scheduleForm.value.end_time) {
        errors.times = 'End time must be after start time'
      }
    }
  }
  if (updateFields.value.break_times && scheduleForm.value.break_start && scheduleForm.value.break_end) {
    if (scheduleForm.value.break_start >= scheduleForm.value.break_end) {
      errors.break = 'Break end must be after break start'
    } else if (updateFields.value.working_hours) {
      if (scheduleForm.value.break_start <= scheduleForm.value.start_time) {
        errors.break = 'Break must start after work start time'
      } else if (scheduleForm.value.break_end >= scheduleForm.value.end_time) {
        errors.break = 'Break must end before work end time'
      }
    }
  }
  return errors
})

const exceptionFormValid = computed(() => Object.keys(exceptionValidation.value).length === 0)
const scheduleFormValid = computed(() => Object.keys(scheduleValidation.value).length === 0)

const canPreview = computed(() => {
  if (operationType.value === 'exception') {
    return exceptionForm.value.specific_date &&
           selectedStaffIds.value.length > 0 &&
           exceptionFormValid.value
  }
  return (updateFields.value.working_hours || updateFields.value.break_times) &&
         selectedStaffIds.value.length > 0 &&
         scheduleFormValid.value
})

const loadStaff = async () => {
  loadingStaff.value = true
  try {
    const response = await api.get('staff/list')
    if (response.data.success) {
      staffList.value = response.data.staff.filter(s => s.is_active)
    }
  } catch (err) {
    console.error('Error loading staff:', err)
  } finally {
    loadingStaff.value = false
  }
}

const toggleSelectAll = () => {
  if (allSelected.value) {
    selectedStaffIds.value = []
  } else {
    selectedStaffIds.value = staffList.value.map(s => s.id)
  }
}

const isConflictSkipped = (staffId) => {
  return conflicts.value.some(c => c.staff_id === staffId) && !overwriteConflicts.value.includes(staffId)
}

const previewChanges = async () => {
  if (!canPreview.value) return

  showPreview.value = true
  checkingConflicts.value = true
  conflicts.value = []
  overwriteConflicts.value = []

  try {
    if (operationType.value === 'exception') {
      const response = await api.post('staff/bulk-hours/check-conflicts', {
        staff_ids: selectedStaffIds.value,
        specific_date: exceptionForm.value.specific_date
      })
      if (response.data.success) {
        conflicts.value = response.data.conflicts
      }
    }
  } catch (err) {
    console.error('Error checking conflicts:', err)
  } finally {
    checkingConflicts.value = false
  }
}

const applyChanges = async () => {
  applying.value = true
  operationSuccess.value = ''
  operationError.value = ''

  try {
    if (operationType.value === 'exception') {
      const response = await api.post('staff/bulk-hours/add-exception', {
        staff_ids: selectedStaffIds.value,
        specific_date: exceptionForm.value.specific_date,
        is_working: exceptionForm.value.is_working,
        start_time: exceptionForm.value.start_time || null,
        end_time: exceptionForm.value.end_time || null,
        break_start: exceptionForm.value.break_start || null,
        break_end: exceptionForm.value.break_end || null,
        notes: exceptionForm.value.notes || null,
        overwrite_conflicts: overwriteConflicts.value
      })
      if (response.data.success) {
        operationSuccess.value = response.data.message
        showPreview.value = false
        resetForms()
        setTimeout(() => { operationSuccess.value = '' }, 5000)
      }
    } else {
      const updates = {}
      if (updateFields.value.working_hours) {
        updates.start_time = scheduleForm.value.start_time
        updates.end_time = scheduleForm.value.end_time
      }
      if (updateFields.value.break_times) {
        updates.break_start = scheduleForm.value.break_start
        updates.break_end = scheduleForm.value.break_end
      }

      const response = await api.post('staff/bulk-hours/update-schedule', {
        staff_ids: selectedStaffIds.value,
        day_of_week: scheduleForm.value.day_of_week,
        updates
      })
      if (response.data.success) {
        operationSuccess.value = response.data.message
        showPreview.value = false
        resetForms()
        setTimeout(() => { operationSuccess.value = '' }, 5000)
      }
    }
  } catch (err) {
    console.error('Error applying changes:', err)
    operationError.value = err.message || 'Failed to apply changes'
  } finally {
    applying.value = false
  }
}

const resetForms = () => {
  selectedStaffIds.value = []
  exceptionForm.value = {
    specific_date: '',
    is_working: false,
    start_time: '09:00',
    end_time: '17:00',
    break_start: '',
    break_end: '',
    notes: ''
  }
  scheduleForm.value = {
    day_of_week: 1,
    start_time: '09:00',
    end_time: '17:00',
    break_start: '12:00',
    break_end: '13:00'
  }
  updateFields.value = {
    working_hours: false,
    break_times: false
  }
}

const getStaffName = (staffId) => {
  const staff = staffList.value.find(s => s.id === staffId)
  return staff ? staff.full_name : `Staff ${staffId}`
}

const formatDate = (dateStr) => {
  if (!dateStr) return ''
  const date = new Date(dateStr + 'T00:00:00')
  return date.toLocaleDateString('en-GB', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric'
  })
}

const getDayName = (dayNum) => {
  const days = ['', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']
  return days[dayNum] || ''
}

const getInitials = (fullName) => {
  if (!fullName) return '??'
  const names = fullName.trim().split(' ').filter(n => n)
  if (names.length === 0) return '??'
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
  loadStaff()
})
</script>
