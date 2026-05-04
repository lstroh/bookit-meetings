<template>
  <div class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4" @click.self="emit('close')">
    <div
      ref="modalPanelRef"
      tabindex="-1"
      class="bg-white rounded-lg shadow-xl max-w-lg w-full max-h-[90vh] overflow-y-auto focus:outline-none"
      role="dialog"
      aria-modal="true"
      :aria-labelledby="modalTitleId"
    >
      <!-- Header -->
      <div class="px-6 py-4 border-b border-gray-200 sticky top-0 bg-white z-10">
        <div class="flex items-center justify-between">
          <h2 :id="modalTitleId" class="text-xl font-semibold text-gray-900">
            {{ isEditing ? 'Edit Package Type' : 'Create Package Type' }}
          </h2>
          <button
            type="button"
            class="text-gray-400 hover:text-gray-600 text-2xl leading-none"
            aria-label="Close"
            @click="emit('close')"
          >
            &times;
          </button>
        </div>
      </div>

      <!-- Body -->
      <form class="px-6 py-6 space-y-4" @submit.prevent="savePackageType">
        <div v-if="validationError" class="bg-amber-50 border border-amber-200 rounded-lg p-3">
          <p class="text-sm text-amber-800">{{ validationError }}</p>
        </div>

        <div v-if="apiError" class="bg-red-50 border border-red-200 rounded-lg p-3">
          <p class="text-sm text-red-800">{{ apiError }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Package Name *</label>
          <input
            v-model="formData.name"
            type="text"
            maxlength="255"
            required
            placeholder="e.g. 5-Session Bundle"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
          <textarea
            v-model="formData.description"
            rows="3"
            maxlength="500"
            placeholder="Optional package description..."
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
          ></textarea>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1 inline-flex items-center gap-1">
            Number of Sessions *
            <BookitTooltip
              content="How many sessions the customer receives when they purchase this package"
              position="top"
            />
          </label>
          <input
            v-model.number="formData.sessions_count"
            type="number"
            min="1"
            step="1"
            required
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Pricing *</label>
          <div class="space-y-2">
            <label class="flex items-center gap-2 text-sm text-gray-700">
              <input v-model="formData.price_mode" type="radio" value="fixed" class="text-primary-600 focus:ring-primary-500" />
              Fixed price
            </label>
            <label class="flex items-center gap-2 text-sm text-gray-700">
              <input v-model="formData.price_mode" type="radio" value="discount" class="text-primary-600 focus:ring-primary-500" />
              Discount rate
            </label>
          </div>
        </div>

        <div v-if="formData.price_mode === 'fixed'">
          <label class="block text-sm font-medium text-gray-700 mb-1 inline-flex items-center gap-1">
            Total Package Price (£) *
            <BookitTooltip
              content="The total price the customer pays for the whole package"
              position="top"
            />
          </label>
          <input
            v-model.number="formData.fixed_price"
            type="number"
            min="0.01"
            step="0.01"
            required
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
          />
        </div>

        <div v-if="formData.price_mode === 'discount'">
          <label class="block text-sm font-medium text-gray-700 mb-1 inline-flex items-center gap-1">
            Discount (%) *
            <BookitTooltip
              content="Discount applied to the sum of individual session prices. The lowest applicable service price is used for the calculation."
              position="top"
            />
          </label>
          <input
            v-model.number="formData.discount_percentage"
            type="number"
            min="0.01"
            max="100"
            step="0.01"
            required
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
          />
        </div>

        <div>
          <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
            <input
              v-model="formData.expiry_enabled"
              type="checkbox"
              class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500"
            />
            Package Expires
          </label>
        </div>

        <div v-if="formData.expiry_enabled">
          <label class="block text-sm font-medium text-gray-700 mb-1 inline-flex items-center gap-1">
            Expires after (days) *
            <BookitTooltip
              content="Days from purchase date before unused sessions expire"
              position="top"
            />
          </label>
          <input
            v-model.number="formData.expiry_days"
            type="number"
            min="1"
            step="1"
            required
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1 inline-flex items-center gap-1">
            Applicable Services
            <BookitTooltip
              content="Which services can be booked using this package"
              position="top"
            />
          </label>
          <p class="text-xs text-gray-500 mb-2">Leave empty to allow this package for all services</p>

          <div v-if="services.length === 0" class="text-sm text-gray-500 border border-gray-200 rounded-lg px-3 py-2">
            No active services available. Leaving this empty allows all services.
          </div>
          <div v-else class="max-h-40 overflow-y-auto border border-gray-200 rounded-lg p-3 space-y-2">
            <label
              v-for="service in services"
              :key="service.id"
              class="flex items-center gap-2 text-sm text-gray-700"
            >
              <input
                v-model="formData.applicable_service_ids"
                type="checkbox"
                :value="Number(service.id)"
                class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500"
              />
              <span>{{ service.name }}</span>
            </label>
          </div>
        </div>
      </form>

      <!-- Footer -->
      <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex justify-end gap-2 sticky bottom-0">
        <button
          type="button"
          :disabled="saving"
          class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50"
          @click="emit('close')"
        >
          Cancel
        </button>
        <button
          type="button"
          :disabled="saving"
          class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 disabled:opacity-50"
          @click="savePackageType"
        >
          {{ saving ? 'Saving...' : (isEditing ? 'Update Package Type' : 'Create Package Type') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useApi } from '../composables/useApi'
import BookitTooltip from './BookitTooltip.vue'

const api = useApi()

const props = defineProps({
  packageType: {
    type: Object,
    default: null
  },
  services: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['close', 'saved'])

const saving = ref(false)
const apiError = ref('')
const validationError = ref('')
const modalPanelRef = ref(null)
const previousActiveElement = ref(null)

const formData = ref({
  name: '',
  description: '',
  sessions_count: 1,
  price_mode: 'fixed',
  fixed_price: null,
  discount_percentage: null,
  expiry_enabled: false,
  expiry_days: null,
  applicable_service_ids: []
})

const modalTitleId = `package-type-modal-title-${Math.random().toString(36).slice(2, 11)}`
const isEditing = computed(() => !!props.packageType)

function populateForm(packageType) {
  if (!packageType) {
    formData.value = {
      name: '',
      description: '',
      sessions_count: 1,
      price_mode: 'fixed',
      fixed_price: null,
      discount_percentage: null,
      expiry_enabled: false,
      expiry_days: null,
      applicable_service_ids: []
    }
    return
  }

  formData.value = {
    name: packageType.name || '',
    description: packageType.description || '',
    sessions_count: Number(packageType.sessions_count || 1),
    price_mode: packageType.price_mode || 'fixed',
    fixed_price: packageType.fixed_price !== null && packageType.fixed_price !== undefined ? Number(packageType.fixed_price) : null,
    discount_percentage: packageType.discount_percentage !== null && packageType.discount_percentage !== undefined
      ? Number(packageType.discount_percentage)
      : null,
    expiry_enabled: Boolean(packageType.expiry_enabled),
    expiry_days: packageType.expiry_days !== null && packageType.expiry_days !== undefined ? Number(packageType.expiry_days) : null,
    applicable_service_ids: Array.isArray(packageType.applicable_service_ids)
      ? packageType.applicable_service_ids.map((id) => Number(id))
      : []
  }
}

watch(
  () => props.packageType,
  (packageType) => {
    populateForm(packageType)
    apiError.value = ''
    validationError.value = ''
  },
  { immediate: true }
)

function validateForm() {
  const name = String(formData.value.name || '').trim()
  if (!name) return 'Package Name is required.'
  if (!Number.isInteger(Number(formData.value.sessions_count)) || Number(formData.value.sessions_count) < 1) {
    return 'Number of Sessions must be a whole number greater than or equal to 1.'
  }

  if (!['fixed', 'discount'].includes(formData.value.price_mode)) {
    return 'Please select a pricing mode.'
  }

  if (formData.value.price_mode === 'fixed') {
    const fixedPrice = Number(formData.value.fixed_price)
    if (!Number.isFinite(fixedPrice) || fixedPrice <= 0) {
      return 'Total Package Price must be greater than 0.'
    }
  }

  if (formData.value.price_mode === 'discount') {
    const discount = Number(formData.value.discount_percentage)
    if (!Number.isFinite(discount) || discount < 0.01 || discount > 100) {
      return 'Discount must be between 0.01 and 100.'
    }
  }

  if (formData.value.expiry_enabled) {
    if (!Number.isInteger(Number(formData.value.expiry_days)) || Number(formData.value.expiry_days) < 1) {
      return 'Expires after (days) must be a whole number greater than or equal to 1.'
    }
  }

  return ''
}

function getPayload() {
  const payload = {
    name: String(formData.value.name || '').trim(),
    sessions_count: Number(formData.value.sessions_count),
    price_mode: formData.value.price_mode,
    expiry_enabled: Boolean(formData.value.expiry_enabled),
    applicable_service_ids: formData.value.applicable_service_ids.length
      ? formData.value.applicable_service_ids.map((id) => Number(id))
      : null
  }

  const description = String(formData.value.description || '').trim()
  if (description) {
    payload.description = description
  }

  if (formData.value.price_mode === 'fixed') {
    payload.fixed_price = parseFloat(formData.value.fixed_price)
  } else {
    payload.discount_percentage = parseFloat(formData.value.discount_percentage)
  }

  if (formData.value.expiry_enabled && formData.value.expiry_days) {
    payload.expiry_days = parseInt(formData.value.expiry_days, 10)
  }

  return payload
}

async function savePackageType() {
  if (saving.value) return

  validationError.value = validateForm()
  apiError.value = ''

  if (validationError.value) {
    return
  }

  saving.value = true

  try {
    const payload = getPayload()
    const response = isEditing.value
      ? await api.patch(`/package-types/${props.packageType.id}`, payload)
      : await api.post('/package-types', payload)

    emit('saved', response.data)
  } catch (err) {
    apiError.value = err.message || 'Failed to save package type.'
  } finally {
    saving.value = false
  }
}

function getFocusableElements() {
  if (!modalPanelRef.value) return []
  return Array.from(
    modalPanelRef.value.querySelectorAll(
      'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
    )
  )
}

function handleKeydown(event) {
  if (event.key === 'Escape') {
    emit('close')
    return
  }

  if (event.key !== 'Tab') return

  const focusable = getFocusableElements()
  if (focusable.length === 0) return

  const first = focusable[0]
  const last = focusable[focusable.length - 1]

  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault()
    last.focus()
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault()
    first.focus()
  }
}

onMounted(async () => {
  previousActiveElement.value = document.activeElement
  document.addEventListener('keydown', handleKeydown)

  await nextTick()
  const focusable = getFocusableElements()
  if (focusable.length > 0) {
    focusable[0].focus()
  } else {
    modalPanelRef.value?.focus()
  }
})

onBeforeUnmount(() => {
  document.removeEventListener('keydown', handleKeydown)
  nextTick(() => previousActiveElement.value?.focus())
})
</script>
