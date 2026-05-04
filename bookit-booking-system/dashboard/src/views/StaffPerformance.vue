<template>
  <div>
    <div class="mb-6">
      <h2 class="text-lg font-semibold text-gray-900">Staff Performance</h2>
      <p class="text-sm text-gray-600 mt-1">Compare performance across your team</p>
    </div>

    <div class="mb-4">
      <DateRangeSelector
        :model-from="dateFrom"
        :model-to="dateTo"
        :active-filter="activeFilter"
        @update:active-filter="activeFilter = $event"
        @change="handleDateRangeChange"
      />
    </div>

    <p v-if="!loading && !error" class="text-sm text-gray-600 mb-4">
      {{ staffList.length }} staff members · {{ periodBookingsTotal }} period bookings total
    </p>

    <div v-if="loading" class="bg-white rounded-lg border border-gray-200 overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="text-left text-gray-500 border-b border-gray-200">
            <th class="py-3 px-4">Staff Member</th>
            <th class="py-3 px-4">Bookings</th>
            <th class="py-3 px-4">Revenue</th>
            <th class="py-3 px-4">Avg Booking Value</th>
            <th class="py-3 px-4">No-Show Rate</th>
            <th class="py-3 px-4">All-Time Bookings</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="i in 3" :key="i" class="border-b border-gray-100 last:border-0">
            <td class="py-3 px-4">
              <div class="flex items-center gap-3">
                <div class="h-9 w-9 rounded-full bg-gray-200 animate-pulse"></div>
                <div>
                  <div class="h-3 w-28 bg-gray-200 rounded animate-pulse mb-2"></div>
                  <div class="h-3 w-20 bg-gray-100 rounded animate-pulse"></div>
                </div>
              </div>
            </td>
            <td class="py-3 px-4"><div class="h-3 w-10 bg-gray-200 rounded animate-pulse"></div></td>
            <td class="py-3 px-4"><div class="h-3 w-20 bg-gray-200 rounded animate-pulse"></div></td>
            <td class="py-3 px-4"><div class="h-3 w-20 bg-gray-200 rounded animate-pulse"></div></td>
            <td class="py-3 px-4"><div class="h-3 w-16 bg-gray-200 rounded animate-pulse"></div></td>
            <td class="py-3 px-4"><div class="h-3 w-10 bg-gray-200 rounded animate-pulse"></div></td>
          </tr>
        </tbody>
      </table>
    </div>

    <ErrorState
      v-else-if="error"
      title="Failed to load staff performance"
      :message="errorMessage"
      :details="errorDetails"
      :show-home="false"
      @retry="fetchStaffPerformance"
    />

    <div v-else class="bg-white rounded-lg border border-gray-200 overflow-x-auto">
      <div v-if="sortedStaff.length === 0" class="p-6">
        <EmptyState
          icon="👥"
          title="No staff data found for this period"
          description="Try another date range to compare team performance."
        />
      </div>

      <table v-else class="min-w-full text-sm">
        <thead>
          <tr class="text-left text-gray-500 border-b border-gray-200">
            <th class="py-3 px-4">
              <button class="font-medium hover:text-gray-700" @click="toggleSort('name')">Staff Member</button>
            </th>
            <th class="py-3 px-4">
              <button class="font-medium hover:text-gray-700" @click="toggleSort('bookings')">Bookings</button>
            </th>
            <th class="py-3 px-4">
              <button class="font-medium hover:text-gray-700" @click="toggleSort('revenue')">Revenue</button>
            </th>
            <th class="py-3 px-4">
              <button class="font-medium hover:text-gray-700" @click="toggleSort('avg_booking_value')">Avg Booking Value</button>
            </th>
            <th class="py-3 px-4">
              <button class="font-medium hover:text-gray-700" @click="toggleSort('no_show_rate')">No-Show Rate</button>
            </th>
            <th class="py-3 px-4">
              <button class="font-medium hover:text-gray-700" @click="toggleSort('total_bookings_alltime')">All-Time Bookings</button>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="staff in sortedStaff"
            :key="staff.id"
            class="border-b border-gray-100 last:border-0 hover:bg-gray-50 cursor-pointer"
            @click="goToStaffDetail(staff.id)"
          >
            <td class="py-3 px-4">
              <div class="flex items-center gap-3">
                <img
                  v-if="staff.photo_url"
                  :src="staff.photo_url"
                  :alt="staff.name"
                  class="h-9 w-9 rounded-full object-cover border border-gray-200"
                />
                <div
                  v-else
                  class="h-9 w-9 rounded-full bg-gray-200 text-gray-700 flex items-center justify-center text-xs font-semibold"
                >
                  {{ initials(staff.name) }}
                </div>
                <div>
                  <p class="font-medium text-gray-900">{{ staff.name }}</p>
                  <p class="text-xs text-gray-500">{{ staff.title || '—' }}</p>
                </div>
              </div>
            </td>
            <td class="py-3 px-4 text-gray-900">{{ Number(staff.bookings || 0) }}</td>
            <td class="py-3 px-4 text-gray-900">{{ formatMoney(staff.revenue) }}</td>
            <td class="py-3 px-4 text-gray-700">{{ formatMoney(staff.avg_booking_value) }}</td>
            <td class="py-3 px-4">
              <span class="font-medium" :class="noShowRateClass(staff.no_show_rate)">
                {{ formatPercent(staff.no_show_rate) }}
              </span>
            </td>
            <td class="py-3 px-4 text-gray-700">{{ Number(staff.total_bookings_alltime || 0) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useApi } from '../composables/useApi'
import DateRangeSelector from '../components/DateRangeSelector.vue'
import ErrorState from '../components/ErrorState.vue'
import EmptyState from '../components/EmptyState.vue'

const api = useApi()
const router = useRouter()

const loading = ref(true)
const error = ref(false)
const errorMessage = ref('')
const errorDetails = ref('')

const dateFrom = ref('')
const dateTo = ref('')
const activeFilter = ref('this_month')
const staffList = ref([])

const sortBy = ref('revenue')
const sortDir = ref('desc')

function toLocalDateString(date) {
  return new Intl.DateTimeFormat('en-CA', { timeZone: 'Europe/London' }).format(date)
}

function applyDefaultDates() {
  const now = new Date()
  dateFrom.value = toLocalDateString(new Date(now.getFullYear(), now.getMonth(), 1))
  dateTo.value = toLocalDateString(now)
}

const periodBookingsTotal = computed(() => {
  return staffList.value.reduce((sum, staff) => sum + Number(staff.bookings || 0), 0)
})

const sortedStaff = computed(() => {
  const rows = [...staffList.value]
  return rows.sort((a, b) => {
    const aVal = a[sortBy.value]
    const bVal = b[sortBy.value]

    if (typeof aVal === 'string' || typeof bVal === 'string') {
      const strA = String(aVal || '')
      const strB = String(bVal || '')
      return sortDir.value === 'asc' ? strA.localeCompare(strB) : strB.localeCompare(strA)
    }

    const numA = Number(aVal || 0)
    const numB = Number(bVal || 0)
    return sortDir.value === 'asc' ? numA - numB : numB - numA
  })
})

function toggleSort(columnKey) {
  if (sortBy.value === columnKey) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortBy.value = columnKey
    sortDir.value = columnKey === 'name' ? 'asc' : 'desc'
  }
}

function formatMoney(value) {
  return `£${Number(value || 0).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}

function formatPercent(value) {
  return `${Number(value || 0).toFixed(1)}%`
}

function initials(name) {
  const parts = String(name || '').trim().split(/\s+/).filter(Boolean)
  if (parts.length === 0) {
    return 'NA'
  }
  return parts.slice(0, 2).map((part) => part.charAt(0).toUpperCase()).join('')
}

function noShowRateClass(rate) {
  const value = Number(rate || 0)
  if (value < 5) {
    return 'text-green-600'
  }
  if (value <= 10) {
    return 'text-amber-600'
  }
  return 'text-red-600'
}

function goToStaffDetail(staffId) {
  router.push(`/reports/staff/${staffId}`)
}

async function fetchStaffPerformance() {
  loading.value = true
  error.value = false
  errorMessage.value = ''
  errorDetails.value = ''

  try {
    const response = await api.get(`/reports/staff?date_from=${dateFrom.value}&date_to=${dateTo.value}`)
    if (response.data?.success) {
      staffList.value = response.data.staff || []
    } else {
      throw new Error(response.data?.message || 'Failed to load staff performance')
    }
  } catch (err) {
    error.value = true
    errorMessage.value = err.message || 'An unexpected error occurred.'
    errorDetails.value = `Status: ${err.status || 'N/A'}`
  } finally {
    loading.value = false
  }
}

function handleDateRangeChange({ from, to }) {
  dateFrom.value = from
  dateTo.value = to
  fetchStaffPerformance()
}

onMounted(() => {
  applyDefaultDates()
  fetchStaffPerformance()
})
</script>
