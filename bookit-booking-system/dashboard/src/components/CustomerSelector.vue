<template>
  <div>
    <h3 class="text-lg font-semibold text-gray-900 mb-4">
      Select or Create Customer
    </h3>

    <!-- Search Existing Customer -->
    <div class="mb-6">
      <label class="block text-sm font-medium text-gray-700 mb-2">
        Search Existing Customer
      </label>
      <div class="relative" ref="searchContainer">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Type name or email to search..."
          class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
          @input="onSearchInput"
          @focus="showResults = true"
        />
        <span class="absolute left-3 top-2.5 text-gray-400">&#x1F50D;</span>

        <!-- Search Results Dropdown -->
        <div
          v-if="showResults && searchQuery.length >= 2"
          class="absolute z-10 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-64 overflow-y-auto"
        >
          <!-- Loading -->
          <div v-if="searching" class="px-4 py-3 text-sm text-gray-500">
            Searching...
          </div>

          <!-- No Results -->
          <div v-else-if="searchResults.length === 0" class="px-4 py-3 text-sm text-gray-500">
            No customers found. Create a new customer below.
          </div>

          <!-- Results List -->
          <div v-else>
            <button
              v-for="customer in searchResults"
              :key="customer.id"
              type="button"
              class="w-full px-4 py-3 text-left hover:bg-gray-50 border-b border-gray-100 last:border-b-0 transition-colors"
              @click="selectCustomer(customer)"
            >
              <div class="font-medium text-gray-900">
                {{ customer.full_name }}
              </div>
              <div class="text-sm text-gray-500">
                {{ customer.email }}
              </div>
              <div v-if="customer.phone" class="text-sm text-gray-500">
                {{ customer.phone }}
              </div>
            </button>
          </div>
        </div>
      </div>

      <!-- Selected Customer Display -->
      <div
        v-if="selectedCustomer && !creatingNew"
        class="mt-3 p-4 bg-green-50 border border-green-200 rounded-lg"
      >
        <div class="flex items-start justify-between">
          <div>
            <div class="font-medium text-green-900">
              &#x2713; {{ selectedCustomerName }}
            </div>
            <div class="text-sm text-green-700">
              {{ selectedCustomerEmail }}
            </div>
            <div v-if="selectedCustomerPhone" class="text-sm text-green-700">
              {{ selectedCustomerPhone }}
            </div>
          </div>
          <button
            type="button"
            class="text-sm text-green-600 hover:text-green-800"
            @click="clearSelection"
          >
            Change
          </button>
        </div>
      </div>
    </div>

    <!-- Divider -->
    <div class="relative my-6">
      <div class="absolute inset-0 flex items-center">
        <div class="w-full border-t border-gray-300"></div>
      </div>
      <div class="relative flex justify-center text-sm">
        <span class="px-2 bg-white text-gray-500">OR</span>
      </div>
    </div>

    <!-- Create New Customer -->
    <div>
      <div class="flex items-center justify-between mb-3">
        <label class="block text-sm font-medium text-gray-700">
          Create New Customer
        </label>
        <button
          v-if="!creatingNew && !selectedCustomer"
          type="button"
          class="text-sm text-primary-600 hover:text-primary-700"
          @click="creatingNew = true"
        >
          + New Customer
        </button>
      </div>

      <!-- New Customer Form -->
      <div v-if="creatingNew" class="space-y-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
        <div class="grid grid-cols-2 gap-4">
          <!-- First Name -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              First Name *
            </label>
            <input
              v-model="newCustomer.first_name"
              type="text"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              :class="{ 'border-red-500': errors.first_name }"
            />
            <p v-if="errors.first_name" class="text-xs text-red-600 mt-1">
              {{ errors.first_name }}
            </p>
          </div>

          <!-- Last Name -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Last Name *
            </label>
            <input
              v-model="newCustomer.last_name"
              type="text"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              :class="{ 'border-red-500': errors.last_name }"
            />
            <p v-if="errors.last_name" class="text-xs text-red-600 mt-1">
              {{ errors.last_name }}
            </p>
          </div>
        </div>

        <!-- Email -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Email Address *
          </label>
          <input
            v-model="newCustomer.email"
            type="email"
            required
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            :class="{ 'border-red-500': errors.email }"
          />
          <p v-if="errors.email" class="text-xs text-red-600 mt-1">
            {{ errors.email }}
          </p>
        </div>

        <!-- Phone -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Phone Number
          </label>
          <input
            v-model="newCustomer.phone"
            type="tel"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
          />
        </div>

        <!-- Actions -->
        <div class="flex justify-end gap-2">
          <button
            type="button"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
            @click="cancelNewCustomer"
          >
            Cancel
          </button>
          <button
            type="button"
            class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700"
            @click="confirmNewCustomer"
          >
            Use This Customer
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useApi } from '../composables/useApi'

const api = useApi()

// Props & Emits
const props = defineProps({
  modelValue: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['update:modelValue'])

// Refs
const searchContainer = ref(null)

// State
const searchQuery = ref('')
const searchResults = ref([])
const searching = ref(false)
const showResults = ref(false)
const selectedCustomer = ref(null)
const creatingNew = ref(false)
const newCustomer = ref({
  first_name: '',
  last_name: '',
  email: '',
  phone: ''
})
const errors = ref({})

let searchTimeout = null

// Computed properties for displaying selected customer
const selectedCustomerName = computed(() => {
  if (!selectedCustomer.value) return ''
  const customer = selectedCustomer.value
  return customer.full_name || `${customer.first_name || ''} ${customer.last_name || ''}`.trim()
})

const selectedCustomerEmail = computed(() => {
  return selectedCustomer.value?.email || ''
})

const selectedCustomerPhone = computed(() => {
  return selectedCustomer.value?.phone || ''
})

// Lifecycle
const handleClickOutside = (event) => {
  if (searchContainer.value && !searchContainer.value.contains(event.target)) {
    showResults.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})

// Methods
const onSearchInput = () => {
  if (searchQuery.value.length < 2) {
    searchResults.value = []
    return
  }

  // Debounce search
  clearTimeout(searchTimeout)
  searching.value = true

  searchTimeout = setTimeout(async () => {
    try {
      const response = await api.get(`/customers/search?search=${encodeURIComponent(searchQuery.value)}`)

      if (response.data.success) {
        searchResults.value = response.data.customers
      }
    } catch (err) {
      console.error('Error searching customers:', err)
      searchResults.value = []
    } finally {
      searching.value = false
    }
  }, 300)
}

const selectCustomer = (customer) => {
  console.log('RAW customer from API:', customer)
  console.log('customer.id:', customer.id)
  console.log('customer.first_name:', customer.first_name)
  console.log('customer.last_name:', customer.last_name)
  console.log('customer.full_name:', customer.full_name)
  console.log('customer.email:', customer.email)
  
  selectedCustomer.value = customer
  showResults.value = false
  searchQuery.value = ''
  searchResults.value = []
  creatingNew.value = false
  
  console.log('selectedCustomer.value after assignment:', selectedCustomer.value)
  
  // Emit with customer_id for existing customer
  emit('update:modelValue', {
    customer_id: customer.id,
    customer_email: customer.email,
    customer_first_name: customer.first_name,
    customer_last_name: customer.last_name,
    customer_phone: customer.phone,
    is_new: false
  })
}

const clearSelection = () => {
  selectedCustomer.value = null
  searchQuery.value = ''
  searchResults.value = []
  emit('update:modelValue', null)
}

const cancelNewCustomer = () => {
  creatingNew.value = false
  newCustomer.value = {
    first_name: '',
    last_name: '',
    email: '',
    phone: ''
  }
  errors.value = {}
}

const confirmNewCustomer = () => {
  // Validate
  errors.value = {}
  
  if (!newCustomer.value.first_name) {
    errors.value.first_name = 'First name is required'
  }
  if (!newCustomer.value.last_name) {
    errors.value.last_name = 'Last name is required'
  }
  if (!newCustomer.value.email) {
    errors.value.email = 'Email is required'
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(newCustomer.value.email)) {
    errors.value.email = 'Please enter a valid email'
  }
  
  if (Object.keys(errors.value).length > 0) {
    return
  }
  
  // Set as selected
  selectedCustomer.value = {
    full_name: `${newCustomer.value.first_name} ${newCustomer.value.last_name}`,
    email: newCustomer.value.email,
    first_name: newCustomer.value.first_name,
    last_name: newCustomer.value.last_name,
    phone: newCustomer.value.phone
  }
  
  // CRITICAL: Hide the form
  creatingNew.value = false  // ADD THIS LINE
  
  // Emit with customer details (no customer_id since new)
  emit('update:modelValue', {
    customer_email: newCustomer.value.email,
    customer_first_name: newCustomer.value.first_name,
    customer_last_name: newCustomer.value.last_name,
    customer_phone: newCustomer.value.phone,
    is_new: true
  })
  
  console.log('New customer confirmed:', selectedCustomer.value) // DEBUG
}
</script>
