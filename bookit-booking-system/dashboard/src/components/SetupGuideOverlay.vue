<template>
  <Transition name="setup-overlay">
    <div class="fixed inset-0 z-[120] bg-black/60 flex items-stretch sm:items-center justify-center sm:p-4">
      <div
        ref="panelRef"
        class="setup-guide-panel w-full h-full sm:h-auto sm:max-h-[90vh] sm:max-w-2xl bg-white sm:rounded-2xl shadow-2xl overflow-y-auto focus:outline-none"
        role="dialog"
        aria-modal="true"
        aria-labelledby="setup-guide-title"
        tabindex="-1"
      >
        <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
          <div class="flex items-center justify-between gap-3">
            <button
              type="button"
              class="text-sm text-gray-600 hover:text-gray-900"
              @click="showDismissConfirm = true"
            >
              &times; Dismiss
            </button>
            <p class="text-xs sm:text-sm text-gray-500">Step {{ activeStep }} of 4</p>
          </div>

          <div
            v-if="showDismissConfirm"
            class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3"
          >
            <p class="text-sm text-amber-900">
              Are you sure? You can reopen this guide from the sidebar anytime.
            </p>
            <div class="mt-3 flex items-center justify-end gap-2">
              <button
                type="button"
                class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg text-gray-700 hover:bg-white"
                @click="showDismissConfirm = false"
              >
                Cancel
              </button>
              <button
                type="button"
                class="px-3 py-1.5 text-sm rounded-lg bg-red-600 text-white hover:bg-red-700"
                :disabled="isDismissing"
                @click="confirmDismiss"
              >
                {{ isDismissing ? 'Dismissing...' : 'Yes, dismiss' }}
              </button>
            </div>
          </div>
        </div>

        <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
          <div class="flex items-start justify-between gap-2">
            <div v-for="(step, index) in stepMeta" :key="step.number" class="flex-1 min-w-0">
              <button
                type="button"
                class="w-full flex items-center gap-2"
                :class="stepClickable(step.number) ? 'cursor-pointer' : 'cursor-default'"
                :disabled="!stepClickable(step.number)"
                @click="jumpToStep(step.number)"
              >
                <span
                  class="flex-shrink-0 w-7 h-7 rounded-full border flex items-center justify-center text-xs font-semibold"
                  :class="stepCircleClass(step.number)"
                >
                  {{ isStepComplete(step.number) ? '✓' : step.number }}
                </span>
                <span class="text-xs sm:text-sm text-gray-600 truncate">{{ step.label }}</span>
              </button>
              <div
                v-if="index < stepMeta.length - 1"
                class="mt-2 h-0.5 w-full"
                :class="connectorClass(step.number)"
              ></div>
            </div>
          </div>
        </div>

        <div class="px-4 sm:px-6 py-5 space-y-4">
          <template v-if="activeStep === 1">
            <h2 id="setup-guide-title" class="text-xl font-semibold text-gray-900">Add your first service</h2>
            <p class="text-sm text-gray-600">
              What do you offer? Add one service to get started. You can add more later.
            </p>

            <div v-if="stepOneLoading" class="py-8 text-center text-sm text-gray-500">
              Loading services...
            </div>

            <template v-else-if="hasActiveServices">
              <div class="rounded-lg border border-green-200 bg-green-50 p-3">
                <p class="text-sm text-green-800">
                  ✓ You already have {{ activeServices.length }} service(s) set up.
                </p>
              </div>

              <div class="rounded-lg border border-gray-200 p-3">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Existing services</p>
                <ul class="space-y-1">
                  <li
                    v-for="service in visibleServiceNames"
                    :key="service.id"
                    class="text-sm text-gray-700"
                  >
                    {{ service.name }}
                  </li>
                </ul>
                <p v-if="extraServicesCount > 0" class="mt-1 text-xs text-gray-500">
                  + {{ extraServicesCount }} more
                </p>
              </div>
            </template>

            <template v-else>
              <div v-if="stepOneError" class="bg-red-50 border border-red-200 rounded-lg p-3">
                <p class="text-sm text-red-800">{{ stepOneError }}</p>
              </div>

              <form class="space-y-4" @submit.prevent>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Service Name *</label>
                  <input
                    v-model="serviceForm.name"
                    type="text"
                    required
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                    placeholder="e.g., Women's Haircut"
                  />
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                  <textarea
                    v-model="serviceForm.description"
                    rows="3"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                    placeholder="Describe the service..."
                  ></textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Duration (minutes) *</label>
                    <input
                      v-model.number="serviceForm.duration"
                      type="number"
                      min="1"
                      required
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                    />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Price (&pound;) *</label>
                    <input
                      v-model.number="serviceForm.price"
                      type="number"
                      step="0.01"
                      min="0"
                      required
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                    />
                  </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Deposit Amount</label>
                    <input
                      v-model.number="serviceForm.deposit_amount"
                      type="number"
                      step="0.01"
                      min="0"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                      placeholder="0.00"
                    />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Deposit Type</label>
                    <select
                      v-model="serviceForm.deposit_type"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                    >
                      <option value="fixed">Fixed Amount (&pound;)</option>
                      <option value="percentage">Percentage (%)</option>
                    </select>
                  </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Buffer Before (minutes)</label>
                    <input
                      v-model.number="serviceForm.buffer_before"
                      type="number"
                      min="0"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                    />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Buffer After (minutes)</label>
                    <input
                      v-model.number="serviceForm.buffer_after"
                      type="number"
                      min="0"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                    />
                  </div>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Categories</label>
                  <div v-if="categories.length === 0" class="text-sm text-gray-500 mb-2">
                    No categories available
                  </div>
                  <div v-else class="space-y-2 max-h-32 overflow-y-auto border border-gray-200 rounded-lg p-3">
                    <label
                      v-for="category in categories"
                      :key="category.id"
                      class="flex items-center"
                    >
                      <input
                        v-model="serviceForm.category_ids"
                        type="checkbox"
                        :value="category.id"
                        class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500"
                      />
                      <span class="ml-2 text-sm text-gray-700">{{ category.name }}</span>
                    </label>
                  </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Display Order</label>
                    <input
                      v-model.number="serviceForm.display_order"
                      type="number"
                      min="0"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                    />
                  </div>
                  <div class="flex items-end pb-1">
                    <label class="flex items-center">
                      <input
                        v-model="serviceForm.is_active"
                        type="checkbox"
                        class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500"
                      />
                      <span class="ml-2 text-sm font-medium text-gray-700">Active (visible to customers)</span>
                    </label>
                  </div>
                </div>
              </form>
            </template>
          </template>

          <template v-else-if="activeStep === 2">
            <h2 id="setup-guide-title" class="text-xl font-semibold text-gray-900">When are you available?</h2>
            <p class="text-sm text-gray-600">
              Set your working hours so customers can book you. These apply to your account as the business owner.
            </p>

            <div v-if="stepTwoError" class="bg-red-50 border border-red-200 rounded-lg p-3">
              <p class="text-sm text-red-800">{{ stepTwoError }}</p>
            </div>

            <div v-if="stepTwoLoading" class="py-8 text-center text-sm text-gray-500">
              Loading availability...
            </div>

            <div v-else class="rounded-lg border border-gray-200 divide-y divide-gray-200">
              <div
                v-for="day in days"
                :key="day.number"
                class="p-3 sm:p-4"
                :class="{ 'bg-gray-50': !schedule[day.number].is_working }"
              >
                <div class="flex items-center justify-between">
                  <label class="flex items-center cursor-pointer">
                    <input
                      v-model="schedule[day.number].is_working"
                      type="checkbox"
                      class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500"
                    />
                    <span
                      class="ml-2 text-sm font-medium"
                      :class="schedule[day.number].is_working ? 'text-gray-900' : 'text-gray-400'"
                    >
                      {{ day.name }}
                    </span>
                  </label>
                  <span v-if="!schedule[day.number].is_working" class="text-xs text-gray-500 bg-gray-200 px-2 py-0.5 rounded">
                    Not working
                  </span>
                </div>

                <div v-if="schedule[day.number].is_working" class="mt-3 grid grid-cols-2 gap-3">
                  <div>
                    <label class="block text-xs text-gray-500 mb-1">From</label>
                    <select
                      v-model="schedule[day.number].start_time"
                      class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                    >
                      <option v-for="time in timeOptions" :key="`start-${day.number}-${time}`" :value="time">
                        {{ time }}
                      </option>
                    </select>
                  </div>
                  <div>
                    <label class="block text-xs text-gray-500 mb-1">To</label>
                    <select
                      v-model="schedule[day.number].end_time"
                      class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                    >
                      <option v-for="time in timeOptions" :key="`end-${day.number}-${time}`" :value="time">
                        {{ time }}
                      </option>
                    </select>
                  </div>
                </div>
              </div>
            </div>
          </template>

          <template v-else-if="activeStep === 3">
            <h2 id="setup-guide-title" class="text-xl font-semibold text-gray-900">How do you want to get paid?</h2>
            <p class="text-sm text-gray-600">
              Connect a payment method so you can take deposits and full payments online.
            </p>

            <div v-if="stepThreeLoading" class="py-8 text-center text-sm text-gray-500">
              Checking payment status...
            </div>

            <div v-else class="space-y-3">
              <div class="rounded-lg border border-gray-200 p-4">
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <p class="text-sm font-semibold text-gray-900">💳 Stripe — Credit &amp; Debit Cards</p>
                    <span class="inline-flex mt-1 px-2 py-0.5 text-[10px] font-semibold tracking-wide rounded-full bg-primary-100 text-primary-700">
                      RECOMMENDED
                    </span>
                  </div>
                  <span
                    class="text-xs font-medium px-2 py-1 rounded-full"
                    :class="paymentStatus.stripeConnected ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'"
                  >
                    {{ paymentStatus.stripeConnected ? '✓ Connected' : 'Not connected' }}
                  </span>
                </div>
                <a
                  v-if="!paymentStatus.stripeConnected"
                  href="/settings"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="inline-block mt-2 text-sm text-primary-600 hover:text-primary-700"
                >
                  Set up in Settings →
                </a>
              </div>

              <div class="rounded-lg border border-gray-200 p-4">
                <div class="flex items-start justify-between gap-3">
                  <p class="text-sm font-semibold text-gray-900">🅿️ PayPal</p>
                  <span
                    class="text-xs font-medium px-2 py-1 rounded-full"
                    :class="paymentStatus.paypalConnected ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'"
                  >
                    {{ paymentStatus.paypalConnected ? '✓ Connected' : 'Not connected' }}
                  </span>
                </div>
                <a
                  v-if="!paymentStatus.paypalConnected"
                  href="/settings"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="inline-block mt-2 text-sm text-primary-600 hover:text-primary-700"
                >
                  Set up in Settings →
                </a>
              </div>

              <div class="rounded-lg border border-gray-200 p-4">
                <div class="flex items-start justify-between gap-3">
                  <p class="text-sm font-semibold text-gray-900">💵 Pay on Arrival</p>
                  <span class="text-xs font-medium px-2 py-1 rounded-full bg-gray-100 text-gray-700">
                    ✓ Always available
                  </span>
                </div>
              </div>

              <p class="text-xs text-gray-500">
                You can configure payment gateways in Settings → Payments at any time. At least one method is required to accept bookings.
              </p>
            </div>
          </template>

          <template v-else>
            <h2 id="setup-guide-title" class="text-xl font-semibold text-gray-900">🎉 You're ready to take bookings!</h2>
            <p class="text-sm text-gray-600">Here's a summary of your setup:</p>

            <div class="rounded-lg border border-gray-200 p-4 space-y-2">
              <p class="text-sm" :class="isStepComplete(1) ? 'text-green-700' : 'text-gray-500'">
                {{ isStepComplete(1) ? '✓' : '✗' }} Service added
              </p>
              <p class="text-sm" :class="isStepComplete(2) ? 'text-green-700' : 'text-gray-500'">
                {{ isStepComplete(2) ? '✓' : '✗' }} Availability set
              </p>
              <p class="text-sm" :class="isStepComplete(3) ? 'text-green-700' : 'text-gray-500'">
                {{ isStepComplete(3) ? '✓' : '✗' }} Payment configured
              </p>
            </div>

            <div class="rounded-lg border border-gray-200 p-4">
              <p class="text-sm font-medium text-gray-700">Your booking page:</p>
              <p class="mt-1 text-sm break-all text-gray-900">{{ bookingPageUrl }}</p>
              <div class="mt-3 flex flex-wrap items-center gap-3">
                <button
                  type="button"
                  class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50"
                  @click="copyBookingUrl"
                >
                  {{ copiedLink ? 'Copied!' : 'Copy link' }}
                </button>
                <a
                  :href="bookingPageUrl"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="text-sm text-primary-600 hover:text-primary-700"
                >
                  View booking page
                </a>
              </div>
            </div>

            <ul class="list-disc ml-5 text-sm text-gray-700 space-y-1">
              <li>Add more services in Services</li>
              <li>Add staff members in Staff</li>
              <li>Test a booking yourself</li>
            </ul>
          </template>
        </div>

        <div class="px-4 sm:px-6 py-4 border-t border-gray-200 bg-gray-50 flex items-center justify-between gap-3">
          <button
            v-if="activeStep < 4"
            type="button"
            class="text-sm text-gray-500 hover:text-gray-800"
            :disabled="isBusy"
            @click="skipCurrentStep"
          >
            Skip this step →
          </button>
          <span v-else></span>

          <button
            type="button"
            class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 disabled:opacity-50"
            :disabled="isBusy"
            @click="runPrimaryAction"
          >
            {{ primaryButtonLabel }}
          </button>
        </div>
      </div>
    </div>
  </Transition>
</template>

<script setup>
import { computed, inject, nextTick, onMounted, onUnmounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useApi } from '../composables/useApi'
import { useSetupGuide } from '../composables/useSetupGuide'
import { useToast } from '../composables/useToast'

const emit = defineEmits(['close'])

const api = useApi()
const router = useRouter()
const { success: toastSuccess, error: toastError } = useToast()

const injectedGuideState = inject('setupGuideState', null)
const {
  currentStep,
  stepsCompleted,
  markComplete,
  dismiss,
  updateStep
} = injectedGuideState || useSetupGuide()

const panelRef = ref(null)
const previousActiveElement = ref(null)
const showDismissConfirm = ref(false)
const isDismissing = ref(false)
const isBusy = ref(false)
const activeStep = ref(1)
const copiedLink = ref(false)
const adminStaffId = Number(window.BOOKIT_DASHBOARD?.staff?.id || 0)

const stepMeta = [
  { number: 1, label: 'Add Service' },
  { number: 2, label: 'Availability' },
  { number: 3, label: 'Payments' },
  { number: 4, label: 'Go Live' }
]

const days = [
  { number: 1, name: 'Monday' },
  { number: 2, name: 'Tuesday' },
  { number: 3, name: 'Wednesday' },
  { number: 4, name: 'Thursday' },
  { number: 5, name: 'Friday' },
  { number: 6, name: 'Saturday' },
  { number: 7, name: 'Sunday' }
]

const createStepTwoDefaults = () => {
  const values = {}
  for (let day = 1; day <= 7; day++) {
    values[day] = {
      day_of_week: day,
      is_working: day <= 5,
      start_time: '09:00',
      end_time: '17:00'
    }
  }
  return values
}

const schedule = ref(createStepTwoDefaults())

const stepOneLoading = ref(false)
const stepOneError = ref('')
const activeServices = ref([])
const categories = ref([])
const serviceForm = reactive({
  name: '',
  description: '',
  duration: 60,
  price: 0,
  deposit_amount: null,
  deposit_type: 'fixed',
  buffer_before: 0,
  buffer_after: 0,
  category_ids: [],
  is_active: true,
  display_order: 0
})

const stepTwoLoading = ref(false)
const stepTwoError = ref('')

const stepThreeLoading = ref(false)
const paymentStatus = ref({
  stripeConnected: false,
  paypalConnected: false
})

const visibleServiceNames = computed(() => activeServices.value.slice(0, 3))
const extraServicesCount = computed(() => Math.max(0, activeServices.value.length - 3))
const hasActiveServices = computed(() => activeServices.value.length > 0)
const bookingPageUrl = computed(() => `${window.location.origin}/book`)

const timeOptions = computed(() => {
  const options = []
  for (let hour = 0; hour < 24; hour++) {
    for (let minutes = 0; minutes < 60; minutes += 30) {
      const h = String(hour).padStart(2, '0')
      const m = String(minutes).padStart(2, '0')
      options.push(`${h}:${m}`)
    }
  }
  return options
})

const primaryButtonLabel = computed(() => {
  if (activeStep.value === 1) {
    return hasActiveServices.value ? 'Continue →' : 'Save & Continue →'
  }
  if (activeStep.value === 2) return 'Save & Continue →'
  if (activeStep.value === 3) return 'Continue →'
  return 'Go to Dashboard ✓'
})

const getFocusableElements = () => {
  if (!panelRef.value) return []
  return Array.from(
    panelRef.value.querySelectorAll(
      'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
    )
  )
}

const trapFocus = (event) => {
  if (!panelRef.value) return

  if (event.key === 'Escape') {
    if (showDismissConfirm.value) {
      showDismissConfirm.value = false
    } else {
      showDismissConfirm.value = true
    }
    event.preventDefault()
    return
  }

  if (event.key !== 'Tab') return

  const focusable = getFocusableElements()
  if (focusable.length === 0) return

  const first = focusable[0]
  const last = focusable[focusable.length - 1]

  if (event.shiftKey && document.activeElement === first) {
    last.focus()
    event.preventDefault()
  } else if (!event.shiftKey && document.activeElement === last) {
    first.focus()
    event.preventDefault()
  }
}

const isStepComplete = (stepNumber) => stepsCompleted.value.includes(stepNumber)

const stepClickable = (stepNumber) => stepNumber === activeStep.value || isStepComplete(stepNumber)

const connectorClass = (stepNumber) => (
  isStepComplete(stepNumber) ? 'bg-green-400' : 'bg-gray-200'
)

const stepCircleClass = (stepNumber) => {
  if (isStepComplete(stepNumber)) return 'bg-green-500 border-green-500 text-white'
  if (activeStep.value === stepNumber) return 'bg-primary-600 border-primary-600 text-white'
  return 'bg-white border-gray-300 text-gray-500'
}

const formatTimeForInput = (timeValue) => {
  if (!timeValue) return '09:00'
  return String(timeValue).substring(0, 5)
}

const toBool = (value) => (
  value === true ||
  value === 1 ||
  value === '1' ||
  value === 'true' ||
  value === 'yes' ||
  value === 'on'
)

const loadStepOneData = async () => {
  stepOneLoading.value = true
  stepOneError.value = ''

  try {
    const [servicesResponse, categoriesResponse] = await Promise.all([
      api.get('/services/list?status=active&page=1&per_page=50'),
      api.get('categories/list')
    ])

    if (servicesResponse.data?.success) {
      activeServices.value = Array.isArray(servicesResponse.data.services)
        ? servicesResponse.data.services
        : []
    } else {
      activeServices.value = []
    }

    if (categoriesResponse.data?.success) {
      categories.value = Array.isArray(categoriesResponse.data.categories)
        ? categoriesResponse.data.categories
        : []
    } else {
      categories.value = []
    }
  } catch (error) {
    stepOneError.value = error.message || 'Unable to load services right now.'
  } finally {
    stepOneLoading.value = false
  }
}

const loadStepTwoHours = async () => {
  if (!adminStaffId) return

  stepTwoLoading.value = true
  stepTwoError.value = ''
  schedule.value = createStepTwoDefaults()

  try {
    const response = await api.get(`staff/${adminStaffId}/hours`)
    const apiSchedule = response.data?.schedule || {}

    for (let day = 1; day <= 7; day++) {
      const dayData = apiSchedule[day]
      if (!dayData || !dayData.is_working || !Array.isArray(dayData.records) || dayData.records.length === 0) {
        continue
      }

      const firstRecord = dayData.records[0]
      schedule.value[day] = {
        day_of_week: day,
        is_working: true,
        start_time: formatTimeForInput(firstRecord.start_time),
        end_time: formatTimeForInput(firstRecord.end_time)
      }
    }
  } catch (error) {
    stepTwoError.value = error.message || 'Unable to load existing availability.'
  } finally {
    stepTwoLoading.value = false
  }
}

const loadPaymentStatus = async () => {
  stepThreeLoading.value = true
  try {
    const response = await api.get('settings?keys=stripe_connected,stripe_account_id,paypal_connected,paypal_client_id')
    const settings = response.data?.settings || response.data || {}

    paymentStatus.value = {
      stripeConnected: toBool(settings.stripe_connected) || Boolean(settings.stripe_account_id),
      paypalConnected: toBool(settings.paypal_connected) || Boolean(settings.paypal_client_id)
    }
  } catch {
    paymentStatus.value = {
      stripeConnected: false,
      paypalConnected: false
    }
  } finally {
    stepThreeLoading.value = false
  }
}

const jumpToStep = (stepNumber) => {
  if (!stepClickable(stepNumber)) return
  activeStep.value = stepNumber
  currentStep.value = stepNumber
  showDismissConfirm.value = false
}

const persistDoneAndAdvance = async (doneStep, nextStep) => {
  await updateStep(activeStep.value, doneStep)
  activeStep.value = nextStep
  currentStep.value = nextStep
  showDismissConfirm.value = false
}

const persistAdvanceWithoutDone = async (nextStep) => {
  activeStep.value = nextStep
  currentStep.value = nextStep
  showDismissConfirm.value = false
}

const validateStepOneForm = () => (
  serviceForm.name.trim() !== '' &&
  Number(serviceForm.duration) > 0 &&
  Number(serviceForm.price) >= 0
)

const saveStepOneService = async () => {
  if (!validateStepOneForm()) {
    stepOneError.value = 'Please complete the required service fields.'
    return
  }

  isBusy.value = true
  stepOneError.value = ''

  try {
    const payload = {
      name: serviceForm.name,
      description: serviceForm.description,
      duration: serviceForm.duration,
      price: serviceForm.price,
      deposit_amount: serviceForm.deposit_amount,
      deposit_type: serviceForm.deposit_type,
      buffer_before: serviceForm.buffer_before,
      buffer_after: serviceForm.buffer_after,
      category_ids: serviceForm.category_ids,
      is_active: serviceForm.is_active,
      display_order: serviceForm.display_order
    }

    const response = await api.post('/services/create', payload)
    if (!response.data?.success) {
      throw new Error(response.data?.message || 'Failed to create service.')
    }

    toastSuccess('Service created!')
    await updateStep(activeStep.value, 1)
    await router.push('/services')
    await new Promise(resolve => setTimeout(resolve, 1000))
    activeStep.value = 2
    currentStep.value = 2
    showDismissConfirm.value = false
  } catch (error) {
    stepOneError.value = error.message || 'Failed to create service.'
  } finally {
    isBusy.value = false
  }
}

const validateStepTwo = () => {
  for (let day = 1; day <= 7; day++) {
    const dayData = schedule.value[day]
    if (!dayData.is_working) continue
    if (!dayData.start_time || !dayData.end_time || dayData.start_time >= dayData.end_time) {
      return false
    }
  }
  return true
}

const saveStepTwo = async () => {
  if (!adminStaffId) {
    stepTwoError.value = 'Could not determine your staff profile.'
    return
  }

  if (!validateStepTwo()) {
    stepTwoError.value = 'Each working day must have an end time after the start time.'
    return
  }

  isBusy.value = true
  stepTwoError.value = ''

  try {
    const scheduleData = []
    for (let day = 1; day <= 7; day++) {
      const dayData = schedule.value[day]
      scheduleData.push({
        day_of_week: day,
        is_working: dayData.is_working,
        start_time: dayData.start_time,
        end_time: dayData.end_time,
        break_start: null,
        break_end: null,
        valid_from: null,
        valid_until: null
      })
    }

    const response = await api.post(`staff/${adminStaffId}/hours`, {
      schedule: scheduleData
    })

    if (!response.data?.success) {
      throw new Error(response.data?.message || 'Failed to save availability.')
    }

    await persistDoneAndAdvance(2, 3)
    await router.push(`/staff/${adminStaffId}/hours`)
  } catch (error) {
    stepTwoError.value = error.message || 'Failed to save availability.'
  } finally {
    isBusy.value = false
  }
}

const runPrimaryAction = async () => {
  if (isBusy.value) return

  if (activeStep.value === 1) {
    if (hasActiveServices.value) {
      isBusy.value = true
      try {
        await persistDoneAndAdvance(1, 2)
        await router.push('/services')
      } finally {
        isBusy.value = false
      }
      return
    }
    await saveStepOneService()
    return
  }

  if (activeStep.value === 2) {
    await saveStepTwo()
    return
  }

  if (activeStep.value === 3) {
    isBusy.value = true
    try {
      await persistDoneAndAdvance(3, 4)
    } finally {
      isBusy.value = false
    }
    return
  }

  isBusy.value = true
  try {
    await markComplete()
    emit('close')
  } finally {
    isBusy.value = false
  }
}

const skipCurrentStep = async () => {
  if (isBusy.value) return
  isBusy.value = true

  try {
    if (activeStep.value === 1) {
      await persistDoneAndAdvance(1, 2)
      return
    }
    if (activeStep.value === 2) {
      await persistAdvanceWithoutDone(3)
      return
    }
    if (activeStep.value === 3) {
      await persistAdvanceWithoutDone(4)
    }
  } finally {
    isBusy.value = false
  }
}

const confirmDismiss = async () => {
  isDismissing.value = true
  try {
    await dismiss()
    emit('close')
  } finally {
    isDismissing.value = false
  }
}

const copyBookingUrl = async () => {
  try {
    await navigator.clipboard.writeText(bookingPageUrl.value)
    copiedLink.value = true
    setTimeout(() => {
      copiedLink.value = false
    }, 2000)
  } catch {
    toastError('Could not copy link. Please copy it manually.')
  }
}

watch(activeStep, (stepNumber) => {
  if (stepNumber === 1 && activeServices.value.length === 0 && !stepOneLoading.value) {
    void loadStepOneData()
  }
  if (stepNumber === 2 && !stepTwoLoading.value) {
    void loadStepTwoHours()
  }
  if (stepNumber === 3 && !stepThreeLoading.value) {
    void loadPaymentStatus()
  }
})

onMounted(async () => {
  previousActiveElement.value = document.activeElement
  document.addEventListener('keydown', trapFocus)
  document.body.style.overflow = 'hidden'

  activeStep.value = Number(currentStep.value) >= 1 && Number(currentStep.value) <= 4
    ? Number(currentStep.value)
    : 1

  await nextTick()
  panelRef.value?.focus()

  await Promise.all([
    loadStepOneData(),
    loadStepTwoHours(),
    loadPaymentStatus()
  ])
})

onUnmounted(() => {
  document.removeEventListener('keydown', trapFocus)
  document.body.style.overflow = ''
  if (previousActiveElement.value && typeof previousActiveElement.value.focus === 'function') {
    previousActiveElement.value.focus()
  }
})
</script>

<style scoped>
.setup-overlay-enter-active,
.setup-overlay-leave-active {
  transition: opacity 0.2s ease;
}

.setup-overlay-enter-active .setup-guide-panel,
.setup-overlay-leave-active .setup-guide-panel {
  transition: transform 0.2s ease, opacity 0.2s ease;
}

.setup-overlay-enter-from {
  opacity: 0;
}

.setup-overlay-enter-from .setup-guide-panel {
  transform: scale(0.98);
  opacity: 0;
}

.setup-overlay-leave-to {
  opacity: 0;
}

.setup-overlay-leave-to .setup-guide-panel {
  transform: scale(0.98);
  opacity: 0;
}
</style>
