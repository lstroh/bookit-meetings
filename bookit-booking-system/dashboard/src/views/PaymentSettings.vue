<template>
  <div class="max-w-4xl mx-auto">
    <div v-if="loading" class="text-center py-12">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
      <p class="mt-2 text-sm text-gray-600">Loading payment settings...</p>
    </div>

    <div v-else class="space-y-6">
      <div>
        <h1 class="text-2xl font-semibold text-gray-900">Payment Gateways</h1>
        <p class="text-sm text-gray-500 mt-1">Configure how customers pay for their bookings.</p>
      </div>

      <form @submit.prevent="saveSettings" class="space-y-6">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
          <div class="px-4 sm:px-6 py-4 border-b border-gray-200 flex items-center justify-between gap-4">
            <div>
              <h2 class="text-lg font-semibold text-gray-900">💳 Stripe - Credit &amp; Debit Cards</h2>
            </div>
            <span
              class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium"
              :class="stripeConnected ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'"
            >
              {{ stripeConnected ? 'Connected' : 'Not configured' }}
            </span>
          </div>

          <div class="px-4 sm:px-6 py-6 space-y-5">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                <span class="inline-flex items-center gap-1">
                  Publishable key (pk_...)
                  <BookitTooltip
                    content="Your Stripe publishable key. Safe to expose in frontend code. Starts with pk_test_ or pk_live_."
                    position="top"
                  />
                </span>
              </label>
              <input
                v-model="settings.stripe_publishable_key"
                type="text"
                placeholder="pk_live_... or pk_test_..."
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              />
              <p class="text-xs text-gray-500 mt-1">
                Found in your Stripe dashboard under Developers -> API keys
              </p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                <span class="inline-flex items-center gap-1">
                  Secret key (sk_...)
                  <BookitTooltip
                    content="Your Stripe secret key. Never share this. Keep it server-side only. Starts with sk_test_ or sk_live_."
                    position="top"
                  />
                </span>
              </label>
              <div class="relative">
                <input
                  v-model="settings.stripe_secret_key"
                  :type="showStripeSecret ? 'text' : 'password'"
                  :placeholder="stripeSecretPlaceholder"
                  class="w-full px-3 py-2 pr-16 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                  @input="onStripeSecretInput"
                />
                <button
                  type="button"
                  class="absolute inset-y-0 right-0 px-3 text-sm text-gray-600 hover:text-gray-900"
                  @click="showStripeSecret = !showStripeSecret"
                >
                  {{ showStripeSecret ? '🙈' : '👁️' }}
                </button>
              </div>
              <p class="text-xs text-gray-500 mt-1">
                Keep this private. Never share or expose in frontend code.
              </p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Webhook signing secret (whsec_...)
              </label>
              <div class="relative">
                <input
                  v-model="settings.stripe_webhook_secret"
                  :type="showStripeWebhook ? 'text' : 'password'"
                  :placeholder="stripeWebhookPlaceholder"
                  class="w-full px-3 py-2 pr-16 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                  @input="onStripeWebhookInput"
                />
                <button
                  type="button"
                  class="absolute inset-y-0 right-0 px-3 text-sm text-gray-600 hover:text-gray-900"
                  @click="showStripeWebhook = !showStripeWebhook"
                >
                  {{ showStripeWebhook ? '🙈' : '👁️' }}
                </button>
              </div>
              <p class="text-xs text-gray-500 mt-1">
                Found in Stripe dashboard under Developers -> Webhooks. Used to verify webhook authenticity.
              </p>
            </div>

            <div class="flex items-start justify-between gap-4">
              <div>
                <p class="text-sm font-medium text-gray-900 inline-flex items-center gap-1">
                  Test mode
                  <BookitTooltip
                    content="When enabled, no real payments are processed. Use test card numbers to simulate transactions. Disable before going live."
                    position="top"
                  />
                </p>
                <p class="text-sm text-gray-500 mt-1">
                  Use Stripe test keys for development. Disable for live payments.
                </p>
              </div>
              <label class="flex items-center cursor-pointer">
                <input v-model="settings.stripe_test_mode" type="checkbox" class="sr-only peer" />
                <div class="relative w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-600"></div>
              </label>
            </div>

            <div class="bg-blue-50 border border-blue-200 rounded p-3">
              <p class="text-sm text-blue-800">
                ℹ️ Stripe Connect (OAuth) setup will be available once your site is live. For now, enter your API keys directly.
              </p>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
          <div class="px-4 sm:px-6 py-4 border-b border-gray-200 flex items-center justify-between gap-4">
            <div>
              <h2 class="text-lg font-semibold text-gray-900">🅿️ PayPal</h2>
            </div>
            <span
              class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium"
              :class="paypalConnected ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'"
            >
              {{ paypalConnected ? 'Connected' : 'Not configured' }}
            </span>
          </div>

          <div class="px-4 sm:px-6 py-6 space-y-5">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Client ID
              </label>
              <input
                v-model="settings.paypal_client_id"
                type="text"
                placeholder="AaBbCc..."
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              />
              <p class="text-xs text-gray-500 mt-1">
                Found in PayPal Developer dashboard under My Apps &amp; Credentials
              </p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Client secret
              </label>
              <div class="relative">
                <input
                  v-model="settings.paypal_client_secret"
                  :type="showPaypalSecret ? 'text' : 'password'"
                  :placeholder="paypalSecretPlaceholder"
                  class="w-full px-3 py-2 pr-16 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                  @input="onPaypalSecretInput"
                />
                <button
                  type="button"
                  class="absolute inset-y-0 right-0 px-3 text-sm text-gray-600 hover:text-gray-900"
                  @click="showPaypalSecret = !showPaypalSecret"
                >
                  {{ showPaypalSecret ? '🙈' : '👁️' }}
                </button>
              </div>
            </div>

            <div class="flex items-start justify-between gap-4">
              <div>
                <p class="text-sm font-medium text-gray-900">Sandbox mode</p>
                <p class="text-sm text-gray-500 mt-1">
                  Use PayPal sandbox for testing. Disable for live payments.
                </p>
              </div>
              <label class="flex items-center cursor-pointer">
                <input v-model="settings.paypal_sandbox_mode" type="checkbox" class="sr-only peer" />
                <div class="relative w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-600"></div>
              </label>
            </div>

            <div class="bg-blue-50 border border-blue-200 rounded p-3">
              <p class="text-sm text-blue-800">
                ℹ️ PayPal OAuth login flow will be available once your site is live.
              </p>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
          <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">💵 Pay on Arrival</h2>
          </div>

          <div class="px-4 sm:px-6 py-6">
            <div class="flex items-start justify-between gap-4">
              <div>
                <p class="text-sm font-medium text-gray-900">Enable Pay on Arrival</p>
                <p class="text-sm text-gray-500 mt-1">
                  Allow customers to book without paying online. They pay when they arrive for their appointment.
                </p>
              </div>
              <label class="flex items-center cursor-pointer">
                <input v-model="settings.pay_on_arrival_enabled" type="checkbox" class="sr-only peer" />
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
            {{ saving ? 'Saving...' : 'Save Payment Settings' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useApi } from '../composables/useApi'
import { useToast } from '../composables/useToast'

const SETTING_KEYS = 'stripe_publishable_key,stripe_secret_key,stripe_webhook_secret,stripe_test_mode,paypal_client_id,paypal_client_secret,paypal_sandbox_mode,pay_on_arrival_enabled'
const SAVED_SENTINEL = 'SAVED'

const api = useApi()
const { success: toastSuccess, error: toastError } = useToast()

const loading = ref(false)
const saving = ref(false)

const showStripeSecret = ref(false)
const showStripeWebhook = ref(false)
const showPaypalSecret = ref(false)

const stripeSecretSaved = ref(false)
const stripeWebhookSaved = ref(false)
const paypalSecretSaved = ref(false)

const settings = ref({
  stripe_publishable_key: '',
  stripe_secret_key: '',
  stripe_webhook_secret: '',
  stripe_test_mode: true,
  paypal_client_id: '',
  paypal_client_secret: '',
  paypal_sandbox_mode: true,
  pay_on_arrival_enabled: true
})

const stripeConnected = computed(() =>
  stripeSecretSaved.value || Boolean((settings.value.stripe_secret_key || '').trim())
)

const paypalConnected = computed(() =>
  Boolean((settings.value.paypal_client_id || '').trim())
)

const stripeSecretPlaceholder = computed(() =>
  stripeSecretSaved.value ? 'sk_••••••••••••' : 'sk_live_... or sk_test_...'
)

const stripeWebhookPlaceholder = computed(() =>
  stripeWebhookSaved.value ? 'whsec_••••••••••••' : 'whsec_...'
)

const paypalSecretPlaceholder = computed(() =>
  paypalSecretSaved.value ? '••••••••••••' : '••••••••'
)

const onStripeSecretInput = () => {
  stripeSecretSaved.value = false
}

const onStripeWebhookInput = () => {
  stripeWebhookSaved.value = false
}

const onPaypalSecretInput = () => {
  paypalSecretSaved.value = false
}

const loadSettings = async () => {
  loading.value = true

  try {
    const response = await api.get(`settings?keys=${SETTING_KEYS}`)
    const loadedSettings = response.data?.settings || {}

    settings.value.stripe_publishable_key = loadedSettings.stripe_publishable_key || ''
    settings.value.stripe_test_mode = Boolean(loadedSettings.stripe_test_mode ?? true)
    settings.value.paypal_client_id = loadedSettings.paypal_client_id || ''
    settings.value.paypal_sandbox_mode = Boolean(loadedSettings.paypal_sandbox_mode ?? true)
    settings.value.pay_on_arrival_enabled = Boolean(loadedSettings.pay_on_arrival_enabled ?? true)

    stripeSecretSaved.value = loadedSettings.stripe_secret_key === SAVED_SENTINEL
    stripeWebhookSaved.value = loadedSettings.stripe_webhook_secret === SAVED_SENTINEL
    paypalSecretSaved.value = loadedSettings.paypal_client_secret === SAVED_SENTINEL

    settings.value.stripe_secret_key = stripeSecretSaved.value ? '' : (loadedSettings.stripe_secret_key || '')
    settings.value.stripe_webhook_secret = stripeWebhookSaved.value ? '' : (loadedSettings.stripe_webhook_secret || '')
    settings.value.paypal_client_secret = paypalSecretSaved.value ? '' : (loadedSettings.paypal_client_secret || '')
  } catch {
    toastError('Failed to load payment settings.')
  } finally {
    loading.value = false
  }
}

const saveSettings = async () => {
  saving.value = true

  try {
    const payload = {
      stripe_publishable_key: settings.value.stripe_publishable_key || '',
      stripe_test_mode: Boolean(settings.value.stripe_test_mode),
      paypal_client_id: settings.value.paypal_client_id || '',
      paypal_sandbox_mode: Boolean(settings.value.paypal_sandbox_mode),
      pay_on_arrival_enabled: Boolean(settings.value.pay_on_arrival_enabled)
    }

    if ((settings.value.stripe_secret_key || '').trim() !== '' || !stripeSecretSaved.value) {
      payload.stripe_secret_key = settings.value.stripe_secret_key || ''
    }

    if ((settings.value.stripe_webhook_secret || '').trim() !== '' || !stripeWebhookSaved.value) {
      payload.stripe_webhook_secret = settings.value.stripe_webhook_secret || ''
    }

    if ((settings.value.paypal_client_secret || '').trim() !== '' || !paypalSecretSaved.value) {
      payload.paypal_client_secret = settings.value.paypal_client_secret || ''
    }

    const response = await api.post('settings', {
      settings: payload
    })

    if (response.data?.success) {
      toastSuccess('Payment settings saved.')
      await loadSettings()
    } else {
      toastError(response.data?.message || 'Failed to save payment settings.')
    }
  } catch (err) {
    toastError(err.message || 'Failed to save payment settings.')
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  loadSettings()
})
</script>
