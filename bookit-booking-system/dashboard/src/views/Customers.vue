<template>
  <div>
    <div class="mb-6 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
      <div>
        <h2 class="text-lg font-semibold text-gray-900">Customers</h2>
        <p class="text-sm text-gray-600 mt-1">Manage your customer database</p>
      </div>
      <button
        type="button"
        class="w-full sm:w-auto px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors font-medium text-center"
        @click="exportCsv"
      >
        Export CSV
      </button>
    </div>

    <div class="mb-4">
      <div class="relative">
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
          <svg aria-hidden="true" class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search by name, email or phone..."
          class="w-full pl-10 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
        />
      </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6 p-4">
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label for="status-filter" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
          <select
            id="status-filter"
            v-model="filters.status"
            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            @change="loadCustomers(1)"
          >
            <option value="">All</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
            <option value="new">New</option>
          </select>
        </div>
        <div>
          <label for="per-page-filter" class="block text-sm font-medium text-gray-700 mb-1">Per page</label>
          <select
            id="per-page-filter"
            v-model.number="filters.per_page"
            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            @change="loadCustomers(1)"
          >
            <option :value="25">25</option>
            <option :value="50">50</option>
            <option :value="100">100</option>
          </select>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
      <div v-if="loading">
        <TableSkeleton :rows="8" :columns="8" />
      </div>

      <ErrorState
        v-else-if="error"
        title="Failed to load customers"
        :message="errorMessage"
        :details="errorDetails"
        :show-home="false"
        @retry="loadCustomers(pagination.current_page)"
      />

      <div v-else-if="customers.length === 0" class="p-8 text-center text-sm text-gray-600">
        No customers found matching your search.
      </div>

      <div v-else>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bookings</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Spent</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Visit</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr
                v-for="customer in customers"
                :key="customer.id"
                class="hover:bg-gray-50 cursor-pointer transition-colors"
                @click="viewCustomer(customer.id)"
              >
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center gap-3">
                    <div
                      class="w-9 h-9 rounded-full flex items-center justify-center text-white text-sm font-semibold"
                      :class="getAvatarColour(customer.id)"
                    >
                      {{ getInitials(customer.first_name, customer.last_name) }}
                    </div>
                    <div>
                      <p class="text-sm font-medium text-gray-900">{{ customer.full_name }}</p>
                      <span v-if="customer.marketing_consent" class="inline-flex items-center text-xs text-gray-500">
                        <svg aria-hidden="true" class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8m-18 8h18a2 2 0 002-2V8a2 2 0 00-2-2H3a2 2 0 00-2 2v6a2 2 0 002 2z" />
                        </svg>
                        Opted in
                      </span>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ customer.email }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ customer.phone || '-' }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ customer.total_bookings }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ formatMoney(customer.total_spent) }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ formatDate(customer.last_visit) }}</td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="px-2 py-1 text-xs font-medium rounded-full" :class="getStatusClass(customer.status)">
                    {{ formatStatus(customer.status) }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                  <button
                    class="text-primary-600 hover:text-primary-900"
                    @click.stop="viewCustomer(customer.id)"
                  >
                    View
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <nav class="bg-gray-50 px-4 sm:px-6 py-4 border-t border-gray-200" aria-label="Customers pagination">
          <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
            <div class="text-sm text-gray-700">
              Showing
              <span class="font-medium">{{ resultsStart }}</span>
              to
              <span class="font-medium">{{ resultsEnd }}</span>
              of
              <span class="font-medium">{{ pagination.total }}</span>
              customers
            </div>

            <div class="flex items-center gap-1 sm:gap-2">
              <button
                @click="goToPage(1)"
                :disabled="pagination.current_page <= 1"
                class="hidden sm:block px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                &laquo; First
              </button>
              <button
                @click="goToPage(pagination.current_page - 1)"
                :disabled="pagination.current_page <= 1"
                class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                &lsaquo; Prev
              </button>

              <div class="hidden sm:flex items-center gap-1">
                <button
                  v-for="page in visiblePages"
                  :key="page"
                  @click="goToPage(page)"
                  class="px-3 py-2 text-sm font-medium rounded-lg"
                  :class="page === pagination.current_page
                    ? 'bg-primary-600 text-white'
                    : 'text-gray-700 bg-white border border-gray-300 hover:bg-gray-50'"
                >
                  {{ page }}
                </button>
              </div>

              <span class="sm:hidden text-sm text-gray-700 px-2">
                {{ pagination.current_page }} / {{ pagination.total_pages }}
              </span>

              <button
                @click="goToPage(pagination.current_page + 1)"
                :disabled="pagination.current_page >= pagination.total_pages"
                class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Next &rsaquo;
              </button>
              <button
                @click="goToPage(pagination.total_pages)"
                :disabled="pagination.current_page >= pagination.total_pages"
                class="hidden sm:block px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Last &raquo;
              </button>
            </div>
          </div>
        </nav>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useApi } from '../composables/useApi'
import ErrorState from '../components/ErrorState.vue'
import TableSkeleton from '../components/TableSkeleton.vue'

const router = useRouter()
const api = useApi()

const loading = ref(true)
const error = ref(false)
const errorMessage = ref('')
const errorDetails = ref('')
const customers = ref([])

const searchQuery = ref('')
const filters = ref({
  status: '',
  per_page: 25
})

const pagination = ref({
  total: 0,
  per_page: 25,
  current_page: 1,
  total_pages: 1
})

let searchTimeout = null

const resultsStart = computed(() => {
  if (customers.value.length === 0) return 0
  return ((pagination.value.current_page - 1) * pagination.value.per_page) + 1
})

const resultsEnd = computed(() => {
  if (customers.value.length === 0) return 0
  const end = pagination.value.current_page * pagination.value.per_page
  return Math.min(end, pagination.value.total)
})

const visiblePages = computed(() => {
  const current = pagination.value.current_page
  const total = pagination.value.total_pages
  const pages = []

  if (total > 0) pages.push(1)
  for (let i = Math.max(2, current - 1); i <= Math.min(total - 1, current + 1); i++) {
    if (!pages.includes(i)) pages.push(i)
  }
  if (total > 1 && !pages.includes(total)) pages.push(total)

  return pages
})

const avatarColours = ['bg-blue-500', 'bg-green-500', 'bg-purple-500', 'bg-amber-500', 'bg-rose-500', 'bg-teal-500']

function getInitials(firstName, lastName) {
  return ((firstName?.[0] || '') + (lastName?.[0] || '')).toUpperCase()
}

function getAvatarColour(id) {
  return avatarColours[id % avatarColours.length]
}

function formatMoney(value) {
  return `£${Number(value || 0).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}

function formatDate(dateValue) {
  if (!dateValue) return 'Never'
  const date = new Date(`${dateValue}T00:00:00`)
  return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

function formatStatus(status) {
  const labels = {
    active: 'Active',
    inactive: 'Inactive',
    new: 'New'
  }
  return labels[status] || 'Inactive'
}

function getStatusClass(status) {
  const map = {
    active: 'bg-green-100 text-green-800',
    inactive: 'bg-gray-100 text-gray-700',
    new: 'bg-blue-100 text-blue-800'
  }
  return map[status] || map.inactive
}

async function loadCustomers(page = 1) {
  loading.value = true
  error.value = false
  errorMessage.value = ''
  errorDetails.value = ''

  try {
    const params = new URLSearchParams({
      page: String(page),
      per_page: String(filters.value.per_page)
    })

    if (filters.value.status) params.append('status', filters.value.status)
    if (searchQuery.value) params.append('search', searchQuery.value)

    const response = await api.get(`/customers?${params.toString()}`)
    if (!response.data?.success) {
      throw new Error(response.data?.message || 'Failed to load customers')
    }

    customers.value = response.data.customers || []
    pagination.value = {
      ...pagination.value,
      ...(response.data.pagination || {}),
      per_page: response.data?.pagination?.per_page || filters.value.per_page
    }
  } catch (err) {
    error.value = true
    errorMessage.value = err.message || 'An unexpected error occurred.'
    errorDetails.value = `Status: ${err.status || 'N/A'}`
  } finally {
    loading.value = false
  }
}

function goToPage(page) {
  if (page < 1 || page > pagination.value.total_pages) return
  loadCustomers(page)
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

function viewCustomer(id) {
  router.push(`/customers/${id}`)
}

function exportCsv() {
  const restBase = window.BOOKIT_DASHBOARD.restBase || window.location.origin + '/wp-json/bookit/v1/'
  const url = `${restBase}dashboard/customers/export`
  window.open(url, '_blank')
}

watch(searchQuery, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    loadCustomers(1)
  }, 400)
})

onMounted(() => {
  loadCustomers(1)
})

onBeforeUnmount(() => {
  clearTimeout(searchTimeout)
})
</script>
