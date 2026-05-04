<template>
  <!-- Modal Backdrop -->
  <div class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4" @click.self="$emit('close')">
    <!-- Modal Content -->
    <div
      ref="modalRef"
      role="dialog"
      aria-modal="true"
      aria-labelledby="booking-view-modal-title"
      class="bg-white rounded-lg shadow-xl max-w-3xl w-full max-h-[90vh] overflow-y-auto"
    >
      <!-- Header -->
      <div class="px-4 sm:px-6 py-4 border-b border-gray-200 flex items-center justify-between sticky top-0 bg-white z-10">
        <div>
          <h2 id="booking-view-modal-title" class="text-xl font-semibold text-gray-900">
            {{ editMode ? 'Edit Booking' : 'Booking Details' }}
          </h2>
          <p class="text-sm text-gray-500 mt-1">
            Booking {{ booking ? getBookingReference(booking) : `#${bookingId}` }}
          </p>
        </div>
        <div class="flex items-center gap-3">
          <button
            v-if="!editMode && canEdit"
            @click="enableEditMode"
            class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700"
          >
            Edit
          </button>
          <button
            @click="$emit('close')"
            class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100"
            aria-label="Close dialog"
          >
            <svg aria-hidden="true" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </div>

      <!-- Body -->
      <div class="px-4 sm:px-6 py-6">
        <!-- Loading State -->
        <div v-if="loading" class="text-center py-12">
          <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
          <p class="mt-2 text-sm text-gray-600">Loading booking details...</p>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-lg p-4">
          <div class="flex items-start">
            <span class="text-2xl mr-3">&#x26A0;&#xFE0F;</span>
            <div>
              <h3 class="text-sm font-medium text-red-800">Error Loading Booking</h3>
              <p class="text-sm text-red-700 mt-1">{{ error }}</p>
            </div>
          </div>
        </div>

        <!-- Booking Content -->
        <div v-else-if="booking" class="space-y-6">
          <div class="border border-primary-200 bg-primary-50 rounded-lg p-4">
            <label class="text-xs text-primary-700 uppercase tracking-wide">Booking Reference</label>
            <p class="text-lg font-semibold text-primary-900 mt-1">
              {{ getBookingReference(booking) }}
            </p>
          </div>

          <!-- READ-ONLY VIEW -->
          <div v-if="!editMode">
            <!-- Customer Information -->
            <div class="bg-gray-50 rounded-lg p-4">
              <h3 class="text-sm font-semibold text-gray-900 mb-3">Customer Information</h3>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label class="text-xs text-gray-600">Name</label>
                  <p class="text-sm font-medium text-gray-900">{{ booking.customer_name }}</p>
                </div>
                <div>
                  <label class="text-xs text-gray-600">Email</label>
                  <p class="text-sm text-gray-900">{{ booking.customer_email }}</p>
                </div>
                <div v-if="booking.customer_phone">
                  <label class="text-xs text-gray-600">Phone</label>
                  <p class="text-sm text-gray-900">{{ booking.customer_phone }}</p>
                </div>
              </div>
            </div>

            <!-- Service & Staff -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div class="border border-gray-200 rounded-lg p-4">
                <label class="text-xs text-gray-600">Service</label>
                <p class="text-sm font-medium text-gray-900 mt-1">{{ booking.service_name }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ booking.duration }} minutes</p>
              </div>
              <div class="border border-gray-200 rounded-lg p-4">
                <label class="text-xs text-gray-600">Staff Member</label>
                <p class="text-sm font-medium text-gray-900 mt-1">{{ booking.staff_name }}</p>
              </div>
            </div>

            <!-- Date & Time -->
            <div class="border border-gray-200 rounded-lg p-4">
              <label class="text-xs text-gray-600">Appointment</label>
              <p class="text-base font-semibold text-gray-900 mt-1">
                {{ formatDate(booking.booking_date) }} at {{ booking.start_time }}
              </p>
              <p class="text-xs text-gray-500 mt-1">
                {{ booking.start_time }} - {{ booking.end_time }} ({{ booking.duration }} min)
              </p>
            </div>

            <!-- Status & Payment -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div class="border border-gray-200 rounded-lg p-4">
                <label class="text-xs text-gray-600">Status</label>
                <div class="mt-2">
                  <span
                    class="px-2 py-1 text-xs font-medium rounded-full"
                    :class="getStatusClass(booking.status)"
                  >
                    {{ formatStatus(booking.status) }}
                  </span>
                </div>
              </div>
              <div class="border border-gray-200 rounded-lg p-4">
                <label class="text-xs text-gray-600">Payment</label>
                <div class="mt-2 space-y-1">
                  <div class="flex items-baseline justify-between">
                    <span class="text-xs text-gray-600">Service Price:</span>
                    <span class="text-sm font-medium text-gray-900">
                      &pound;{{ parseFloat(booking.total_price).toFixed(2) }}
                    </span>
                  </div>
                  <div class="flex items-baseline justify-between">
                    <span class="text-xs text-gray-600">Amount Paid:</span>
                    <span class="text-sm font-semibold" :class="getAmountPaidClass(booking)">
                      &pound;{{ parseFloat(booking.deposit_paid).toFixed(2) }}
                    </span>
                  </div>
                  <div v-if="parseFloat(booking.deposit_paid) !== parseFloat(booking.total_price)"
                       class="flex items-baseline justify-between pt-1 border-t border-gray-200">
                    <span class="text-xs font-medium" :class="getBalanceTextClass(booking)">
                      {{ getBalanceLabel(booking) }}:
                    </span>
                    <span class="text-sm font-semibold" :class="getBalanceClass(booking)">
                      &pound;{{ Math.abs(parseFloat(booking.deposit_paid) - parseFloat(booking.total_price)).toFixed(2) }}
                    </span>
                  </div>
                </div>
                <p class="text-xs text-gray-500 mt-3 pt-2 border-t border-gray-100">
                  {{ formatPaymentMethod(booking.payment_method) }}
                  {{ booking.full_amount_paid ? '&bull; Paid in full' : '' }}
                </p>
              </div>
            </div>

            <!-- Special Requests -->
            <div v-if="booking.special_requests" class="border border-gray-200 rounded-lg p-4">
              <label class="text-xs text-gray-600">Special Requests</label>
              <p class="text-sm text-gray-900 mt-1 whitespace-pre-wrap">{{ booking.special_requests }}</p>
            </div>

            <!-- Staff Notes -->
            <div v-if="booking.staff_notes" class="border border-gray-200 rounded-lg p-4 bg-yellow-50">
              <label class="text-xs text-gray-600">Staff Notes</label>
              <p class="text-sm text-gray-900 mt-1 whitespace-pre-wrap">{{ booking.staff_notes }}</p>
            </div>

            <!-- Metadata -->
            <div class="text-xs text-gray-500 pt-4 border-t border-gray-200">
              <p v-if="booking.created_at">Created: {{ formatDateTime(booking.created_at) }}</p>
              <p v-if="booking.updated_at">Updated: {{ formatDateTime(booking.updated_at) }}</p>
            </div>
          </div>

          <!-- EDIT MODE -->
          <div v-else class="space-y-4">
            <!-- Service Selection -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Service *
              </label>
              <select
                v-model="editData.service_id"
                @change="onServiceChange"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                required
              >
                <option value="">Select service...</option>
                <option
                  v-for="service in services"
                  :key="service.id"
                  :value="service.id"
                >
                  {{ service.name }} - {{ service.duration }}min - &pound;{{ formatPrice(service.price) }}
                </option>
              </select>
            </div>

            <!-- Staff Selection -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Staff Member *
              </label>
              <div v-if="loadingStaff" class="text-sm text-gray-500">
                Loading staff...
              </div>
              <select
                v-else
                v-model="editData.staff_id"
                @change="onStaffChange"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                required
              >
                <option value="">Select staff...</option>
                <option
                  v-for="staff in staffList"
                  :key="staff.id"
                  :value="staff.id"
                >
                  {{ staff.name }}
                </option>
              </select>
            </div>

            <!-- Date & Time Selection -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Date *
                </label>
                <input
                  v-model="editData.booking_date"
                  type="date"
                  :min="minDate"
                  @change="onDateChange"
                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                  required
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Time *
                </label>
                <select
                  v-model="editData.booking_time"
                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                  required
                  :disabled="!editData.booking_date || loadingSlots"
                >
                  <!-- Show current time if it's not in available slots (for past bookings) -->
                  <option
                    v-if="editData.booking_time && !isTimeInSlots(editData.booking_time)"
                    :value="editData.booking_time"
                  >
                    {{ formatSlotTime(editData.booking_time) }} (Current time)
                  </option>

                  <option value="">
                    {{ loadingSlots ? 'Loading...' : 'Select time...' }}
                  </option>

                  <!-- Available slots grouped by time of day -->
                  <optgroup v-if="timeslots?.morning?.length > 0" label="Morning">
                    <option v-for="slot in timeslots.morning" :key="slot" :value="slot">
                      {{ formatSlotTime(slot) }}
                    </option>
                  </optgroup>
                  <optgroup v-if="timeslots?.afternoon?.length > 0" label="Afternoon">
                    <option v-for="slot in timeslots.afternoon" :key="slot" :value="slot">
                      {{ formatSlotTime(slot) }}
                    </option>
                  </optgroup>
                  <optgroup v-if="timeslots?.evening?.length > 0" label="Evening">
                    <option v-for="slot in timeslots.evening" :key="slot" :value="slot">
                      {{ formatSlotTime(slot) }}
                    </option>
                  </optgroup>
                </select>

                <!-- Helper messages -->
                <div v-if="!loadingSlots && editData.booking_date" class="mt-2">
                  <p v-if="timeslotsError" class="text-xs text-red-600">
                    {{ timeslotsError }}
                  </p>
                  <p v-else-if="!timeslots || totalAvailableSlots === 0" class="text-xs text-amber-600">
                    &#x26A0;&#xFE0F; No available time slots for this date. You can keep the current time or select a different date.
                  </p>
                  <p v-else-if="editData.booking_time && !isTimeInSlots(editData.booking_time)" class="text-xs text-blue-600">
                    &#x1F4A1; Current time ({{ formatSlotTime(editData.booking_time) }}) is no longer available. Select a new time to reschedule.
                  </p>
                </div>
              </div>
            </div>

            <!-- Status -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Status *
              </label>
              <select
                v-model="editData.status"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                required
              >
                <option value="pending">Pending</option>
                <option value="pending_payment">Pending Payment</option>
                <option value="confirmed">Confirmed</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
                <option value="no_show">No Show</option>
              </select>
            </div>

            <!-- Payment Method & Amount -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Payment Method *
                </label>
                <select
                  v-model="editData.payment_method"
                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                  required
                >
                  <option value="pay_on_arrival">Pay on Arrival</option>
                  <option value="cash">Cash (Paid)</option>
                  <option value="card_external">Card (Paid Outside)</option>
                  <option value="check">Check (Paid)</option>
                  <option value="complimentary">Complimentary</option>
                  <option value="stripe">Stripe</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Amount Paid
                </label>
                <div class="relative">
                  <span class="absolute left-3 top-2 text-gray-500">&pound;</span>
                  <input
                    v-model.number="editData.amount_paid"
                    type="number"
                    step="0.01"
                    min="0"
                    class="w-full pl-8 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                  />
                </div>
              </div>
            </div>

            <!-- Special Requests -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Special Requests
              </label>
              <textarea
                v-model="editData.special_requests"
                rows="2"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                placeholder="Any special requests..."
              ></textarea>
            </div>

            <!-- Staff Notes -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Staff Notes (Internal)
              </label>
              <textarea
                v-model="editData.staff_notes"
                rows="2"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-yellow-50"
                placeholder="Internal notes..."
              ></textarea>
            </div>

            <!-- Send Notification -->
            <div>
              <label class="flex items-center">
                <input
                  v-model="editData.send_notification"
                  type="checkbox"
                  class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500"
                />
                <span class="ml-2 text-sm text-gray-700">
                  Send update notification to customer
                </span>
              </label>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="px-4 sm:px-6 py-4 border-t border-gray-200 bg-gray-50 sticky bottom-0">
        <div class="flex flex-col sm:flex-row justify-between items-stretch sm:items-center gap-3">
          <!-- Cancel Booking Button (left side) -->
          <button
            v-if="!editMode && canEdit && booking?.status !== 'cancelled'"
            @click="showCancelModal = true"
            class="w-full sm:w-auto px-4 py-2.5 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700"
          >
            Cancel Booking
          </button>
          <div v-else class="hidden sm:block"></div>

          <!-- Action Buttons (right side) -->
          <div class="flex flex-col-reverse sm:flex-row gap-2">
            <button
              v-if="editMode"
              @click="cancelEdit"
              :disabled="saving"
              class="w-full sm:w-auto px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50"
            >
              Cancel
            </button>
            <button
              v-if="!editMode"
              @click="$emit('close')"
              class="w-full sm:w-auto px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
            >
              Close
            </button>
            <button
              v-if="editMode"
              @click="saveChanges"
              :disabled="saving || !canSave"
              class="w-full sm:w-auto px-4 py-2.5 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {{ saving ? 'Saving...' : 'Save Changes' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Cancel Booking Modal -->
    <div v-if="showCancelModal" class="fixed inset-0 bg-black bg-opacity-50 z-[60] flex items-center justify-center p-4">
      <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-4 sm:p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Cancel Booking</h3>

        <p class="text-sm text-gray-600 mb-4">
          Are you sure you want to cancel this booking? This action cannot be undone.
        </p>

        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Cancellation Reason (optional)
          </label>
          <textarea
            v-model="cancellationReason"
            rows="3"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            placeholder="Why is this booking being cancelled?"
          ></textarea>
        </div>

        <div class="mb-4">
          <label class="flex items-center">
            <input
              v-model="cancelSendNotification"
              type="checkbox"
              class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500"
            />
            <span class="ml-2 text-sm text-gray-700">
              Send cancellation notification to customer
            </span>
          </label>
        </div>

        <div class="flex flex-col-reverse sm:flex-row justify-end gap-2">
          <button
            @click="showCancelModal = false; cancellationReason = ''"
            :disabled="cancelling"
            class="w-full sm:w-auto px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
          >
            Keep Booking
          </button>
          <button
            @click="confirmCancel"
            :disabled="cancelling"
            class="w-full sm:w-auto px-4 py-2.5 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-50"
          >
            {{ cancelling ? 'Cancelling...' : 'Yes, Cancel Booking' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Payment Warning Modal -->
    <div v-if="showPaymentWarning" class="fixed inset-0 bg-black bg-opacity-50 z-[60] flex items-center justify-center p-4">
      <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-4 sm:p-6">
        <div class="flex items-start mb-4">
          <span class="text-3xl mr-3">&#x26A0;&#xFE0F;</span>
          <div>
            <h3 class="text-lg font-semibold text-gray-900">Payment Issue Detected</h3>
          </div>
        </div>

        <p class="text-sm text-gray-700 mb-6">
          {{ paymentWarningMessage }}
        </p>

        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4">
          <p class="text-xs text-blue-800">
            <strong>Current Payment Details:</strong><br />
            Service Price: &pound;{{ parseFloat(booking?.total_price || 0).toFixed(2) }}<br />
            Amount Paid: &pound;{{ parseFloat(editData.amount_paid || 0).toFixed(2) }}<br />
            Balance: &pound;{{ (parseFloat(booking?.total_price || 0) - parseFloat(editData.amount_paid || 0)).toFixed(2) }}
          </p>
        </div>

        <div class="flex flex-col-reverse sm:flex-row justify-end gap-2">
          <button
            @click="showPaymentWarning = false"
            class="w-full sm:w-auto px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
          >
            Go Back to Edit
          </button>
          <button
            @click="forceSaveWithPaymentIssue"
            :disabled="saving"
            class="w-full sm:w-auto px-4 py-2.5 text-sm font-medium text-white bg-amber-600 rounded-lg hover:bg-amber-700 disabled:opacity-50"
          >
            {{ saving ? 'Saving...' : 'Save Anyway' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Optimistic Lock Conflict Modal -->
    <div v-if="showConflictModal" class="fixed inset-0 bg-black bg-opacity-50 z-[70] flex items-center justify-center p-4">
      <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-4 sm:p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-3">Booking Updated by Someone Else</h3>

        <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 mb-6">
          <p class="text-sm leading-relaxed text-gray-800">
            This booking was modified while you were editing it.
            Your changes have not been saved.
            Please close this form and reopen the booking to see the latest version.
          </p>
        </div>

        <div class="flex justify-end">
          <button
            @click="closeAndRefreshAfterConflict"
            class="w-full sm:w-auto px-4 py-2.5 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700"
          >
            Close and Refresh
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import { useApi } from '../composables/useApi'
import { useToast } from '../composables/useToast'

const api = useApi()
const { error: toastError } = useToast()

const props = defineProps({
  bookingId: {
    type: Number,
    required: true
  }
})

const emit = defineEmits(['close', 'updated', 'cancelled'])

const modalRef = ref(null)
const previousActiveElement = ref(null)

const getFocusableElements = () => {
  if (!modalRef.value) return []
  return Array.from(
    modalRef.value.querySelectorAll(
      'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
    )
  )
}

const trapFocus = (e) => {
  if (!modalRef.value) return

  const focusable = getFocusableElements()
  if (focusable.length === 0) return

  const first = focusable[0]
  const last = focusable[focusable.length - 1]

  if (e.key === 'Tab') {
    if (e.shiftKey) {
      if (document.activeElement === first) {
        last.focus()
        e.preventDefault()
      }
    } else {
      if (document.activeElement === last) {
        first.focus()
        e.preventDefault()
      }
    }
  }

  if (e.key === 'Escape') {
    emit('close')
  }
}

// Get current user role.
const currentUser = window.BOOKIT_DASHBOARD.staff

// State.
const loading = ref(true)
const error = ref(null)
const booking = ref(null)
const editMode = ref(false)
const saving = ref(false)
const cancelling = ref(false)

// Services & Staff.
const services = ref([])
const staffList = ref([])
const loadingStaff = ref(false)

// Timeslots.
const timeslots = ref(null)
const loadingSlots = ref(false)
const timeslotsError = ref('')

// Edit data.
const editData = ref({
  service_id: '',
  staff_id: '',
  booking_date: '',
  booking_time: '',
  status: '',
  payment_method: '',
  amount_paid: 0,
  special_requests: '',
  staff_notes: '',
  send_notification: true
})

// Payment warning modal.
const showPaymentWarning = ref(false)
const paymentWarningMessage = ref('')
const showConflictModal = ref(false)
const localLockVersion = ref('')

// Cancel modal.
const showCancelModal = ref(false)
const cancellationReason = ref('')
const cancelSendNotification = ref(true)

// Computed.
const canEdit = computed(() => {
  if (!booking.value) return false

  // Admin can edit any booking.
  if (currentUser.role === 'admin') return true

  // Staff can only edit their own bookings.
  return currentUser.id === booking.value.staff_id
})

const canSave = computed(() => {
  return editData.value.service_id &&
         editData.value.staff_id &&
         editData.value.booking_date &&
         editData.value.booking_time &&
         editData.value.status &&
         editData.value.payment_method
})

const minDate = computed(() => {
  const today = new Date()
  return today.toISOString().split('T')[0]
})

const totalAvailableSlots = computed(() => {
  if (!timeslots.value) return 0
  return (timeslots.value.morning?.length || 0) +
         (timeslots.value.afternoon?.length || 0) +
         (timeslots.value.evening?.length || 0)
})

const isTimeInSlots = (time) => {
  if (!time || !timeslots.value) return false

  const allSlots = [
    ...(timeslots.value.morning || []),
    ...(timeslots.value.afternoon || []),
    ...(timeslots.value.evening || [])
  ]

  return allSlots.includes(time)
}

// Methods: data loading.
const loadBooking = async () => {
  loading.value = true
  error.value = null

  try {
    const response = await api.get(`/bookings/${props.bookingId}`)

    if (response.data.success) {
      booking.value = response.data.booking
      localLockVersion.value = response.data.booking?.lock_version || ''
    } else {
      throw new Error(response.data.message || 'Failed to load booking')
    }
  } catch (err) {
    console.error('Error loading booking:', err)
    error.value = err.message || 'Failed to load booking details'
  } finally {
    loading.value = false
  }
}

const loadServices = async () => {
  try {
    const response = await api.get('/services/list')
    if (response.data.success) {
      services.value = response.data.services
    }
  } catch (err) {
    console.error('Error loading services:', err)
  }
}

const loadStaffForService = async (serviceId) => {
  if (!serviceId) return

  loadingStaff.value = true
  try {
    const response = await api.get(`/staff/by-service/${serviceId}`)
    if (response.data.success) {
      staffList.value = response.data.staff
    }
  } catch (err) {
    console.error('Error loading staff:', err)
    staffList.value = []
  } finally {
    loadingStaff.value = false
  }
}

const loadTimeslots = async () => {
  if (!editData.value.booking_date || !editData.value.service_id || !editData.value.staff_id) {
    return
  }

  loadingSlots.value = true
  timeslotsError.value = ''

  try {
    const params = new URLSearchParams({
      date: editData.value.booking_date,
      service_id: editData.value.service_id,
      staff_id: editData.value.staff_id
    })

    const response = await api.get(`timeslots?${params.toString()}`)

    if (response.data.success === false || response.data.available === false) {
      timeslotsError.value = response.data.message || 'No available times'
      timeslots.value = null
    } else {
      timeslots.value = response.data.slots
      timeslotsError.value = ''
    }
  } catch (err) {
    console.error('Error loading timeslots:', err)
    timeslotsError.value = err.message || 'Failed to load available times'
    timeslots.value = null
  } finally {
    loadingSlots.value = false
  }
}

// Methods: edit mode.
const enableEditMode = () => {
  editMode.value = true

  // Populate edit data from current booking.
  editData.value = {
    service_id: booking.value.service_id,
    staff_id: booking.value.staff_id,
    booking_date: booking.value.booking_date,
    booking_time: booking.value.start_time,
    status: booking.value.status,
    payment_method: booking.value.payment_method,
    amount_paid: parseFloat(booking.value.deposit_paid) || 0,
    special_requests: booking.value.special_requests || '',
    staff_notes: booking.value.staff_notes || '',
    send_notification: true
  }
  localLockVersion.value = booking.value.lock_version || ''

  // Load services and staff for dropdowns.
  loadServices()
  loadStaffForService(booking.value.service_id)
  loadTimeslots()
}

const cancelEdit = () => {
  editMode.value = false
  editData.value = {
    service_id: '',
    staff_id: '',
    booking_date: '',
    booking_time: '',
    status: '',
    payment_method: '',
    amount_paid: 0,
    special_requests: '',
    staff_notes: '',
    send_notification: true
  }
}

// Methods: field change handlers.
const onServiceChange = () => {
  // Reload staff when service changes.
  loadStaffForService(editData.value.service_id)
  editData.value.staff_id = '' // Clear staff selection.
  // Don't clear time - let the user see they need to change it if unavailable.
}

const onStaffChange = () => {
  // Reload timeslots when staff changes.
  // Don't clear time - let timeslots load to show if current time is still available.
  loadTimeslots()
}

const onDateChange = () => {
  // Reload timeslots when date changes.
  // Don't clear time - let timeslots load to show if current time is still available.
  loadTimeslots()
}

// Methods: payment validation.
const validatePaymentForCompletion = () => {
  // Only validate if changing status to completed.
  if (editData.value.status !== 'completed') {
    return true
  }

  const servicePrice = parseFloat(booking.value.total_price) || 0
  const amountPaid = parseFloat(editData.value.amount_paid) || 0

  // Check if overpaid.
  if (amountPaid > servicePrice) {
    paymentWarningMessage.value = `Warning: Amount paid (\u00A3${amountPaid.toFixed(2)}) exceeds service price (\u00A3${servicePrice.toFixed(2)}). This may be intentional (tip included) or an error.`
    showPaymentWarning.value = true
    return false
  }

  // Check if underpaid.
  if (amountPaid < servicePrice) {
    const remaining = servicePrice - amountPaid
    paymentWarningMessage.value = `Warning: Customer has only paid \u00A3${amountPaid.toFixed(2)} of \u00A3${servicePrice.toFixed(2)}. There is \u00A3${remaining.toFixed(2)} remaining. Are you sure you want to mark this as completed?`
    showPaymentWarning.value = true
    return false
  }

  // Fully paid - no warning needed.
  return true
}

// Methods: save and cancel actions.
const executeSave = async () => {
  saving.value = true

  try {
    const payload = {
      service_id: editData.value.service_id,
      staff_id: editData.value.staff_id,
      booking_date: editData.value.booking_date,
      booking_time: editData.value.booking_time,
      status: editData.value.status,
      payment_method: editData.value.payment_method,
      amount_paid: editData.value.amount_paid,
      special_requests: editData.value.special_requests,
      staff_notes: editData.value.staff_notes,
      send_notification: editData.value.send_notification,
      lock_version: localLockVersion.value
    }

    const response = await api.put(`/bookings/${props.bookingId}`, payload)

    if (response.data.success) {
      localLockVersion.value = response.data.lock_version || response.data.booking?.lock_version || ''
      if (booking.value) {
        booking.value.lock_version = localLockVersion.value
      }
      emit('updated', response.data.booking)
      emit('close')
    } else {
      throw new Error(response.data.message || 'Failed to update booking')
    }
  } catch (err) {
    console.error('Error updating booking:', err)
    if (err?.code === 'E2004' && err?.status === 409) {
      showConflictModal.value = true
      return
    }
    toastError(`Error updating booking: ${err.message}`)
  } finally {
    saving.value = false
  }
}

const closeAndRefreshAfterConflict = () => {
  showConflictModal.value = false
  editMode.value = false
  emit('updated', booking.value)
  emit('close')
}

const saveChanges = async () => {
  if (!canSave.value || saving.value) return

  // Validate payment if marking as completed.
  if (!validatePaymentForCompletion()) {
    return // Show warning modal.
  }

  await executeSave()
}

const forceSaveWithPaymentIssue = async () => {
  showPaymentWarning.value = false
  await executeSave()
}

const confirmCancel = async () => {
  cancelling.value = true

  try {
    const response = await api.post(`/bookings/${props.bookingId}/cancel`, {
      cancellation_reason: cancellationReason.value,
      send_notification: cancelSendNotification.value
    })

    if (response.data.success) {
      emit('cancelled', props.bookingId)
      emit('close')
    } else {
      throw new Error(response.data.message || 'Failed to cancel booking')
    }
  } catch (err) {
    console.error('Error cancelling booking:', err)
    toastError(`Error cancelling booking: ${err.message}`)
  } finally {
    cancelling.value = false
  }
}

// Methods: formatting.
const formatDate = (dateString) => {
  const date = new Date(dateString + 'T00:00:00')
  return date.toLocaleDateString('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric'
  })
}

const getBookingReference = (bookingData) => {
  if (bookingData?.booking_reference && bookingData.booking_reference.trim() !== '') {
    return bookingData.booking_reference
  }
  return `#${bookingData.id}`
}

const formatDateTime = (dateTimeString) => {
  if (!dateTimeString) return ''
  const date = new Date(dateTimeString)
  return date.toLocaleString('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const formatSlotTime = (time) => {
  if (!time) return ''
  // Slots come as "HH:mm:ss" -- show "HH:mm".
  return time.substring(0, 5)
}

const formatStatus = (status) => {
  const labels = {
    'pending': 'Pending',
    'pending_payment': 'Pending Payment',
    'confirmed': 'Confirmed',
    'completed': 'Completed',
    'cancelled': 'Cancelled',
    'no_show': 'No Show'
  }
  return labels[status] || status
}

const getStatusClass = (status) => {
  const classes = {
    'pending': 'bg-yellow-100 text-yellow-800',
    'pending_payment': 'bg-orange-100 text-orange-800',
    'confirmed': 'bg-green-100 text-green-800',
    'completed': 'bg-blue-100 text-blue-800',
    'cancelled': 'bg-red-100 text-red-800',
    'no_show': 'bg-gray-100 text-gray-800'
  }
  return classes[status] || 'bg-gray-100 text-gray-800'
}

const getAmountPaidClass = (booking) => {
  const total = parseFloat(booking.total_price) || 0
  const paid = parseFloat(booking.deposit_paid) || 0

  if (paid > total) return 'text-green-700'   // Overpaid (tip).
  if (paid >= total) return 'text-green-600'   // Fully paid.
  if (paid > 0) return 'text-amber-600'        // Partially paid.
  return 'text-gray-900'                       // Unpaid.
}

const getBalanceLabel = (booking) => {
  const total = parseFloat(booking.total_price) || 0
  const paid = parseFloat(booking.deposit_paid) || 0

  return paid > total ? 'Tip/Gratuity' : 'Balance Due'
}

const getBalanceTextClass = (booking) => {
  const total = parseFloat(booking.total_price) || 0
  const paid = parseFloat(booking.deposit_paid) || 0

  return paid > total ? 'text-green-600' : 'text-red-600'
}

const getBalanceClass = (booking) => {
  const total = parseFloat(booking.total_price) || 0
  const paid = parseFloat(booking.deposit_paid) || 0

  return paid > total ? 'text-green-700' : 'text-red-600'
}

const formatPaymentMethod = (method) => {
  const labels = {
    'pay_on_arrival': 'Pay on Arrival',
    'cash': 'Cash',
    'card_external': 'Card',
    'check': 'Check',
    'complimentary': 'Complimentary',
    'stripe': 'Stripe'
  }
  return labels[method] || method || 'Unknown'
}

const formatPrice = (price) => {
  const num = parseFloat(price)
  return isNaN(num) ? '0.00' : num.toFixed(2)
}

// Lifecycle: load booking and set up focus trap.
onMounted(async () => {
  previousActiveElement.value = document.activeElement
  document.addEventListener('keydown', trapFocus)

  await nextTick()
  const focusable = getFocusableElements()
  if (focusable.length > 0) {
    focusable[0].focus()
  }

  loadBooking()
})

onUnmounted(() => {
  document.removeEventListener('keydown', trapFocus)
  if (previousActiveElement.value && previousActiveElement.value.focus) {
    previousActiveElement.value.focus()
  }
})
</script>
