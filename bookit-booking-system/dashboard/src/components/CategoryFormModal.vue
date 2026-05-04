<template>
  <div class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl max-w-lg w-full max-h-[90vh] overflow-y-auto">
      <!-- Header -->
      <div class="px-6 py-4 border-b border-gray-200 sticky top-0 bg-white z-10">
        <div class="flex items-center justify-between">
          <h2 class="text-xl font-semibold text-gray-900">
            {{ isEditing ? 'Edit Category' : 'Create New Category' }}
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
      <form @submit.prevent="saveCategory" class="px-6 py-6 space-y-4">
        <!-- Error Message -->
        <div v-if="errorMessage" class="bg-red-50 border border-red-200 rounded-lg p-3">
          <p class="text-sm text-red-800">{{ errorMessage }}</p>
        </div>

        <!-- Category Name -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Category Name *
          </label>
          <input
            v-model="formData.name"
            type="text"
            required
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            placeholder="e.g., Hair Services"
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
            placeholder="Describe this category..."
          ></textarea>
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
                Active
              </span>
            </label>
          </div>
        </div>

        <!-- Service Count (when editing) -->
        <div v-if="isEditing && category?.service_count > 0" class="bg-blue-50 border border-blue-200 rounded p-3">
          <p class="text-sm text-blue-800">
            &#x2139;&#xFE0F; This category is currently used by <strong>{{ category.service_count }} service(s)</strong>.
          </p>
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
          @click="saveCategory"
          :disabled="saving || !isValid"
          class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {{ saving ? 'Saving...' : (isEditing ? 'Update Category' : 'Create Category') }}
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
  category: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['close', 'saved'])

// State
const saving = ref(false)
const errorMessage = ref('')
const formData = ref({
  name: '',
  description: '',
  is_active: true,
  display_order: 0
})

// Computed
const isEditing = computed(() => !!props.category)

const isValid = computed(() => {
  return formData.value.name.trim() !== ''
})

// Watch for category prop changes (when editing)
watch(() => props.category, (category) => {
  if (category) {
    formData.value = {
      name: category.name || '',
      description: category.description || '',
      is_active: category.is_active ?? true,
      display_order: category.display_order || 0
    }
  } else {
    formData.value = {
      name: '',
      description: '',
      is_active: true,
      display_order: 0
    }
  }
}, { immediate: true })

// Methods
const saveCategory = async () => {
  if (!isValid.value || saving.value) return

  saving.value = true
  errorMessage.value = ''

  try {
    const payload = {
      name: formData.value.name,
      description: formData.value.description,
      is_active: formData.value.is_active,
      display_order: formData.value.display_order
    }

    let response
    if (isEditing.value) {
      response = await api.put(`categories/${props.category.id}`, payload)
    } else {
      response = await api.post('categories/create', payload)
    }

    if (response.data.success) {
      emit('saved', response.data.category)
    } else {
      throw new Error(response.data.message || 'Failed to save category')
    }
  } catch (err) {
    console.error('Error saving category:', err)

    // Show duplicate name error specifically
    if (err.response?.data?.code === 'duplicate_name') {
      errorMessage.value = 'A category with this name already exists. Please choose a different name.'
    } else {
      errorMessage.value = err.message || 'Failed to save category. Please try again.'
    }
  } finally {
    saving.value = false
  }
}
</script>
