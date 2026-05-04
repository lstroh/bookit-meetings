<template>
  <div class="max-w-6xl mx-auto">
    <div v-if="loading" class="text-center py-12">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
      <p class="mt-2 text-sm text-gray-600">Loading deposit settings...</p>
    </div>

    <div v-else class="space-y-6">
      <div>
        <h1 class="text-2xl font-semibold text-gray-900">Deposit Settings</h1>
        <p class="text-sm text-gray-500 mt-1">Control how deposits are collected at booking.</p>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <form @submit.prevent="saveSettings" class="space-y-6">
          <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
              <h2 class="text-lg font-semibold text-gray-900">Default Deposit Rules</h2>
            </div>

            <div class="px-4 sm:px-6 py-6 space-y-6">
              <div class="flex items-start justify-between gap-4">
                <div>
                  <p class="text-sm font-medium text-gray-900">Require a deposit for all new services</p>
                  <p class="text-sm text-gray-500 mt-1">
                    When ON, new services will have deposits enabled by default. You can still override this per service.
                  </p>
                </div>
                <label class="flex items-center cursor-pointer">
                  <input v-model="settings.deposit_required_default" type="checkbox" class="sr-only peer" />
                  <div class="relative w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-600"></div>
                </label>
              </div>

              <div v-if="settings.deposit_required_default" class="space-y-5">
                <div>
                  <p class="text-sm font-medium text-gray-700 mb-2 inline-flex items-center gap-1">
                    Default deposit type
                    <BookitTooltip
                      content="Whether deposits are calculated as a fixed amount or a percentage of the service price."
                      position="top"
                    />
                  </p>
                  <div class="space-y-2">
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                      <input v-model="settings.deposit_type_default" type="radio" value="percentage" class="text-primary-600" />
                      <span>Percentage of service price</span>
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                      <input v-model="settings.deposit_type_default" type="radio" value="fixed" class="text-primary-600" />
                      <span>Fixed amount (£)</span>
                    </label>
                  </div>
                </div>

                <div v-if="settings.deposit_type_default === 'percentage'">
                  <label class="block text-sm font-medium text-gray-700 mb-1">Default deposit amount</label>
                  <p class="text-xs text-gray-500 mb-2">Customers pay {{ safeDepositPercent }}% upfront</p>
                  <input
                    v-model.number="settings.deposit_amount_default"
                    type="range"
                    min="10"
                    max="100"
                    step="5"
                    class="w-full"
                  />
                </div>

                <div v-else>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Default deposit amount</label>
                  <input
                    v-model.number="settings.deposit_amount_default"
                    type="number"
                    min="0"
                    step="0.01"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                    placeholder="0.00"
                  />
                  <p class="text-xs text-gray-500 mt-1">Applied to all new services unless overridden</p>
                </div>
              </div>
            </div>
          </div>

          <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
              <h2 class="text-lg font-semibold text-gray-900">Deposit Rules</h2>
            </div>

            <div class="px-4 sm:px-6 py-6 space-y-6">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                  <span class="inline-flex items-center gap-1">
                    Minimum deposit percentage
                    <BookitTooltip
                      content="The lowest deposit amount that can be required, regardless of percentage calculation."
                      position="top"
                    />
                  </span>
                </label>
                <input
                  v-model.number="settings.deposit_minimum_percent"
                  type="number"
                  min="0"
                  max="100"
                  step="5"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                />
                <p class="text-xs text-gray-500 mt-1">
                  The lowest deposit percentage allowed across all services. Prevents staff from setting deposits too low.
                </p>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Maximum deposit percentage</label>
                <input
                  v-model.number="settings.deposit_maximum_percent"
                  type="number"
                  min="0"
                  max="100"
                  step="5"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                />
                <p class="text-xs text-gray-500 mt-1">
                  The highest deposit percentage allowed. Set to 100 to allow full upfront payment via deposit.
                </p>
              </div>

              <p v-if="minimumMaximumError" class="text-sm text-red-600">{{ minimumMaximumError }}</p>

              <div>
                <p class="text-sm font-medium text-gray-700 mb-2">Deposit applies to</p>
                <div class="space-y-2">
                  <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input v-model="settings.deposit_applies_to" type="radio" value="all" class="text-primary-600" />
                    <span>All bookings</span>
                  </label>
                  <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input v-model="settings.deposit_applies_to" type="radio" value="online_only" class="text-primary-600" />
                    <span>Online bookings only (not manual bookings created by admin)</span>
                  </label>
                </div>
              </div>

              <div class="flex items-start justify-between gap-4">
                <div>
                  <p class="text-sm font-medium text-gray-900">Collect deposit even when customer selects Pay on Arrival</p>
                  <p class="text-sm text-gray-500 mt-1">
                    When ON, customers must pay the deposit online regardless of payment method chosen for the balance.
                  </p>
                </div>
                <label class="flex items-center cursor-pointer">
                  <input v-model="settings.deposit_required_for_pay_on_arrival" type="checkbox" class="sr-only peer" />
                  <div class="relative w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-600"></div>
                </label>
              </div>
            </div>
          </div>

          <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
              <h2 class="text-lg font-semibold text-gray-900">Deposit Refund Behaviour</h2>
            </div>

            <div class="px-4 sm:px-6 py-6 space-y-6">
              <div class="bg-blue-50 border border-blue-200 rounded p-3">
                <p class="text-sm text-blue-800">
                  ℹ️ These rules apply specifically to deposits. Full cancellation and refund policy is configured in Settings -> Cancellation Policy.
                </p>
              </div>

              <div class="flex items-start justify-between gap-4">
                <div>
                  <p class="text-sm font-medium text-gray-900 inline-flex items-center gap-1">
                    Refund deposit for on-time cancellations
                    <BookitTooltip
                      content="Controls whether the deposit is returned if a customer cancels within the allowed cancellation window."
                      position="top"
                    />
                  </p>
                  <p class="text-sm text-gray-500 mt-1">
                    If the customer cancels within your free cancellation window, refund their deposit automatically.
                  </p>
                </div>
                <label class="flex items-center cursor-pointer">
                  <input v-model="settings.deposit_refundable_within_window" type="checkbox" class="sr-only peer" />
                  <div class="relative w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-600"></div>
                </label>
              </div>

              <div class="flex items-start justify-between gap-4">
                <div>
                  <p class="text-sm font-medium text-gray-900">Refund deposit for late cancellations</p>
                  <p class="text-sm text-gray-500 mt-1">
                    If the customer cancels outside the free window, refund their deposit. Leave OFF to keep the deposit as a late cancellation fee.
                  </p>
                </div>
                <label class="flex items-center cursor-pointer">
                  <input v-model="settings.deposit_refundable_outside_window" type="checkbox" class="sr-only peer" />
                  <div class="relative w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-600"></div>
                </label>
              </div>
            </div>
          </div>

          <div class="flex justify-end pt-2">
            <button
              type="submit"
              :disabled="saving"
              class="w-full sm:w-auto px-4 py-2.5 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 disabled:opacity-50"
            >
              {{ saving ? 'Saving...' : 'Save Deposit Settings' }}
            </button>
          </div>
        </form>

        <div class="space-y-4">
          <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 sm:p-6">
            <h3 class="text-lg font-semibold text-gray-900">Customer checkout preview</h3>
            <p class="text-sm text-gray-500 mt-1">What customers see at the payment step.</p>

            <div v-if="settings.deposit_required_default" class="mt-4 border border-gray-200 rounded-lg p-4 bg-gray-50 text-sm text-gray-800 space-y-4">
              <p class="font-semibold">Order Summary</p>
              <div class="flex items-center justify-between">
                <span>Women's Haircut</span>
                <span>£35.00</span>
              </div>
              <div class="space-y-1">
                <div class="flex items-center justify-between">
                  <span>Due today (deposit):</span>
                  <span>£{{ dueTodayDisplay }}</span>
                </div>
                <div class="flex items-center justify-between">
                  <span>Due on arrival:</span>
                  <span>£{{ dueOnArrivalDisplay }}</span>
                </div>
              </div>
              <button type="button" class="w-full sm:w-auto px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg opacity-90">
                Pay £{{ dueTodayDisplay }} now
              </button>
            </div>

            <div v-else class="mt-4 border border-gray-200 rounded-lg p-4 bg-gray-50 text-sm text-gray-700">
              Deposits are not required by default. Customers pay the full amount at checkout unless a service has a deposit configured.
            </div>

            <p class="text-xs text-gray-500 mt-4">
              Deposit amounts are set per service. This preview uses a £35.00 example service with your current default settings.
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useApi } from '../composables/useApi'
import { useToast } from '../composables/useToast'

const PREVIEW_PRICE = 35
const SETTING_KEYS = [
  'deposit_required_default',
  'deposit_type_default',
  'deposit_amount_default',
  'deposit_minimum_percent',
  'deposit_maximum_percent',
  'deposit_applies_to',
  'deposit_required_for_pay_on_arrival',
  'deposit_refundable_within_window',
  'deposit_refundable_outside_window'
].join(',')

const api = useApi()
const { success: toastSuccess, error: toastError } = useToast()

const loading = ref(false)
const saving = ref(false)

const settings = ref({
  deposit_required_default: false,
  deposit_type_default: 'percentage',
  deposit_amount_default: 50,
  deposit_minimum_percent: 10,
  deposit_maximum_percent: 100,
  deposit_applies_to: 'all',
  deposit_required_for_pay_on_arrival: false,
  deposit_refundable_within_window: true,
  deposit_refundable_outside_window: false
})

const safeDepositPercent = computed(() => {
  const value = Number(settings.value.deposit_amount_default)
  if (Number.isNaN(value)) {
    return 10
  }

  return Math.min(100, Math.max(10, Math.round(value / 5) * 5))
})

const minimumMaximumError = computed(() => {
  const minimum = Number(settings.value.deposit_minimum_percent)
  const maximum = Number(settings.value.deposit_maximum_percent)

  if (!Number.isNaN(minimum) && !Number.isNaN(maximum) && minimum > maximum) {
    return 'Minimum cannot exceed maximum.'
  }

  return ''
})

const dueToday = computed(() => {
  if (!settings.value.deposit_required_default) {
    return PREVIEW_PRICE
  }

  const amount = Number(settings.value.deposit_amount_default || 0)

  if (settings.value.deposit_type_default === 'fixed') {
    return Math.max(0, Math.min(PREVIEW_PRICE, amount))
  }

  const percent = Number.isNaN(amount) ? 0 : Math.max(0, Math.min(100, amount))
  return Math.max(0, Math.min(PREVIEW_PRICE, (PREVIEW_PRICE * percent) / 100))
})

const dueOnArrival = computed(() => Math.max(0, PREVIEW_PRICE - dueToday.value))
const dueTodayDisplay = computed(() => dueToday.value.toFixed(2))
const dueOnArrivalDisplay = computed(() => dueOnArrival.value.toFixed(2))

const normalizeLoadedSettings = (loadedSettings = {}) => {
  settings.value.deposit_required_default = Boolean(loadedSettings.deposit_required_default ?? false)
  settings.value.deposit_type_default = loadedSettings.deposit_type_default === 'fixed' ? 'fixed' : 'percentage'
  settings.value.deposit_amount_default = Number(loadedSettings.deposit_amount_default ?? 50)
  settings.value.deposit_minimum_percent = Number(loadedSettings.deposit_minimum_percent ?? 10)
  settings.value.deposit_maximum_percent = Number(loadedSettings.deposit_maximum_percent ?? 100)
  settings.value.deposit_applies_to = loadedSettings.deposit_applies_to === 'online_only' ? 'online_only' : 'all'
  settings.value.deposit_required_for_pay_on_arrival = Boolean(loadedSettings.deposit_required_for_pay_on_arrival ?? false)
  settings.value.deposit_refundable_within_window = Boolean(loadedSettings.deposit_refundable_within_window ?? true)
  settings.value.deposit_refundable_outside_window = Boolean(loadedSettings.deposit_refundable_outside_window ?? false)
}

const loadSettings = async () => {
  loading.value = true

  try {
    const response = await api.get(`settings?keys=${SETTING_KEYS}`)

    if (response.data.success && response.data.settings) {
      normalizeLoadedSettings(response.data.settings)
    }
  } catch {
    toastError('Failed to load deposit settings.')
  } finally {
    loading.value = false
  }
}

const saveSettings = async () => {
  if (minimumMaximumError.value) {
    toastError(minimumMaximumError.value)
    return
  }

  saving.value = true

  try {
    const payload = {
      deposit_required_default: Boolean(settings.value.deposit_required_default),
      deposit_type_default: settings.value.deposit_type_default === 'fixed' ? 'fixed' : 'percentage',
      deposit_amount_default: Number(settings.value.deposit_amount_default ?? 50),
      deposit_minimum_percent: Number(settings.value.deposit_minimum_percent ?? 10),
      deposit_maximum_percent: Number(settings.value.deposit_maximum_percent ?? 100),
      deposit_applies_to: settings.value.deposit_applies_to === 'online_only' ? 'online_only' : 'all',
      deposit_required_for_pay_on_arrival: Boolean(settings.value.deposit_required_for_pay_on_arrival),
      deposit_refundable_within_window: Boolean(settings.value.deposit_refundable_within_window),
      deposit_refundable_outside_window: Boolean(settings.value.deposit_refundable_outside_window)
    }

    const response = await api.post('settings', {
      settings: payload
    })

    if (response.data.success) {
      toastSuccess('Deposit settings saved.')
    } else {
      toastError(response.data.message || 'Failed to save deposit settings.')
    }
  } catch (err) {
    toastError(err.message || 'Failed to save deposit settings.')
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  loadSettings()
})
</script>
