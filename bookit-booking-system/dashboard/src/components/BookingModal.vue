<template>
  <!-- Modal Backdrop -->
  <div class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4" @click.self="$emit('close')">
    <!-- Modal Content -->
    <div
      ref="modalRef"
      role="dialog"
      aria-modal="true"
      aria-labelledby="booking-modal-title"
      class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto"
    >
      <!-- Header -->
      <div class="px-4 sm:px-6 py-4 border-b border-gray-200 flex items-center justify-between sticky top-0 bg-white z-10">
        <div>
          <h2 id="booking-modal-title" class="text-xl font-semibold text-gray-900">
            Create New Booking
          </h2>
          <p class="text-sm text-gray-500 mt-1">
            Step {{ currentStep }} of 5: {{ stepTitle }}
          </p>
        </div>
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

      <!-- Body -->
      <div class="px-4 sm:px-6 py-6">
        <!-- Step 1: Customer Selection -->
        <CustomerSelector
          v-if="currentStep === 1"
          v-model="bookingData.customer"
        />

        <!-- Step 2: Service Selection -->
        <div v-else-if="currentStep === 2">
          <h3 class="text-lg font-semibold text-gray-900 mb-4">
            Select Service
          </h3>

          <div v-if="loadingServices" class="text-center py-8 text-gray-500">
            Loading services...
          </div>

          <div v-else-if="services.length === 0" class="text-center py-8">
            <p class="text-gray-600">No active services found.</p>
          </div>

          <div v-else class="space-y-3">
            <button
              v-for="service in services"
              :key="service.id"
              type="button"
              class="w-full p-4 border-2 rounded-lg text-left transition-all"
              :class="bookingData.service?.id === service.id
                ? 'border-primary-600 bg-primary-50'
                : 'border-gray-200 hover:border-primary-300'"
              @click="selectService(service)"
            >
              <div class="flex items-start justify-between">
                <div>
                  <div class="font-medium text-gray-900">
                    {{ service.name }}
                  </div>
                  <div class="text-sm text-gray-600 mt-1">
                    {{ service.duration }} minutes
                  </div>
                </div>
                <div class="text-right">
                  <div class="font-semibold text-gray-900">
                    &pound;{{ formatPrice(service.price) }}
                  </div>
                </div>
              </div>
            </button>
          </div>
        </div>

        <!-- Step 3: Staff Selection -->
        <div v-else-if="currentStep === 3">
          <h3 class="text-lg font-semibold text-gray-900 mb-4">
            Select Staff Member
          </h3>

          <div v-if="loadingStaff" class="text-center py-8 text-gray-500">
            Loading staff...
          </div>

          <div v-else-if="staffList.length === 0" class="text-center py-8">
            <p class="text-gray-600">No staff available for this service.</p>
            <button
              @click="currentStep = 2"
              class="mt-4 text-sm text-primary-600 hover:text-primary-700 underline"
            >
              &larr; Select a different service
            </button>
          </div>

          <div v-else class="space-y-3">
            <!-- No Preference Option (only show if multiple staff) -->
            <button
              v-if="showNoPreference"
              type="button"
              class="w-full p-4 border-2 rounded-lg text-left transition-all"
              :class="bookingData.staff?.id === 0
                ? 'border-primary-600 bg-primary-50'
                : 'border-blue-200 bg-blue-50 hover:border-primary-400'"
              @click="selectNoPreference"
            >
              <div class="flex items-center">
                <span class="text-2xl mr-3">&#127919;</span>
                <div>
                  <div class="font-medium text-gray-900">
                    No Preference - First Available
                  </div>
                  <div class="text-sm text-gray-600 mt-1">
                    System will assign the first available staff member
                  </div>
                </div>
              </div>
            </button>

            <!-- Individual Staff Members -->
            <button
              v-for="staff in availableStaff"
              :key="staff.id"
              type="button"
              class="w-full p-4 border-2 rounded-lg text-left transition-all"
              :class="bookingData.staff?.id === staff.id
                ? 'border-primary-600 bg-primary-50'
                : 'border-gray-200 hover:border-primary-300'"
              @click="selectStaff(staff)"
            >
              <div class="font-medium text-gray-900">
                {{ staff.name }}
              </div>
            </button>
          </div>
        </div>

        <!-- Step 4: Date & Time Selection -->
        <div v-else-if="currentStep === 4">
          <h3 class="text-lg font-semibold text-gray-900 mb-4">
            Select Date &amp; Time
          </h3>

          <!-- Date Picker -->
          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Select Date
            </label>
            <input
              v-model="selectedDate"
              type="date"
              :min="minDate"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              @change="loadTimeslots"
            />
          </div>

          <!-- Time Slots -->
          <div v-if="selectedDate">
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Available Times
            </label>

            <div v-if="loadingSlots" class="text-center py-8 text-gray-500">
              Loading available times...
            </div>

            <div v-else-if="timeslotsError || !timeslots || totalSlots === 0 || (timeslots && !timeslots.morning && !timeslots.afternoon && !timeslots.evening)" class="text-center py-8">
              <div class="text-5xl mb-3">&#128197;</div>
              <p class="text-gray-900 font-medium mb-2">No Available Times</p>
              <p class="text-sm text-gray-600 mb-1">
                {{ timeslotsError || 'No staff members are available on this date.' }}
              </p>
              <p class="text-xs text-gray-500 mt-2">
                This may be because:
              </p>
              <ul class="text-xs text-gray-500 mt-2 space-y-1 text-left inline-block">
                <li>&bull; Staff are not working on this day</li>
                <li>&bull; All time slots are already booked</li>
                <li>&bull; No staff can provide this service</li>
              </ul>
              <button
                @click="selectedDate = ''; bookingData.date = null; bookingData.time = null"
                class="mt-4 block mx-auto text-sm text-primary-600 hover:text-primary-700 underline"
              >
                &larr; Try a different date
              </button>
            </div>

            <div v-else class="space-y-4">
              <!-- Morning Slots -->
              <div v-if="timeslots.morning && timeslots.morning.length > 0">
                <h4 class="text-sm font-medium text-gray-700 mb-2">Morning</h4>
                <div class="grid grid-cols-3 sm:grid-cols-4 gap-2">
                  <button
                    v-for="slot in timeslots.morning"
                    :key="slot"
                    type="button"
                    class="px-3 py-2 text-sm border-2 rounded-lg transition-all"
                    :class="bookingData.time === slot
                      ? 'border-primary-600 bg-primary-600 text-white font-medium'
                      : 'border-gray-200 hover:border-primary-300 text-gray-700'"
                    @click="selectTime(slot)"
                  >
                    {{ formatSlotTime(slot) }}
                  </button>
                </div>
              </div>

              <!-- Afternoon Slots -->
              <div v-if="timeslots.afternoon && timeslots.afternoon.length > 0">
                <h4 class="text-sm font-medium text-gray-700 mb-2">Afternoon</h4>
                <div class="grid grid-cols-3 sm:grid-cols-4 gap-2">
                  <button
                    v-for="slot in timeslots.afternoon"
                    :key="slot"
                    type="button"
                    class="px-3 py-2 text-sm border-2 rounded-lg transition-all"
                    :class="bookingData.time === slot
                      ? 'border-primary-600 bg-primary-600 text-white font-medium'
                      : 'border-gray-200 hover:border-primary-300 text-gray-700'"
                    @click="selectTime(slot)"
                  >
                    {{ formatSlotTime(slot) }}
                  </button>
                </div>
              </div>

              <!-- Evening Slots -->
              <div v-if="timeslots.evening && timeslots.evening.length > 0">
                <h4 class="text-sm font-medium text-gray-700 mb-2">Evening</h4>
                <div class="grid grid-cols-3 sm:grid-cols-4 gap-2">
                  <button
                    v-for="slot in timeslots.evening"
                    :key="slot"
                    type="button"
                    class="px-3 py-2 text-sm border-2 rounded-lg transition-all"
                    :class="bookingData.time === slot
                      ? 'border-primary-600 bg-primary-600 text-white font-medium'
                      : 'border-gray-200 hover:border-primary-300 text-gray-700'"
                    @click="selectTime(slot)"
                  >
                    {{ formatSlotTime(slot) }}
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Step 5: Payment & Confirmation -->
        <div v-else-if="currentStep === 5">
          <h3 class="text-lg font-semibold text-gray-900 mb-4">
            Payment &amp; Confirmation
          </h3>

          <!-- Booking Summary -->
          <div class="bg-gray-50 rounded-lg p-4 mb-6">
            <h4 class="text-sm font-medium text-gray-900 mb-3">Booking Summary</h4>
            <div class="space-y-2 text-sm">
              <div class="flex justify-between">
                <span class="text-gray-600">Customer:</span>
                <span class="font-medium">{{ getCustomerName() }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-600">Service:</span>
                <span class="font-medium">{{ bookingData.service?.name }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-600">Staff:</span>
                <span class="font-medium">
                  {{ bookingData.staff?.id === 0 ? 'First Available Staff' : bookingData.staff?.name }}
                </span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-600">Date &amp; Time:</span>
                <span class="font-medium">{{ formatBookingDate() }} at {{ formatSlotTime(bookingData.time) }}</span>
              </div>
              <div class="flex justify-between pt-2 border-t border-gray-300">
                <span class="text-gray-900 font-medium">Total:</span>
                <span class="text-lg font-semibold text-gray-900">&pound;{{ formatPrice(bookingData.service?.price) }}</span>
              </div>
            </div>
          </div>

          <!-- Payment Method -->
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Payment Method *
            </label>
            <select
              v-model="bookingData.payment_method"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              required
            >
              <option value="">Select payment method...</option>
              <option value="pay_on_arrival">Pay on Arrival</option>
              <option value="cash">Cash (Paid)</option>
              <option value="card_external">Card (Paid Outside System)</option>
              <option value="check">Check (Paid)</option>
              <option value="complimentary">Complimentary (Free)</option>
            </select>
          </div>

          <!-- Amount Paid (show only for paid methods) -->
          <div v-if="showAmountPaid" class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Amount Paid
            </label>
            <div class="relative">
              <span class="absolute left-3 top-2 text-gray-500">&pound;</span>
              <input
                v-model.number="bookingData.amount_paid"
                type="number"
                step="0.01"
                min="0"
                :max="bookingData.service?.price"
                class="w-full pl-8 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              />
            </div>
            <p class="text-xs text-gray-500 mt-1">
              Full amount: &pound;{{ formatPrice(bookingData.service?.price) }}
            </p>
          </div>

          <!-- Special Requests -->
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Special Requests / Notes
            </label>
            <textarea
              v-model="bookingData.special_requests"
              rows="3"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              placeholder="Any special requests or notes..."
            ></textarea>
          </div>

          <!-- Send Confirmation Email -->
          <div class="mb-4">
            <label class="flex items-center">
              <input
                v-model="bookingData.send_confirmation"
                type="checkbox"
                class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500"
              />
              <span class="ml-2 text-sm text-gray-700">
                Send confirmation email to customer
              </span>
            </label>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="px-4 sm:px-6 py-4 border-t border-gray-200 bg-gray-50 sticky bottom-0">
        <div class="flex flex-col-reverse sm:flex-row justify-between gap-3">
          <button
            v-if="currentStep > 1"
            @click="previousStep"
            :disabled="creating"
            class="w-full sm:w-auto px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            &larr; Back
          </button>
          <div v-else class="hidden sm:block"></div>

          <div class="flex flex-col-reverse sm:flex-row gap-2">
            <button
              @click="$emit('close')"
              :disabled="creating"
              class="w-full sm:w-auto px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Cancel
            </button>

            <button
              v-if="currentStep < 5"
              :disabled="!canProceed"
              @click="nextStep"
              class="w-full sm:w-auto px-4 py-2.5 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Next: {{ nextStepLabel }} &rarr;
            </button>

            <button
              v-else
              :disabled="!canCreate || creating"
              @click="createBooking"
              class="w-full sm:w-auto px-4 py-2.5 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {{ creating ? 'Creating...' : 'Create Booking' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { useApi } from '../composables/useApi'
import { useToast } from '../composables/useToast'
import CustomerSelector from './CustomerSelector.vue'

const api = useApi()
const { error: toastError } = useToast()

const emit = defineEmits(['close', 'created'])

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

// State.
const currentStep = ref(1)
const creating = ref(false)
const bookingData = ref({
  customer: null,
  service: null,
  staff: null,
  date: null,
  time: null,
  payment_method: '',
  amount_paid: 0,
  special_requests: '',
  send_confirmation: true
})

// Services & Staff.
const services = ref([])
const loadingServices = ref(false)
const staffList = ref([])
const loadingStaff = ref(false)
const showNoPreference = ref(true)

// Date & Time.
const selectedDate = ref('')
const timeslots = ref(null)
const loadingSlots = ref(false)
const timeslotsError = ref('')

// Computed: step titles and labels.
const stepTitle = computed(() => {
  const titles = {
    1: 'Customer',
    2: 'Service',
    3: 'Staff',
    4: 'Date & Time',
    5: 'Payment'
  }
  return titles[currentStep.value] || ''
})

const nextStepLabel = computed(() => {
  const labels = {
    1: 'Select Service',
    2: 'Select Staff',
    3: 'Select Date & Time',
    4: 'Payment'
  }
  return labels[currentStep.value] || 'Next'
})

// Computed: step validation.
const canProceed = computed(() => {
  switch (currentStep.value) {
    case 1: return !!bookingData.value.customer
    case 2: return !!bookingData.value.service
    case 3: return !!bookingData.value.staff
    case 4: return !!bookingData.value.date && !!bookingData.value.time
    default: return false
  }
})

const canCreate = computed(() => {
  return bookingData.value.customer &&
         bookingData.value.service &&
         bookingData.value.staff &&
         bookingData.value.date &&
         bookingData.value.time &&
         bookingData.value.payment_method
})

const availableStaff = computed(() => {
  // TODO: Filter by service when staff-services relationship is available.
  return staffList.value
})

const showAmountPaid = computed(() => {
  const paidMethods = ['cash', 'card_external', 'check']
  return paidMethods.includes(bookingData.value.payment_method)
})

const totalSlots = computed(() => {
  if (!timeslots.value) return 0
  return (timeslots.value.morning?.length || 0) +
         (timeslots.value.afternoon?.length || 0) +
         (timeslots.value.evening?.length || 0)
})

const minDate = computed(() => {
  const today = new Date()
  return today.toISOString().split('T')[0]
})

// Watch for service change to set default amount.
watch(() => bookingData.value.service, (service) => {
  if (service) {
    bookingData.value.amount_paid = parseFloat(service.price || 0)
  }
})

// Watch for payment method change.
watch(() => bookingData.value.payment_method, (method) => {
  if (method === 'complimentary' || method === 'pay_on_arrival') {
    bookingData.value.amount_paid = 0
  } else if (showAmountPaid.value && bookingData.value.service) {
    bookingData.value.amount_paid = parseFloat(bookingData.value.service.price || 0)
  }
})

// Methods: data loading.
const loadServices = async () => {
  loadingServices.value = true
  try {
    const response = await api.get('/services/list')
    if (response.data.success) {
      services.value = response.data.services
    }
  } catch (err) {
    console.error('Error loading services:', err)
  } finally {
    loadingServices.value = false
  }
}

const loadStaffList = async () => {
  if (!bookingData.value.service) {
    console.error('No service selected')
    return
  }

  loadingStaff.value = true
  try {
    const response = await api.get(`staff/by-service/${bookingData.value.service.id}`)
    if (response.data.success) {
      staffList.value = response.data.staff

      // Show "No Preference" option if there are multiple staff.
      showNoPreference.value = staffList.value.length > 1

      if (staffList.value.length === 0) {
        console.warn('No staff available for this service')
      }
    }
  } catch (err) {
    console.error('Error loading staff:', err)
    staffList.value = []
    showNoPreference.value = false
  } finally {
    loadingStaff.value = false
  }
}

const loadTimeslots = async () => {
  if (!selectedDate.value || !bookingData.value.service || !bookingData.value.staff) {
    return
  }

  loadingSlots.value = true
  timeslotsError.value = ''

  try {
    const params = new URLSearchParams({
      date: selectedDate.value,
      service_id: bookingData.value.service.id,
      staff_id: bookingData.value.staff.id
    })

    const response = await api.get(`timeslots?${params.toString()}`)

    if (response.data.success === false) {
      timeslotsError.value = response.data.message || 'No slots available'
      timeslots.value = null
    } else if (response.data.available === false) {
      timeslotsError.value = response.data.message || 'Date not available'
      timeslots.value = null
    } else {
      timeslots.value = response.data.slots
    }
  } catch (err) {
    console.error('Error loading timeslots:', err)
    timeslotsError.value = err.message || 'Failed to load available times'
    timeslots.value = null
  } finally {
    loadingSlots.value = false
  }
}

// Methods: selections.
const selectService = (service) => {
  bookingData.value.service = service
}

const selectStaff = (staff) => {
  bookingData.value.staff = staff
}

const selectNoPreference = () => {
  bookingData.value.staff = {
    id: 0,
    name: 'First Available Staff'
  }
}

const selectTime = (time) => {
  bookingData.value.time = time
}

// Methods: navigation.
const nextStep = () => {
  if (currentStep.value === 1 && services.value.length === 0 && !loadingServices.value) {
    loadServices()
  }
  // Load staff when moving from Step 2 (Service) to Step 3 (Staff).
  if (currentStep.value === 2) {
    // Clear previous staff selection when service may have changed.
    bookingData.value.staff = null
    // Load staff filtered by selected service.
    loadStaffList()
  }
  if (currentStep.value < 5) {
    currentStep.value++
  }
}

const previousStep = () => {
  if (currentStep.value > 1) {
    currentStep.value--
  }
}

// Methods: formatting.
const getCustomerName = () => {
  if (!bookingData.value.customer) return ''
  const c = bookingData.value.customer
  return c.customer_first_name && c.customer_last_name
    ? `${c.customer_first_name} ${c.customer_last_name}`
    : c.customer_email || ''
}

const formatBookingDate = () => {
  if (!bookingData.value.date) return ''
  const date = new Date(bookingData.value.date + 'T00:00:00')
  return date.toLocaleDateString('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric'
  })
}

/**
 * Format a time slot string (H:i:s) to a shorter display format (HH:MM).
 */
const formatSlotTime = (time) => {
  if (!time) return ''
  // Slots come as "HH:mm:ss" — show "HH:mm".
  return time.substring(0, 5)
}

/**
 * Format a price value safely, returning '0.00' if the value is not a number.
 */
const formatPrice = (price) => {
  const num = parseFloat(price)
  return isNaN(num) ? '0.00' : num.toFixed(2)
}

// Methods: create booking.
const createBooking = async () => {
  if (!canCreate.value || creating.value) return

  creating.value = true

  try {
    const payload = {
      ...bookingData.value.customer,
      service_id: bookingData.value.service.id,
      staff_id: bookingData.value.staff.id,
      booking_date: bookingData.value.date,
      booking_time: bookingData.value.time,
      payment_method: bookingData.value.payment_method,
      amount_paid: bookingData.value.amount_paid,
      special_requests: bookingData.value.special_requests,
      send_confirmation: bookingData.value.send_confirmation
    }

    const response = await api.post('/bookings/create', payload)

    if (response.data.success) {
      emit('created', response.data.booking)
    } else {
      throw new Error(response.data.message || 'Failed to create booking')
    }
  } catch (err) {
    console.error('Error creating booking:', err)
    toastError(`Error creating booking: ${err.message}`)
  } finally {
    creating.value = false
  }
}

// Lifecycle: pre-load services and set up focus trap.
onMounted(async () => {
  previousActiveElement.value = document.activeElement
  document.addEventListener('keydown', trapFocus)

  await nextTick()
  const focusable = getFocusableElements()
  if (focusable.length > 0) {
    focusable[0].focus()
  }

  loadServices()
})

onUnmounted(() => {
  document.removeEventListener('keydown', trapFocus)
  if (previousActiveElement.value && previousActiveElement.value.focus) {
    previousActiveElement.value.focus()
  }
})

// Watch for step 4 to set today as default date.
watch(currentStep, (newStep) => {
  if (newStep === 4 && !selectedDate.value) {
    selectedDate.value = minDate.value
  }
})

// Watch for date selection to sync with bookingData and load slots.
watch(selectedDate, (newDate) => {
  bookingData.value.date = newDate
  bookingData.value.time = null // Clear selected time when date changes.
  if (newDate && bookingData.value.service && bookingData.value.staff) {
    loadTimeslots()
  }
})
</script>
