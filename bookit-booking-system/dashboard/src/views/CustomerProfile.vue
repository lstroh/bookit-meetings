<template>
  <div>
    <button
      class="mb-4 text-sm text-primary-600 hover:text-primary-700 font-medium"
      @click="router.push('/customers')"
    >
      ← Back to Customers
    </button>

    <div v-if="loading">
      <div class="bg-white rounded-lg border border-gray-200 p-6">
        <div class="h-6 w-48 bg-gray-200 rounded animate-pulse mb-4"></div>
        <div class="h-4 w-72 bg-gray-100 rounded animate-pulse"></div>
      </div>
    </div>

    <ErrorState
      v-else-if="error"
      title="Failed to load customer"
      :message="errorMessage"
      :details="errorDetails"
      :show-home="false"
      @retry="loadCustomer"
    />

    <div v-else-if="customer" class="space-y-6">
      <div class="bg-white rounded-lg border border-gray-200 p-6">
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
          <div class="flex items-center gap-4 min-w-0">
            <div
              class="w-16 h-16 rounded-full flex items-center justify-center text-white text-xl font-semibold"
              :class="getAvatarColour(customer.id)"
            >
              {{ getInitials(customer.first_name, customer.last_name) }}
            </div>
            <div class="min-w-0">
              <h2 class="text-xl font-semibold text-gray-900 truncate">{{ customer.full_name }}</h2>
              <p class="text-sm text-gray-600 truncate">{{ customer.email }}</p>
              <p class="text-sm text-gray-500 mt-1">Member since {{ formatDate(customer.member_since) }}</p>
            </div>
          </div>

          <div class="flex gap-2">
            <button
              class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
              @click="toggleEdit"
            >
              {{ editMode ? 'Close' : 'Edit' }}
            </button>
            <button
              class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700"
              @click="showDeleteModal = true"
            >
              Delete
            </button>
          </div>
        </div>
      </div>

      <div v-if="editMode" class="bg-white rounded-lg border border-gray-200 p-6">
        <div class="mb-4">
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div class="text-sm text-gray-700">
              <span class="font-medium text-gray-900">Email</span>
              <span class="mx-2 text-gray-300">•</span>
              <span class="font-mono">{{ customer.email }}</span>
            </div>
            <button
              type="button"
              class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
              @click="toggleEmailForm"
            >
              {{ showEmailForm ? 'Close' : 'Change Email' }}
            </button>
          </div>

          <div v-if="emailChangeSuccess" class="mt-3 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800">
            {{ emailChangeSuccess }}
          </div>
          <div v-if="emailChangeError" class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
            {{ emailChangeError }}
          </div>

          <form v-if="showEmailForm" class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-3" @submit.prevent="sendEmailChangeRequest">
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1">New email address</label>
              <input
                v-model="newEmail"
                type="email"
                required
                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                placeholder="name@example.com"
              />
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1">Reason</label>
              <select
                v-model="emailReason"
                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                required
              >
                <option value="Typo fix">Typo fix</option>
                <option value="Customer request">Customer request</option>
                <option value="Other">Other</option>
              </select>
            </div>
            <div class="md:col-span-2 flex gap-2">
              <button
                type="button"
                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
                :disabled="emailChangeLoading"
                @click="cancelEmailChange"
              >
                Cancel
              </button>
              <button
                type="submit"
                class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 disabled:opacity-50"
                :disabled="emailChangeLoading"
              >
                {{ emailChangeLoading ? 'Sending...' : 'Send Verification' }}
              </button>
            </div>
          </form>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
            <input
              v-model="editForm.first_name"
              type="text"
              class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
            <input
              v-model="editForm.last_name"
              type="text"
              class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
            <input
              v-model="editForm.phone"
              type="text"
              class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            />
          </div>
          <div class="flex items-center">
            <label class="inline-flex items-center gap-2 text-sm text-gray-700 mt-6">
              <input v-model="editForm.marketing_consent" type="checkbox" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
              Marketing Consent
            </label>
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
            <textarea
              v-model="editForm.notes"
              rows="4"
              class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            ></textarea>
          </div>
        </div>

        <div class="mt-4 flex gap-2">
          <button
            class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 disabled:opacity-50"
            :disabled="saving"
            @click="saveCustomer"
          >
            {{ saving ? 'Saving...' : 'Save' }}
          </button>
          <button
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
            :disabled="saving"
            @click="cancelEdit"
          >
            Cancel
          </button>
        </div>
      </div>

      <div v-if="deleteError" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        {{ deleteError }}
      </div>

      <div class="grid grid-cols-2 lg:grid-cols-6 gap-3">
        <div class="bg-white rounded-lg border border-gray-200 p-3">
          <p class="text-xs text-gray-500">Total Bookings</p>
          <p class="text-lg font-semibold text-gray-900">{{ customer.total_bookings || 0 }}</p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-3">
          <p class="text-xs text-gray-500">Total Spent</p>
          <p class="text-lg font-semibold text-gray-900">{{ formatMoney(customer.total_spent) }}</p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-3">
          <p class="text-xs text-gray-500">Avg Booking Value</p>
          <p class="text-lg font-semibold text-gray-900">{{ formatMoney(avgBookingValue) }}</p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-3">
          <p class="text-xs text-gray-500">Last Visit</p>
          <p class="text-lg font-semibold text-gray-900">{{ formatDate(customer.last_visit) }}</p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-3">
          <p class="text-xs text-gray-500">Upcoming</p>
          <p class="text-lg font-semibold text-gray-900">{{ customer.upcoming_count || 0 }}</p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-3">
          <p class="text-xs text-gray-500">Cancellation Rate</p>
          <p class="text-lg font-semibold text-gray-900">{{ cancellationRate }}%</p>
        </div>
      </div>

      <div>
        <span
          class="inline-flex items-center px-3 py-1 text-sm font-medium rounded-full"
          :class="customer.marketing_consent ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700'"
        >
          {{ customer.marketing_consent ? '✅ Marketing opted in' : '❌ Marketing opted out' }}
        </span>
      </div>

      <div v-if="isAdmin" class="bg-white rounded-lg border border-gray-200 p-4 sm:p-6">
        <h3 class="text-base font-semibold text-gray-900">Export Customer Data</h3>
        <p class="text-sm text-gray-600 mt-1">
          Download this customer's personal, booking, payment, and audit data.
        </p>
        <div class="mt-4 flex flex-col sm:flex-row gap-2">
          <button
            type="button"
            class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700"
            @click="exportCustomerData('json')"
          >
            Export as JSON
          </button>
          <button
            type="button"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
            @click="exportCustomerData('csv')"
          >
            Export as CSV
          </button>
        </div>
      </div>

      <div class="bg-white rounded-lg border border-gray-200">
        <div class="border-b border-gray-200 px-4 sm:px-6 py-3 flex gap-3">
          <button
            class="px-3 py-1.5 text-sm font-medium rounded-lg"
            :class="activeTab === 'bookings' ? 'bg-primary-600 text-white' : 'text-gray-700 bg-gray-100 hover:bg-gray-200'"
            @click="activeTab = 'bookings'"
          >
            Booking History
          </button>
          <button
            class="px-3 py-1.5 text-sm font-medium rounded-lg"
            :class="activeTab === 'payments' ? 'bg-primary-600 text-white' : 'text-gray-700 bg-gray-100 hover:bg-gray-200'"
            @click="activeTab = 'payments'"
          >
            Payment History
          </button>
          <button
            class="px-3 py-1.5 text-sm font-medium rounded-lg"
            :class="activeTab === 'packages' ? 'bg-primary-600 text-white' : 'text-gray-700 bg-gray-100 hover:bg-gray-200'"
            @click="activeTab = 'packages'"
          >
            Packages
          </button>
        </div>

        <div v-if="activeTab === 'bookings'" class="p-4 sm:p-6">
          <div v-if="!customer.bookings?.length" class="text-sm text-gray-600">No booking history yet.</div>
          <div v-else class="space-y-2">
            <div
              v-for="booking in customer.bookings"
              :key="booking.id"
              class="rounded-lg border border-gray-200 px-4 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2"
            >
              <div class="text-sm text-gray-700">
                <div class="font-medium text-gray-900">{{ formatDate(booking.booking_date) }} | {{ formatTimeRange(booking.start_time, booking.end_time) }}</div>
                <div>{{ booking.service_name }} · {{ booking.staff_name }}</div>
              </div>
              <div class="flex items-center gap-2">
                <span class="px-2 py-1 text-xs font-medium rounded-full" :class="getBookingStatusClass(booking.status)">
                  {{ formatBookingStatus(booking.status) }}
                </span>
                <span class="text-sm font-semibold text-gray-900">{{ formatMoney(booking.total_price) }}</span>
              </div>
            </div>
          </div>
        </div>

        <div v-else-if="activeTab === 'payments'" class="p-4 sm:p-6">
          <div v-if="!customer.payments?.length" class="text-sm text-gray-600">No payment records.</div>
          <div v-else class="space-y-2">
            <div
              v-for="payment in customer.payments"
              :key="payment.id"
              class="rounded-lg border border-gray-200 px-4 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2"
            >
              <div class="text-sm text-gray-700">
                <div class="font-medium text-gray-900">{{ formatDateTime(payment.transaction_date) }}</div>
                <div>{{ paymentMethodLabel(payment.payment_method) }} · {{ paymentTypeLabel(payment.payment_type) }}</div>
              </div>
              <div class="flex items-center gap-2">
                <span class="text-sm font-semibold text-gray-900">{{ formatMoney(payment.amount) }}</span>
                <span class="px-2 py-1 text-xs font-medium rounded-full" :class="getPaymentStatusClass(payment.payment_status)">
                  {{ formatPaymentStatus(payment.payment_status) }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <div v-else-if="activeTab === 'packages'" class="p-4 sm:p-6">
          <div v-if="packagesLoading" role="status" aria-live="polite" class="text-sm text-gray-500">Loading packages...</div>
          <div v-else-if="packagesError" role="alert" class="text-sm text-red-600">{{ packagesError }}</div>
          <div v-else-if="!customerPackages.length" class="text-sm text-gray-600">
            No packages found for this customer.
          </div>
          <div v-else class="space-y-2">
            <div
              v-for="pkg in customerPackages"
              :key="pkg.id"
              class="rounded-lg border border-gray-200 px-4 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2"
            >
              <div class="text-sm text-gray-700">
                <div class="font-medium text-gray-900">{{ pkg.package_type_name }}</div>
                <div>{{ pkg.sessions_remaining }} / {{ pkg.sessions_total }} sessions remaining</div>
                <div v-if="pkg.expires_at" class="text-xs text-gray-500">
                  Expires {{ formatDate(pkg.expires_at) }}
                </div>
              </div>
              <div class="flex flex-col items-start sm:items-end">
                <span
                  class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full"
                  :class="{
                    'bg-green-100 text-green-800': pkg.status === 'active',
                    'bg-gray-100 text-gray-600': pkg.status === 'exhausted',
                    'bg-amber-100 text-amber-800': pkg.status === 'expired',
                    'bg-red-100 text-red-700': pkg.status === 'cancelled',
                  }"
                >
                  <!-- A11y audit: status is conveyed with explicit text (not colour alone). -->
                  {{ pkg.status.charAt(0).toUpperCase() + pkg.status.slice(1) }}
                </span>
                <button
                  type="button"
                  class="mt-2 text-xs text-primary-600 hover:text-primary-700 font-medium"
                  :aria-expanded="expandedPackageId === pkg.id ? 'true' : 'false'"
                  :aria-controls="`customer-package-redemptions-${pkg.id}`"
                  @click="togglePackageRedemptions(pkg)"
                >
                  {{ expandedPackageId === pkg.id ? 'Hide history' : 'View history' }}
                </button>
                <div
                  v-if="expandedPackageId === pkg.id"
                  :id="`customer-package-redemptions-${pkg.id}`"
                  class="mt-3 pt-3 border-t border-gray-100 w-full"
                  aria-live="polite"
                >
                  <div v-if="redemptionsLoading && !redemptionsCache[pkg.id]" role="status" aria-live="polite" class="text-xs text-gray-500">
                    Loading...
                  </div>
                  <div v-else-if="redemptionsError[pkg.id]" role="alert" class="text-xs text-red-600">
                    {{ redemptionsError[pkg.id] }}
                  </div>
                  <div v-else-if="!redemptionsCache[pkg.id]?.length" class="text-xs text-gray-500 italic">
                    No sessions redeemed yet.
                  </div>
                  <div v-else class="space-y-1">
                    <div
                      v-for="r in redemptionsCache[pkg.id]"
                      :key="r.id"
                      class="text-xs text-gray-600 flex justify-between"
                    >
                      <span>{{ r.booking_date }} {{ r.start_time?.slice(0,5) }} · {{ r.service_name || '—' }}</span>
                      <span class="text-gray-400">{{ r.redeemed_by_name }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div
      v-if="showDeleteModal"
      class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center px-4"
      @click.self="showDeleteModal = false"
    >
      <div class="w-full max-w-lg bg-white rounded-lg shadow-lg border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-3">Delete Customer Data</h3>
        <p class="text-sm text-gray-700 mb-5">
          This will permanently anonymise this customer's personal data in compliance with GDPR Article 17. Their booking records will be retained for 7 years as required by UK tax law. This cannot be undone.
        </p>
        <div class="flex justify-end gap-2">
          <button
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
            :disabled="deleting"
            @click="showDeleteModal = false"
          >
            Cancel
          </button>
          <button
            class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-50"
            :disabled="deleting"
            @click="deleteCustomer"
          >
            {{ deleting ? 'Deleting...' : 'Delete Customer Data' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useApi } from '../composables/useApi'
import { useToast } from '../composables/useToast'
import ErrorState from '../components/ErrorState.vue'

const route = useRoute()
const router = useRouter()
const api = useApi()
const { success: toastSuccess, error: toastError } = useToast()
const currentUser = window.BOOKIT_DASHBOARD?.staff || {}

const loading = ref(true)
const error = ref(false)
const errorMessage = ref('')
const errorDetails = ref('')
const customer = ref(null)

const editMode = ref(false)
const saving = ref(false)
const deleting = ref(false)
const showDeleteModal = ref(false)
const deleteError = ref('')
const showEmailForm = ref(false)
const newEmail = ref('')
const emailReason = ref('Typo fix')
const emailChangeLoading = ref(false)
const emailChangeSuccess = ref(null)
const emailChangeError = ref(null)
const activeTab = ref('bookings')
const customerPackages = ref([])
const packagesLoading = ref(false)
const packagesError = ref('')
const expandedPackageId = ref(null)
const redemptionsCache = ref({})
const redemptionsLoading = ref(false)
const redemptionsError = ref({})

const editForm = ref({
  first_name: '',
  last_name: '',
  phone: '',
  marketing_consent: false,
  notes: ''
})

const avatarColours = ['bg-blue-500', 'bg-green-500', 'bg-purple-500', 'bg-amber-500', 'bg-rose-500', 'bg-teal-500']

const avgBookingValue = computed(() => {
  const bookings = Number(customer.value?.total_bookings || 0)
  if (!bookings) return 0
  return Number(customer.value?.total_spent || 0) / bookings
})

const cancellationRate = computed(() => {
  const rows = customer.value?.bookings || []
  if (!rows.length) return 0
  const cancelled = rows.filter((row) => row.status === 'cancelled').length
  return ((cancelled / rows.length) * 100).toFixed(1)
})

const isAdmin = computed(() => {
  return currentUser.role === 'admin' || currentUser.role === 'bookit_admin'
})

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
  return date.toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

function formatDateTime(dateValue) {
  if (!dateValue) return 'Unknown'
  const date = new Date(dateValue.replace(' ', 'T'))
  return date.toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

function formatTimeRange(startTime, endTime) {
  return `${String(startTime || '').slice(0, 5)} - ${String(endTime || '').slice(0, 5)}`
}

function formatBookingStatus(status) {
  const labels = {
    pending: 'Pending',
    pending_payment: 'Pending',
    confirmed: 'Confirmed',
    completed: 'Completed',
    cancelled: 'Cancelled',
    no_show: 'No Show'
  }
  return labels[status] || status
}

function getBookingStatusClass(status) {
  const map = {
    confirmed: 'bg-blue-100 text-blue-800',
    completed: 'bg-green-100 text-green-800',
    cancelled: 'bg-gray-100 text-gray-700',
    no_show: 'bg-amber-100 text-amber-800',
    pending: 'bg-yellow-100 text-yellow-800',
    pending_payment: 'bg-yellow-100 text-yellow-800'
  }
  return map[status] || 'bg-gray-100 text-gray-700'
}

function paymentMethodLabel(method) {
  const labels = {
    stripe: 'Stripe',
    paypal: 'PayPal',
    cash: 'Cash',
    card: 'Card Machine',
    pay_on_arrival: 'Pay on Arrival'
  }
  return labels[method] || method || 'Unknown'
}

function paymentTypeLabel(type) {
  const labels = {
    deposit: 'Deposit',
    full_payment: 'Full Payment'
  }
  return labels[type] || type
}

function formatPaymentStatus(status) {
  const labels = {
    pending: 'Pending',
    completed: 'Completed',
    failed: 'Failed',
    refunded: 'Refunded',
    partially_refunded: 'Partially Refunded'
  }
  return labels[status] || status
}

function getPaymentStatusClass(status) {
  const map = {
    pending: 'bg-yellow-100 text-yellow-800',
    completed: 'bg-green-100 text-green-800',
    failed: 'bg-red-100 text-red-700',
    refunded: 'bg-gray-100 text-gray-700',
    partially_refunded: 'bg-gray-100 text-gray-700'
  }
  return map[status] || 'bg-gray-100 text-gray-700'
}

function fillEditForm() {
  if (!customer.value) return
  editForm.value = {
    first_name: customer.value.first_name || '',
    last_name: customer.value.last_name || '',
    phone: customer.value.phone || '',
    marketing_consent: Boolean(customer.value.marketing_consent),
    notes: customer.value.notes || ''
  }
}

function toggleEdit() {
  editMode.value = !editMode.value
  if (editMode.value) fillEditForm()
}

function cancelEdit() {
  fillEditForm()
  editMode.value = false
}

function toggleEmailForm() {
  showEmailForm.value = !showEmailForm.value
  emailChangeError.value = null
  emailChangeSuccess.value = null
  if (!showEmailForm.value) {
    newEmail.value = ''
    emailReason.value = 'Typo fix'
  }
}

function cancelEmailChange() {
  showEmailForm.value = false
  newEmail.value = ''
  emailReason.value = 'Typo fix'
  emailChangeLoading.value = false
  emailChangeError.value = null
  emailChangeSuccess.value = null
}

async function sendEmailChangeRequest() {
  if (!customer.value?.id) return
  emailChangeLoading.value = true
  emailChangeError.value = null
  emailChangeSuccess.value = null

  try {
    const id = customer.value.id
    const url = `${window.BOOKIT_DASHBOARD.apiBase}/customers/${id}/request-email-change`
    const resp = await fetch(url, {
      method: 'POST',
      headers: {
        'X-WP-Nonce': window.BOOKIT_DASHBOARD.nonce,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        new_email: newEmail.value,
        reason: emailReason.value
      })
    })

    const data = await resp.json().catch(() => ({}))
    if (!resp.ok) {
      const message = data?.message || data?.data?.message || 'Failed to request email change.'
      throw new Error(message)
    }

    showEmailForm.value = false
    emailChangeSuccess.value = `Verification email sent to ${newEmail.value}. The customer must click the link to confirm the change.`
    newEmail.value = ''
    emailReason.value = 'Typo fix'
  } catch (err) {
    emailChangeError.value = err.message || 'Failed to request email change.'
  } finally {
    emailChangeLoading.value = false
  }
}

async function loadCustomer() {
  loading.value = true
  error.value = false
  deleteError.value = ''

  try {
    const response = await api.get(`/customers/${route.params.id}`)
    if (!response.data?.success || !response.data?.customer) {
      throw new Error(response.data?.message || 'Failed to load customer')
    }
    customer.value = response.data.customer
    fillEditForm()
  } catch (err) {
    error.value = true
    errorMessage.value = err.message || 'An unexpected error occurred.'
    errorDetails.value = `Status: ${err.status || 'N/A'}`
  } finally {
    loading.value = false
  }
}

async function loadCustomerPackages() {
  packagesLoading.value = true
  packagesError.value = ''
  try {
    const response = await api.get(`/customer-packages?customer_id=${route.params.id}&per_page=50`)
    customerPackages.value = response.data?.packages || response.data || []
  } catch {
    packagesError.value = 'Failed to load packages.'
  } finally {
    packagesLoading.value = false
  }
}

async function togglePackageRedemptions(pkg) {
  if (expandedPackageId.value === pkg.id) {
    expandedPackageId.value = null
    return
  }

  expandedPackageId.value = pkg.id
  if (redemptionsCache.value[pkg.id]) return

  redemptionsLoading.value = true
  redemptionsError.value[pkg.id] = ''

  try {
    const response = await api.get(`/customer-packages/${pkg.id}/redemptions`)
    redemptionsCache.value[pkg.id] = response.data?.redemptions || []
  } catch (err) {
    redemptionsError.value[pkg.id] = err.message || 'Failed to load redemption history.'
  } finally {
    redemptionsLoading.value = false
  }
}

async function saveCustomer() {
  if (!customer.value) return
  saving.value = true
  deleteError.value = ''

  try {
    const payload = {
      first_name: editForm.value.first_name,
      last_name: editForm.value.last_name,
      phone: editForm.value.phone,
      marketing_consent: Boolean(editForm.value.marketing_consent),
      notes: editForm.value.notes
    }

    const response = await api.put(`/customers/${customer.value.id}`, payload)
    if (!response.data?.success) {
      throw new Error(response.data?.message || 'Failed to update customer')
    }

    toastSuccess(response.data.message || 'Customer updated successfully.')
    editMode.value = false
    await loadCustomer()
  } catch (err) {
    toastError(err.message || 'Failed to update customer')
  } finally {
    saving.value = false
  }
}

async function deleteCustomer() {
  if (!customer.value) return
  deleting.value = true
  deleteError.value = ''

  try {
    const response = await api.delete(`/customers/${customer.value.id}`)
    if (!response.data?.success) {
      throw new Error(response.data?.message || 'Failed to delete customer data')
    }

    toastSuccess(response.data.message || 'Customer data deleted.')
    showDeleteModal.value = false
    router.push('/customers')
  } catch (err) {
    const message = err.message || 'Failed to delete customer data'
    deleteError.value = message
    if (Number(err.status) === 409) {
      toastError(message)
    } else {
      toastError(message)
    }
  } finally {
    deleting.value = false
  }
}

function exportCustomerData(format) {
  if (!customer.value?.id) return
  const apiBase = window.BOOKIT_DASHBOARD?.apiBase || `${window.location.origin}/wp-json/bookit/v1/dashboard`
  const url = `${apiBase}/customers/${customer.value.id}/export?format=${encodeURIComponent(format)}`
  window.open(url, '_blank')
}

onMounted(() => {
  loadCustomer()
})

watch(activeTab, (tab) => {
  if (tab === 'packages' && customerPackages.value.length === 0 && !packagesError.value) {
    loadCustomerPackages()
  }
})
</script>
