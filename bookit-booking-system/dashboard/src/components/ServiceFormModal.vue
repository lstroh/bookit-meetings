<template>
  <div class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
      <!-- Header -->
      <div class="px-6 py-4 border-b border-gray-200 sticky top-0 bg-white z-10">
        <div class="flex items-center justify-between">
          <h2 class="text-xl font-semibold text-gray-900">
            {{ isEditing ? 'Edit Service' : 'Create New Service' }}
          </h2>
          <button
            @click="$emit('close')"
            class="text-gray-400 hover:text-gray-600 text-2xl leading-none"
          >
            &times;
          </button>
        </div>
      </div>

      <!-- Body -->
      <form @submit.prevent="saveService" class="px-6 py-6 space-y-4">
        <!-- Error Message -->
        <div v-if="errorMessage" class="bg-red-50 border border-red-200 rounded-lg p-3">
          <p class="text-sm text-red-800">{{ errorMessage }}</p>
        </div>

        <!-- Service Name -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Service Name *
          </label>
          <input
            v-model="formData.name"
            type="text"
            required
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            placeholder="e.g., Women's Haircut"
          />
        </div>

        <!-- Description -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Description
          </label>
          <textarea
            v-model="formData.description"
            rows="3"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            placeholder="Describe the service..."
          ></textarea>
        </div>

        <!-- Duration and Price -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Duration (minutes) *
            </label>
            <input
              v-model.number="formData.duration"
              type="number"
              min="1"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Price (&pound;) *
            </label>
            <input
              v-model.number="formData.price"
              type="number"
              step="0.01"
              min="0"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            />
          </div>
        </div>

        <!-- Deposit -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Deposit Amount
            </label>
            <input
              v-model.number="formData.deposit_amount"
              type="number"
              step="0.01"
              min="0"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              placeholder="0.00"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Deposit Type
            </label>
            <select
              v-model="formData.deposit_type"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            >
              <option value="fixed">Fixed Amount (&pound;)</option>
              <option value="percentage">Percentage (%)</option>
            </select>
          </div>
        </div>

        <!-- Buffer Times -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              <span class="inline-flex items-center gap-1">
                Buffer Before (minutes)
                <BookitTooltip
                  content="Adds padding before and/or after each appointment of this service. Useful for setup, cleanup, or travel time."
                  position="top"
                />
              </span>
            </label>
            <input
              v-model.number="formData.buffer_before"
              type="number"
              min="0"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            />
            <p class="text-xs text-gray-500 mt-1">Time to prepare before appointment</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Buffer After (minutes)
            </label>
            <input
              v-model.number="formData.buffer_after"
              type="number"
              min="0"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            />
            <p class="text-xs text-gray-500 mt-1">Time to clean up after appointment</p>
          </div>
        </div>

        <!-- Categories -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Categories
          </label>

          <!-- DEBUG: Remove after testing -->
          <div class="text-xs text-red-600 mb-2">
            Debug: {{ categories.length }} categories received
          </div>

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
                type="checkbox"
                :value="category.id"
                v-model="formData.category_ids"
                class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500"
              />
              <span class="ml-2 text-sm text-gray-700">{{ category.name }}</span>
            </label>
          </div>
          <p class="text-xs text-gray-500 mt-1">Select one or more categories</p>
        </div>

        <!-- Display Order and Active Status -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Display Order
            </label>
            <input
              v-model.number="formData.display_order"
              type="number"
              min="0"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            />
            <p class="text-xs text-gray-500 mt-1">Lower numbers appear first</p>
          </div>
          <div class="flex items-end pb-2">
            <label class="flex items-center">
              <input
                type="checkbox"
                v-model="formData.is_active"
                class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500"
              />
              <span class="ml-2 text-sm font-medium text-gray-700">
                Active (visible to customers)
              </span>
            </label>
          </div>
        </div>
      </form>

      <!-- Footer -->
      <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex justify-end gap-2 sticky bottom-0">
        <button
          @click="$emit('close')"
          :disabled="saving"
          class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50"
        >
          Cancel
        </button>
        <button
          @click="saveService"
          :disabled="saving || !isValid"
          class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {{ saving ? 'Saving...' : (isEditing ? 'Update Service' : 'Create Service') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useApi } from '../composables/useApi'

const api = useApi()

const props = defineProps({
  service: {
    type: Object,
    default: null
  },
  categories: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['close', 'saved'])

// State
const saving = ref(false)
const errorMessage = ref('')
const formData = ref({
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

// Computed
const isEditing = computed(() => !!props.service)

const isValid = computed(() => {
  return formData.value.name.trim() !== '' &&
         formData.value.duration > 0 &&
         formData.value.price >= 0
})

// Watch for service prop changes (when editing)
watch(() => props.service, (service) => {
  if (service) {
    formData.value = {
      name: service.name || '',
      description: service.description || '',
      duration: service.duration || 60,
      price: parseFloat(service.price) || 0,
      deposit_amount: service.deposit_amount ? parseFloat(service.deposit_amount) : null,
      deposit_type: service.deposit_type || 'fixed',
      buffer_before: service.buffer_before || 0,
      buffer_after: service.buffer_after || 0,
      category_ids: service.category_ids || (service.categories ? service.categories.map(c => c.id) : []),
      is_active: service.is_active ?? true,
      display_order: service.display_order || 0
    }
  }
}, { immediate: true })

// Methods
const saveService = async () => {
  if (!isValid.value || saving.value) return

  saving.value = true
  errorMessage.value = ''

  try {
    const payload = {
      name: formData.value.name,
      description: formData.value.description,
      duration: formData.value.duration,
      price: formData.value.price,
      deposit_amount: formData.value.deposit_amount,
      deposit_type: formData.value.deposit_type,
      buffer_before: formData.value.buffer_before,
      buffer_after: formData.value.buffer_after,
      category_ids: formData.value.category_ids,
      is_active: formData.value.is_active,
      display_order: formData.value.display_order
    }

    let response
    if (isEditing.value) {
      response = await api.put(`/services/${props.service.id}`, payload)
    } else {
      response = await api.post('/services/create', payload)
    }

    if (response.data.success) {
      emit('saved', response.data.service)
    } else {
      throw new Error(response.data.message || 'Failed to save service')
    }
  } catch (err) {
    console.error('Error saving service:', err)
    errorMessage.value = err.message || 'Failed to save service. Please try again.'
  } finally {
    saving.value = false
  }
}
</script>
