<template>
  <div>
    <!-- Header with Actions -->
    <div class="mb-6 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
      <div>
        <h2 class="text-lg font-semibold text-gray-900">All Bookings</h2>
        <p class="text-sm text-gray-600 mt-1">
          Manage all appointments
        </p>
      </div>
      <button
        class="w-full sm:w-auto px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors font-medium text-center"
        @click="createBooking"
      >
        + New Booking
      </button>
    </div>

    <!-- Filters Section -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
      <!-- Filter Header (always visible) -->
      <div class="px-4 lg:px-6 py-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <h3 class="text-base font-semibold text-gray-900">Filters</h3>
          <span
            v-if="activeFilterCount > 0"
            class="inline-flex items-center justify-center w-6 h-6 text-xs font-bold text-white bg-primary-600 rounded-full"
          >
            {{ activeFilterCount }}
          </span>
        </div>

        <div class="flex items-center gap-2">
          <button
            v-if="hasActiveFilters"
            @click="clearFilters"
            class="px-3 py-1.5 text-sm font-medium text-red-600 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100"
          >
            Clear All
          </button>

          <button
            @click="showFilters = !showFilters"
            class="lg:hidden p-2 text-gray-600 hover:bg-gray-100 rounded-lg"
            aria-label="Toggle filters"
            :aria-expanded="showFilters"
          >
            <svg
              aria-hidden="true"
              class="w-5 h-5 transition-transform"
              :class="{ 'rotate-180': showFilters }"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>
        </div>
      </div>

      <!-- Filter Inputs (collapsible on mobile, always visible on desktop) -->
      <div
        class="px-4 lg:px-6 pb-4 border-t border-gray-200"
        :class="{ 'hidden lg:block': !showFilters }"
      >
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-4"
             :class="isAdmin ? 'lg:grid-cols-5' : 'lg:grid-cols-4'">
          <!-- Date From -->
          <div>
            <label for="filter-date-from" class="block text-sm font-medium text-gray-700 mb-1">
              From Date
            </label>
            <input
              id="filter-date-from"
              v-model="filters.date_from"
              type="date"
              class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              @change="applyFilters"
            />
          </div>

          <!-- Date To -->
          <div>
            <label for="filter-date-to" class="block text-sm font-medium text-gray-700 mb-1">
              To Date
            </label>
            <input
              id="filter-date-to"
              v-model="filters.date_to"
              type="date"
              class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              @change="applyFilters"
            />
          </div>

          <!-- Staff Filter (Admin Only) -->
          <div v-if="isAdmin">
            <label for="filter-staff" class="block text-sm font-medium text-gray-700 mb-1">
              Staff Member
            </label>
            <select
              id="filter-staff"
              v-model="filters.staff_id"
              class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              @change="applyFilters"
            >
              <option value="">All Staff</option>
              <option v-for="staff in staffList" :key="staff.id" :value="staff.id">
                {{ staff.name }}
              </option>
            </select>
          </div>

          <!-- Service Filter -->
          <div>
            <label for="filter-service" class="block text-sm font-medium text-gray-700 mb-1">
              Service
            </label>
            <select
              id="filter-service"
              v-model="filters.service_id"
              class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              @change="applyFilters"
            >
              <option value="">All Services</option>
              <option v-for="service in servicesList" :key="service.id" :value="service.id">
                {{ service.name }}
              </option>
            </select>
          </div>

          <!-- Status Filter -->
          <div>
            <label for="filter-status" class="block text-sm font-medium text-gray-700 mb-1">
              Status
            </label>
            <select
              id="filter-status"
              v-model="filters.status"
              class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              @change="applyFilters"
            >
              <option value="">All Statuses</option>
              <option value="pending">Pending</option>
              <option value="pending_payment">Pending Payment</option>
              <option value="confirmed">Confirmed</option>
              <option value="completed">Completed</option>
              <option value="cancelled">Cancelled</option>
              <option value="no_show">No Show</option>
            </select>
          </div>
        </div>

        <!-- Search Bar -->
        <div class="mt-4">
          <label for="filter-search" class="block text-sm font-medium text-gray-700 mb-1">
            Search
          </label>
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <svg aria-hidden="true" class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </div>
            <input
              id="filter-search"
              v-model="searchQuery"
              type="text"
              placeholder="Search by customer name, email, or booking reference..."
              class="w-full pl-10 pr-10 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              @input="onSearchInput"
            />
            <button
              v-if="searchQuery"
              @click="searchQuery = ''; applyFilters()"
              class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600"
              aria-label="Clear search"
            >
              <svg aria-hidden="true" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Content Area -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
      <!-- Loading State -->
      <div v-if="loading">
        <div class="hidden md:block">
          <TableSkeleton :rows="8" :columns="6" />
        </div>
        <div class="md:hidden p-4 space-y-3">
          <CardSkeleton v-for="i in 5" :key="i" />
        </div>
      </div>

      <!-- Error State -->
      <ErrorState
        v-else-if="error"
        :title="errorTitle"
        :message="errorMessage"
        :details="errorDetails"
        @retry="loadBookings"
      />

      <!-- Empty State (No Bookings) -->
      <EmptyState
        v-else-if="bookings.length === 0 && !hasActiveFilters"
        icon="📅"
        title="No bookings yet"
        description="Bookings will appear here once customers start making appointments. Create your first booking to get started."
        action-text="+ Create First Booking"
        @action="createBooking"
      />

      <!-- Empty State (No Results from Filters) -->
      <EmptyState
        v-else-if="bookings.length === 0 && hasActiveFilters"
        icon="🔍"
        title="No bookings found"
        description="No bookings match your current filters. Try adjusting your search criteria or clearing filters."
        action-text="Clear Filters"
        @action="clearFilters"
      />

      <!-- Actual Content -->
      <div v-else>
      <div
        v-if="selectedIds.length > 0 && isAdmin"
        class="px-4 sm:px-6 pt-4 flex flex-col sm:flex-row sm:items-center gap-3"
      >
        <select
          v-model="bulkAction"
          class="w-full sm:w-auto px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
        >
          <option value="">Select action...</option>
          <option value="cancel">Cancel bookings</option>
          <option value="complete">Mark as complete</option>
          <option value="no_show">Mark as no-show</option>
        </select>
        <button
          @click="applyBulkAction"
          :disabled="!bulkAction || bulkActionLoading"
          class="w-full sm:w-auto px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors font-medium disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {{ bulkActionLoading ? 'Applying...' : `Apply to ${selectedIds.length} booking(s)` }}
        </button>
      </div>

      <!-- Desktop Table View -->
      <div class="hidden md:block overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th v-if="isAdmin" scope="col" class="px-6 py-3 w-12">
                <input
                  type="checkbox"
                  :checked="allVisibleSelected"
                  @change="toggleSelectAllVisible"
                  aria-label="Select all bookings on this page"
                  class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500"
                />
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                <span class="inline-flex items-center gap-1">
                  Reference
                  <BookitTooltip
                    content="Unique booking identifier in format BK[YYMM]-XXXX. Use this to look up or reference a specific appointment."
                    position="top"
                  />
                </span>
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Date &amp; Time
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Customer
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Service
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Staff
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                <span class="inline-flex items-center gap-1">
                  Status
                  <BookitTooltip
                    content="Pending: awaiting confirmation. Confirmed: appointment set. Completed: attended. Cancelled: booking cancelled. No-show: customer did not attend."
                    position="top"
                  />
                </span>
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Amount
              </th>
              <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                Actions
              </th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr
              v-for="booking in bookings"
              :key="booking.id"
              class="hover:bg-gray-50 cursor-pointer transition-colors"
              @click="viewBooking(booking)"
            >
              <td v-if="isAdmin" class="px-6 py-4 whitespace-nowrap">
                <input
                  v-model="selectedIds"
                  :value="booking.id"
                  type="checkbox"
                  @click.stop
                  aria-label="Select booking"
                  class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500"
                />
              </td>
              <!-- Reference -->
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-semibold text-gray-900">
                  {{ getBookingReference(booking) }}
                </div>
              </td>

              <!-- Date & Time -->
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900">
                  {{ formatDate(booking.booking_date) }}
                </div>
                <div class="text-sm text-gray-500">
                  {{ booking.start_time }} - {{ booking.end_time }}
                </div>
              </td>

              <!-- Customer -->
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900">
                  {{ booking.customer_name }}
                </div>
                <div class="text-sm text-gray-500">
                  {{ booking.customer_email }}
                </div>
              </td>

              <!-- Service -->
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900">
                  {{ booking.service_name }}
                </div>
                <div class="text-sm text-gray-500">
                  {{ booking.duration }} min
                </div>
              </td>

              <!-- Staff -->
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                {{ booking.staff_name }}
              </td>

              <!-- Status -->
              <td class="px-6 py-4 whitespace-nowrap">
                <span
                  class="px-2 py-1 text-xs font-medium rounded-full"
                  :class="getStatusClass(booking.status)"
                >
                  {{ formatStatus(booking.status) }}
                </span>
              </td>

              <!-- Amount -->
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900">
                  &pound;{{ booking.total_price.toFixed(2) }}
                </div>
                <div class="text-xs text-gray-500">
                  {{ getPaymentLabel(booking) }}
                </div>
              </td>

              <!-- Actions -->
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                <button
                  @click.stop="viewBooking(booking)"
                  class="text-primary-600 hover:text-primary-900"
                  :aria-label="'View booking for ' + booking.customer_name"
                >
                  View
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Mobile Card View -->
      <div class="md:hidden divide-y divide-gray-200">
        <div
          v-for="booking in bookings"
          :key="'mobile-' + booking.id"
          class="p-4 hover:bg-gray-50 cursor-pointer"
          @click="viewBooking(booking)"
        >
          <div class="flex items-start justify-between mb-2">
            <div class="flex-1 min-w-0">
              <p class="text-xs font-semibold text-gray-700 mb-1">{{ getBookingReference(booking) }}</p>
              <p class="text-sm font-medium text-gray-900">{{ booking.customer_name }}</p>
              <p class="text-xs text-gray-500 truncate">{{ booking.customer_email }}</p>
            </div>
            <span
              class="px-2 py-1 text-xs font-medium rounded-full whitespace-nowrap ml-2"
              :class="getStatusClass(booking.status)"
            >
              {{ formatStatus(booking.status) }}
            </span>
          </div>

          <p class="text-sm text-gray-900 mb-2">
            {{ booking.service_name }}
            <span class="text-gray-500">({{ booking.duration }} min)</span>
          </p>

          <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-600">
            <span>&#x1F4C5; {{ formatDate(booking.booking_date) }}</span>
            <span>&#x23F0; {{ booking.start_time }} - {{ booking.end_time }}</span>
            <span>&#x1F464; {{ booking.staff_name }}</span>
          </div>

          <div class="mt-2 text-sm font-medium text-gray-900">
            &pound;{{ booking.total_price.toFixed(2) }}
            <span class="text-xs font-normal text-gray-500 ml-1">{{ getPaymentLabel(booking) }}</span>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <nav class="bg-gray-50 px-4 sm:px-6 py-4 border-t border-gray-200" aria-label="Bookings pagination">
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
          <!-- Results Info -->
          <div class="text-sm text-gray-700">
            Showing
            <span class="font-medium">{{ resultsStart }}</span>
            to
            <span class="font-medium">{{ resultsEnd }}</span>
            of
            <span class="font-medium">{{ pagination.total }}</span>
            bookings
          </div>

          <!-- Pagination Controls -->
          <div class="flex items-center gap-1 sm:gap-2">
            <button
              @click="goToPage(1)"
              :disabled="!pagination.has_prev"
              class="hidden sm:block px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              &laquo; First
            </button>
            <button
              @click="goToPage(pagination.current_page - 1)"
              :disabled="!pagination.has_prev"
              class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              &lsaquo; Prev
            </button>

            <!-- Page Numbers -->
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

            <!-- Mobile Page Indicator -->
            <span class="sm:hidden text-sm text-gray-700 px-2">
              {{ pagination.current_page }} / {{ pagination.total_pages }}
            </span>

            <button
              @click="goToPage(pagination.current_page + 1)"
              :disabled="!pagination.has_next"
              class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Next &rsaquo;
            </button>
            <button
              @click="goToPage(pagination.total_pages)"
              :disabled="!pagination.has_next"
              class="hidden sm:block px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Last &raquo;
            </button>
          </div>
        </div>
      </nav>
      </div>
    </div>

    <!-- Booking Creation Modal -->
    <Transition name="fade">
      <BookingModal
        v-if="showBookingModal"
        @close="closeBookingModal"
        @created="handleBookingCreated"
      />
    </Transition>

    <!-- Booking View/Edit Modal -->
    <Transition name="fade">
      <BookingViewModal
        v-if="showViewModal && selectedBookingId"
        :booking-id="selectedBookingId"
        @close="closeViewModal"
        @updated="handleBookingUpdated"
        @cancelled="handleBookingCancelled"
      />
    </Transition>

    <!-- Bulk Action Confirmation Modal -->
    <div v-if="showBulkConfirmModal" class="fixed inset-0 bg-black bg-opacity-50 z-[60] flex items-center justify-center p-4">
      <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-4 sm:p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-3">{{ getBulkActionTitle() }}</h3>
        <p class="text-sm text-gray-700 mb-2">This will apply to {{ selectedIds.length }} booking(s).</p>
        <p class="text-sm text-red-600 mb-6">This action cannot be undone.</p>
        <div class="flex flex-col-reverse sm:flex-row justify-end gap-2">
          <button
            @click="showBulkConfirmModal = false"
            :disabled="bulkActionLoading"
            class="w-full sm:w-auto px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
          >
            Cancel
          </button>
          <button
            @click="confirmBulkAction"
            :disabled="bulkActionLoading"
            class="w-full sm:w-auto px-4 py-2.5 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 disabled:opacity-50"
          >
            {{ bulkActionLoading ? 'Applying...' : 'Confirm' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useApi } from '../composables/useApi'
import { useToast } from '../composables/useToast'
import BookingModal from '../components/BookingModal.vue'
import BookingViewModal from '../components/BookingViewModal.vue'
import ErrorState from '../components/ErrorState.vue'
import TableSkeleton from '../components/TableSkeleton.vue'
import CardSkeleton from '../components/CardSkeleton.vue'
import EmptyState from '../components/EmptyState.vue'

const api = useApi()
const { success: toastSuccess, error: toastError } = useToast()

// Get current user role
const currentUser = window.BOOKIT_DASHBOARD.staff
const isAdmin = computed(() => currentUser.role === 'admin')

// State
const loading = ref(true)
const error = ref(null)
const errorTitle = ref('')
const errorMessage = ref('')
const errorDetails = ref('')
const bookings = ref([])
const staffList = ref([])
const servicesList = ref([])
const searchQuery = ref('')
const showFilters = ref(false)
let searchTimeout = null
const selectedIds = ref([])
const bulkAction = ref('')
const bulkActionLoading = ref(false)
const showBulkConfirmModal = ref(false)

// Filters
const filters = ref({
  date_from: '',
  date_to: '',
  staff_id: '',
  service_id: '',
  status: '',
})

// Pagination
const pagination = ref({
  total: 0,
  per_page: 20,
  current_page: 1,
  total_pages: 1,
  has_next: false,
  has_prev: false,
})

// Computed
const activeFilterCount = computed(() => {
  let count = 0
  if (filters.value.date_from) count++
  if (filters.value.date_to) count++
  if (filters.value.staff_id) count++
  if (filters.value.service_id) count++
  if (filters.value.status) count++
  if (searchQuery.value) count++
  return count
})

const hasActiveFilters = computed(() => activeFilterCount.value > 0)

const resultsStart = computed(() => {
  if (bookings.value.length === 0) return 0
  return ((pagination.value.current_page - 1) * pagination.value.per_page) + 1
})

const resultsEnd = computed(() => {
  const end = pagination.value.current_page * pagination.value.per_page
  return Math.min(end, pagination.value.total)
})

const visiblePages = computed(() => {
  const current = pagination.value.current_page
  const total = pagination.value.total_pages
  const pages = []

  // Always show first page
  if (total > 0) pages.push(1)

  // Show pages around current
  for (let i = Math.max(2, current - 1); i <= Math.min(total - 1, current + 1); i++) {
    if (!pages.includes(i)) pages.push(i)
  }

  // Always show last page
  if (total > 1 && !pages.includes(total)) pages.push(total)

  return pages
})

const visibleBookingIds = computed(() => bookings.value.map(booking => booking.id))

const allVisibleSelected = computed(() => {
  if (visibleBookingIds.value.length === 0) return false
  return visibleBookingIds.value.every(id => selectedIds.value.includes(id))
})

// Methods
const loadBookings = async (page = 1) => {
  loading.value = true
  error.value = null
  selectedIds.value = []

  try {
    // Build query params
    const params = new URLSearchParams({
      page: page.toString(),
      per_page: pagination.value.per_page.toString(),
    })

    if (filters.value.date_from) params.append('date_from', filters.value.date_from)
    if (filters.value.date_to) params.append('date_to', filters.value.date_to)
    if (filters.value.staff_id) params.append('staff_id', filters.value.staff_id)
    if (filters.value.service_id) params.append('service_id', filters.value.service_id)
    if (filters.value.status) params.append('status', filters.value.status)
    if (searchQuery.value) params.append('search', searchQuery.value)

    const response = await api.get(`/bookings?${params.toString()}`)

    if (response.data.success) {
      bookings.value = response.data.bookings
      pagination.value = response.data.pagination
    } else {
      throw new Error(response.data.message || 'Failed to load bookings')
    }
  } catch (err) {
    console.error('Error loading bookings:', err)
    error.value = true

    if (err.response?.status === 403) {
      errorTitle.value = 'Access denied'
      errorMessage.value = 'You don\'t have permission to view bookings. Please contact your administrator.'
    } else if (err.response?.status >= 500) {
      errorTitle.value = 'Server error'
      errorMessage.value = 'Our servers are experiencing issues. Please try again in a few moments.'
    } else if (!navigator.onLine) {
      errorTitle.value = 'No internet connection'
      errorMessage.value = 'Please check your internet connection and try again.'
    } else {
      errorTitle.value = 'Failed to load bookings'
      errorMessage.value = err.response?.data?.message || err.message || 'An unexpected error occurred.'
    }

    errorDetails.value = `Error: ${err.message}\nStatus: ${err.response?.status || 'N/A'}\nURL: ${err.config?.url || 'N/A'}`
  } finally {
    loading.value = false
  }
}

const loadFilterData = async () => {
  try {
    // Load staff list
    const staffResponse = await api.get('/staff/list')
    if (staffResponse.data.success) {
      staffList.value = staffResponse.data.staff
    }

    // Load services list
    const servicesResponse = await api.get('/services/list')
    if (servicesResponse.data.success) {
      servicesList.value = servicesResponse.data.services
    }
  } catch (err) {
    console.error('Error loading filter data:', err)
  }
}

const applyFilters = () => {
  loadBookings(1) // Reset to page 1 when filters change
}

const onSearchInput = () => {
  // Debounce search
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    loadBookings(1)
  }, 500)
}

const clearFilters = () => {
  filters.value = {
    date_from: '',
    date_to: '',
    staff_id: '',
    service_id: '',
    status: '',
  }
  searchQuery.value = ''
  loadBookings(1)
}

const goToPage = (page) => {
  if (page < 1 || page > pagination.value.total_pages) return
  loadBookings(page)
  // Scroll to top
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

const toggleSelectAllVisible = () => {
  if (allVisibleSelected.value) {
    const visibleSet = new Set(visibleBookingIds.value)
    selectedIds.value = selectedIds.value.filter(id => !visibleSet.has(id))
    return
  }

  const merged = new Set([...selectedIds.value, ...visibleBookingIds.value])
  selectedIds.value = Array.from(merged)
}

const getBulkActionTitle = () => {
  if (bulkAction.value === 'cancel') return 'Cancel bookings'
  if (bulkAction.value === 'complete') return 'Mark bookings as complete'
  if (bulkAction.value === 'no_show') return 'Mark bookings as no-show'
  return 'Bulk action'
}

const applyBulkAction = () => {
  if (!bulkAction.value || selectedIds.value.length === 0) return
  showBulkConfirmModal.value = true
}

const confirmBulkAction = async () => {
  if (!bulkAction.value || selectedIds.value.length === 0 || bulkActionLoading.value) return

  bulkActionLoading.value = true

  try {
    const response = await api.post('/bookings/bulk-action', {
      action: bulkAction.value,
      booking_ids: selectedIds.value
    })

    const succeeded = Array.isArray(response.data?.succeeded) ? response.data.succeeded : []
    const failed = Array.isArray(response.data?.failed) ? response.data.failed : []
    const total = selectedIds.value.length

    if (succeeded.length === total) {
      toastSuccess(`${succeeded.length} bookings updated.`)
    } else if (succeeded.length > 0) {
      const reasons = failed.map(item => item.reason).filter(Boolean).join('; ')
      toastError(`${succeeded.length} of ${total} bookings updated. ${failed.length} failed: ${reasons}`)
    } else {
      const reasons = failed.map(item => item.reason).filter(Boolean).join('; ')
      toastError(`No bookings were updated. ${reasons || 'Please review booking statuses and try again.'}`)
    }
  } catch (err) {
    console.error('Error applying bulk action:', err)
    toastError(`Bulk update failed: ${err.message}`)
  } finally {
    bulkActionLoading.value = false
    showBulkConfirmModal.value = false
    selectedIds.value = []
    bulkAction.value = ''
    loadBookings(pagination.value.current_page)
  }
}

// View/Edit modal state.
const selectedBookingId = ref(null)
const showViewModal = ref(false)

const viewBooking = (booking) => {
  selectedBookingId.value = booking.id
  showViewModal.value = true
}

const handleBookingUpdated = (updatedBooking) => {
  // Refresh bookings list.
  loadBookings(pagination.value.current_page)
  showViewModal.value = false
}

const handleBookingCancelled = (bookingId) => {
  // Refresh bookings list.
  loadBookings(pagination.value.current_page)
  showViewModal.value = false
}

const closeViewModal = () => {
  showViewModal.value = false
  selectedBookingId.value = null
}

// Create modal state.
const showBookingModal = ref(false)

const createBooking = () => {
  showBookingModal.value = true
}

const closeBookingModal = () => {
  showBookingModal.value = false
}

const handleBookingCreated = (booking) => {
  loadBookings(pagination.value.current_page)
  showBookingModal.value = false
  toastSuccess(`Booking created for ${booking.customer_name}`)
}

const formatDate = (dateString) => {
  const date = new Date(dateString + 'T00:00:00') // Force local timezone
  return date.toLocaleDateString('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric'
  })
}

const getBookingReference = (booking) => {
  if (booking?.booking_reference && booking.booking_reference.trim() !== '') {
    return booking.booking_reference
  }
  return `#${booking.id}`
}

const formatStatus = (status) => {
  const labels = {
    'pending': 'Pending',
    'pending_payment': 'Pending Payment',
    'confirmed': 'Confirmed',
    'completed': 'Completed',
    'cancelled': 'Cancelled',
    'no_show': 'No Show'
  }
  return labels[status] || status
}

const getStatusClass = (status) => {
  const classes = {
    'confirmed': 'bg-green-100 text-green-800',
    'pending': 'bg-yellow-100 text-yellow-800',
    'pending_payment': 'bg-orange-100 text-orange-800',
    'completed': 'bg-blue-100 text-blue-800',
    'cancelled': 'bg-red-100 text-red-800',
    'no_show': 'bg-gray-100 text-gray-800'
  }
  return classes[status] || 'bg-gray-100 text-gray-800'
}

const getPaymentLabel = (booking) => {
  const total = parseFloat(booking.total_price) || 0
  const paid = parseFloat(booking.deposit_paid) || 0

  if (paid > total && total > 0) {
    // Overpayment (tip included).
    const tip = paid - total
    return `\u00A3${paid.toFixed(2)} paid (incl. \u00A3${tip.toFixed(2)} tip)`
  }
  if (paid >= total && total > 0) {
    // Fully paid (exact amount).
    return `\u00A3${paid.toFixed(2)} paid in full`
  }
  if (paid > 0) {
    // Partially paid.
    return `\u00A3${paid.toFixed(2)} paid, \u00A3${(total - paid).toFixed(2)} due`
  }
  if (booking.payment_method === 'pay_on_arrival') {
    return 'Pay on arrival'
  }
  return `${formatPaymentMethod(booking.payment_method)} - Unpaid`
}

const formatPaymentMethod = (method) => {
  const labels = {
    'pay_on_arrival': 'Pay on Arrival',
    'cash': 'Cash',
    'card_external': 'Card',
    'check': 'Check',
    'complimentary': 'Complimentary',
    'stripe': 'Stripe'
  }
  return labels[method] || method || 'Unknown'
}

// Lifecycle
onMounted(() => {
  loadFilterData()
  loadBookings()
})
</script>
