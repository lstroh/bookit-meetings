<template>
  <div class="max-w-6xl mx-auto">
    <div v-if="loading" class="text-center py-12">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
      <p class="mt-2 text-sm text-gray-600">Loading cancellation policy...</p>
    </div>

    <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="space-y-6">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
          <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Cancellation Policy Settings</h2>
            <p class="text-sm text-gray-500 mt-1">
              Define refund, no-show, and rescheduling rules for customers.
            </p>
          </div>

          <form @submit.prevent="savePolicy" class="px-4 sm:px-6 py-6 space-y-6">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                <span class="inline-flex items-center gap-1">
                  Free cancellation period
                  <BookitTooltip
                    content="The number of hours before an appointment during which customers can cancel. Cancellations after this window follow the refund policy below."
                    position="top"
                  />
                </span>
              </label>
              <p class="text-xs text-gray-500 mb-2">
                How much notice must customers give to cancel for free?
              </p>
              <select
                v-model.number="settings.cancellation_window_hours"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              >
                <option v-for="option in windowOptions" :key="option.value" :value="option.value">
                  {{ option.label }}
                </option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                <span class="inline-flex items-center gap-1">
                  Refund if cancelled within the free period
                  <BookitTooltip
                    content="Cancellations made this many hours or more before the appointment receive a full refund."
                    position="top"
                  />
                </span>
              </label>
              <p class="text-xs text-gray-500 mb-2">
                Customer cancels with enough notice — what do they receive?
              </p>
              <div class="space-y-2">
                <label class="flex items-center gap-2 text-sm text-gray-700">
                  <input v-model="settings.within_window_refund_type" type="radio" value="full" class="text-primary-600" />
                  <span>Full refund (100%)</span>
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700">
                  <input v-model="settings.within_window_refund_type" type="radio" value="partial" class="text-primary-600" />
                  <span>Partial refund</span>
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700">
                  <input v-model="settings.within_window_refund_type" type="radio" value="none" class="text-primary-600" />
                  <span>No refund</span>
                </label>
              </div>
              <div v-if="settings.within_window_refund_type === 'partial'" class="mt-3">
                <label class="block text-xs text-gray-600 mb-1">
                  <span class="inline-flex items-center gap-1">
                    Customer receives {{ settings.within_window_refund_percent }}%
                    <BookitTooltip
                      content="The percentage of the booking total refunded for cancellations within the cancellation window but before the no-refund threshold."
                      position="top"
                    />
                  </span>
                </label>
                <input
                  v-model.number="settings.within_window_refund_percent"
                  type="range"
                  min="0"
                  max="100"
                  step="5"
                  class="w-full"
                />
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Refund if cancelled late (outside free period)
              </label>
              <p class="text-xs text-gray-500 mb-2">
                Customer cancels with less notice than your window allows.
              </p>
              <div class="space-y-2">
                <label class="flex items-center gap-2 text-sm text-gray-700">
                  <input v-model="settings.late_cancel_refund_type" type="radio" value="full" class="text-primary-600" />
                  <span>Full refund</span>
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700">
                  <input v-model="settings.late_cancel_refund_type" type="radio" value="partial" class="text-primary-600" />
                  <span>Partial refund</span>
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700">
                  <input v-model="settings.late_cancel_refund_type" type="radio" value="none" class="text-primary-600" />
                  <span>No refund</span>
                </label>
              </div>
              <div v-if="settings.late_cancel_refund_type === 'partial'" class="mt-3">
                <label class="block text-xs text-gray-600 mb-1">
                  Customer receives {{ settings.late_cancel_refund_percent }}%
                </label>
                <input
                  v-model.number="settings.late_cancel_refund_percent"
                  type="range"
                  min="0"
                  max="100"
                  step="5"
                  class="w-full"
                />
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                <span class="inline-flex items-center gap-1">
                  Refund if customer doesn't show up
                  <BookitTooltip
                    content="Applied when a customer does not attend their appointment without cancelling. Choose whether to retain the full amount or apply a partial charge."
                    position="top"
                  />
                </span>
              </label>
              <p class="text-xs text-gray-500 mb-2">
                Applied when a booking is marked as No Show.
              </p>
              <div class="space-y-2">
                <label class="flex items-center gap-2 text-sm text-gray-700">
                  <input v-model="settings.noshow_refund_type" type="radio" value="full" class="text-primary-600" />
                  <span>Full refund</span>
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700">
                  <input v-model="settings.noshow_refund_type" type="radio" value="partial" class="text-primary-600" />
                  <span>Partial refund</span>
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700">
                  <input v-model="settings.noshow_refund_type" type="radio" value="none" class="text-primary-600" />
                  <span>No refund — keep deposit</span>
                </label>
              </div>
              <div v-if="settings.noshow_refund_type === 'partial'" class="mt-3">
                <label class="block text-xs text-gray-600 mb-1">
                  Customer receives {{ settings.noshow_refund_percent }}%
                </label>
                <input
                  v-model.number="settings.noshow_refund_percent"
                  type="range"
                  min="0"
                  max="100"
                  step="5"
                  class="w-full"
                />
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Rescheduling policy
              </label>
              <select
                v-model="settings.reschedule_policy"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              >
                <option value="free">Free rescheduling (unlimited)</option>
                <option value="limited">Free once, then no rescheduling</option>
                <option value="fee">Rescheduling fee applies</option>
                <option value="not_allowed">Rescheduling not permitted</option>
              </select>
              <div v-if="settings.reschedule_policy === 'fee'" class="mt-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                  Fee per reschedule (£)
                </label>
                <input
                  v-model="settings.reschedule_fee_amount"
                  type="number"
                  min="0"
                  step="0.01"
                  placeholder="0.00"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                />
              </div>
            </div>

            <div class="flex items-start justify-between gap-4">
              <div>
                <p class="text-sm font-medium text-gray-900">Automatic refund processing</p>
                <p class="text-sm text-gray-500 mt-1">
                  When enabled, eligible refunds are processed immediately via Stripe or PayPal. When disabled, refunds are flagged for manual processing.
                </p>
              </div>
              <label class="flex items-center cursor-pointer">
                <input v-model="settings.auto_refund_enabled" type="checkbox" class="sr-only peer" />
                <div class="relative w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-600"></div>
              </label>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Policy shown to customers
              </label>
              <p class="text-xs text-gray-500 mb-2">
                This text appears on the booking page and in confirmation emails so customers know your policy before they book.
              </p>
              <textarea
                v-model="settings.cancellation_policy_text"
                rows="4"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              ></textarea>
            </div>

            <div v-if="showStrictWarning" class="bg-amber-50 border border-amber-200 rounded p-3">
              <p class="text-sm text-amber-800">
                ⚠️ This is a strict no-refund policy. Consider allowing refunds for within-window cancellations to maintain good customer relations.
              </p>
            </div>

            <div class="flex justify-end pt-4 border-t border-gray-200">
              <button
                type="submit"
                :disabled="saving"
                class="w-full sm:w-auto px-4 py-2.5 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 disabled:opacity-50"
              >
                {{ saving ? 'Saving...' : 'Save Policy' }}
              </button>
            </div>
          </form>
        </div>
      </div>

      <div class="space-y-4">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 sm:p-6">
          <h3 class="text-lg font-semibold text-gray-900">Customer-facing policy preview</h3>
          <p class="text-sm text-gray-500 mt-1">This is what customers see before booking.</p>

          <div class="mt-4 border border-gray-200 rounded-lg p-4 bg-gray-50 text-sm text-gray-800 space-y-3">
            <p class="font-semibold">📋 Cancellation Policy</p>
            <p>✓ Free cancellation: Cancel up to {{ previewWindowLabel }} before your appointment for a {{ previewWithinRefundLabel }}.</p>
            <p>⚠ Late cancellation: Cancelling within {{ previewWindowLabel }} of your appointment: {{ previewLateRefundLabel }}.</p>
            <p>✗ No-show: If you don't arrive: {{ previewNoshowRefundLabel }}.</p>
            <p>🔄 Rescheduling: {{ previewRescheduleLabel }}</p>
          </div>
        </div>

        <div class="bg-gray-100 border border-gray-200 rounded-lg p-4 text-sm text-gray-700">
          {{ settings.cancellation_policy_text }}
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useApi } from '../composables/useApi'
import { useToast } from '../composables/useToast'

const api = useApi()
const { success: toastSuccess, error: toastError } = useToast()

const loading = ref(false)
const saving = ref(false)

const settings = ref({
  cancellation_window_hours: 24,
  within_window_refund_type: 'full',
  within_window_refund_percent: 100,
  late_cancel_refund_type: 'none',
  late_cancel_refund_percent: 0,
  noshow_refund_type: 'none',
  noshow_refund_percent: 0,
  reschedule_policy: 'free',
  reschedule_fee_amount: '0.00',
  cancellation_policy_text: 'Free cancellation up to 24 hours before your appointment. Late cancellations and no-shows may forfeit their deposit.',
  auto_refund_enabled: false
})

const windowOptions = [
  { value: 1, label: '1 hour before' },
  { value: 6, label: '6 hours before' },
  { value: 12, label: '12 hours before' },
  { value: 24, label: '24 hours before' },
  { value: 48, label: '48 hours before' },
  { value: 72, label: '72 hours before' },
  { value: 168, label: '1 week before' }
]

const SETTING_KEYS = 'cancellation_window_hours,within_window_refund_type,within_window_refund_percent,late_cancel_refund_type,late_cancel_refund_percent,noshow_refund_type,noshow_refund_percent,reschedule_policy,reschedule_fee_amount,cancellation_policy_text,auto_refund_enabled'

const previewWindowLabel = computed(() => {
  const option = windowOptions.find(item => item.value === Number(settings.value.cancellation_window_hours))
  if (option) {
    return option.label.replace(' before', '')
  }

  const hours = Number(settings.value.cancellation_window_hours) || 24
  return `${hours} hours`
})

const mapRefundLabel = (type, percent) => {
  if (type === 'full') {
    return 'full refund'
  }

  if (type === 'partial') {
    const safePercent = Number(percent)
    return `${Number.isNaN(safePercent) ? 0 : safePercent}% refund`
  }

  return 'no refund'
}

const previewWithinRefundLabel = computed(() =>
  mapRefundLabel(settings.value.within_window_refund_type, settings.value.within_window_refund_percent)
)
const previewLateRefundLabel = computed(() =>
  mapRefundLabel(settings.value.late_cancel_refund_type, settings.value.late_cancel_refund_percent)
)
const previewNoshowRefundLabel = computed(() =>
  mapRefundLabel(settings.value.noshow_refund_type, settings.value.noshow_refund_percent)
)

const previewRescheduleLabel = computed(() => {
  if (settings.value.reschedule_policy === 'free') {
    return 'Free, unlimited'
  }

  if (settings.value.reschedule_policy === 'limited') {
    return 'Free once, then not permitted'
  }

  if (settings.value.reschedule_policy === 'fee') {
    const numericFee = Number(settings.value.reschedule_fee_amount || 0)
    return `£${numericFee.toFixed(2)} fee per change`
  }

  return 'Not permitted'
})

const showStrictWarning = computed(() =>
  settings.value.within_window_refund_type === 'none' &&
  settings.value.late_cancel_refund_type === 'none' &&
  settings.value.noshow_refund_type === 'none'
)

const normalizeLoadedSettings = (loadedSettings) => {
  settings.value.cancellation_window_hours = Number(loadedSettings.cancellation_window_hours ?? 24)
  settings.value.within_window_refund_type = loadedSettings.within_window_refund_type || 'full'
  settings.value.within_window_refund_percent = Number(loadedSettings.within_window_refund_percent ?? 100)
  settings.value.late_cancel_refund_type = loadedSettings.late_cancel_refund_type || 'none'
  settings.value.late_cancel_refund_percent = Number(loadedSettings.late_cancel_refund_percent ?? 0)
  settings.value.noshow_refund_type = loadedSettings.noshow_refund_type || 'none'
  settings.value.noshow_refund_percent = Number(loadedSettings.noshow_refund_percent ?? 0)
  settings.value.reschedule_policy = loadedSettings.reschedule_policy || 'free'
  settings.value.reschedule_fee_amount = String(loadedSettings.reschedule_fee_amount ?? '0.00')
  settings.value.cancellation_policy_text = loadedSettings.cancellation_policy_text ||
    'Free cancellation up to 24 hours before your appointment. Late cancellations and no-shows may forfeit their deposit.'
  settings.value.auto_refund_enabled = Boolean(loadedSettings.auto_refund_enabled ?? false)
}

const loadSettings = async () => {
  loading.value = true

  try {
    const response = await api.get(`settings?keys=${SETTING_KEYS}`)

    if (response.data.success && response.data.settings) {
      normalizeLoadedSettings(response.data.settings)
    }
  } catch {
    toastError('Failed to load cancellation policy settings.')
  } finally {
    loading.value = false
  }
}

const savePolicy = async () => {
  saving.value = true

  try {
    const payload = {
      ...settings.value,
      cancellation_window_hours: Number(settings.value.cancellation_window_hours),
      within_window_refund_percent: Number(settings.value.within_window_refund_percent),
      late_cancel_refund_percent: Number(settings.value.late_cancel_refund_percent),
      noshow_refund_percent: Number(settings.value.noshow_refund_percent),
      reschedule_fee_amount: String(Number(settings.value.reschedule_fee_amount || 0).toFixed(2))
    }

    const response = await api.post('settings', {
      settings: payload
    })

    if (response.data.success) {
      settings.value.reschedule_fee_amount = payload.reschedule_fee_amount
      toastSuccess('Cancellation policy saved.')
    } else {
      toastError(response.data.message || 'Failed to save cancellation policy.')
    }
  } catch (err) {
    toastError(err.message || 'Failed to save cancellation policy.')
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  loadSettings()
})
</script>
