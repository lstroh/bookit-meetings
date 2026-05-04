<template>
  <div>
    <router-link to="/reports/staff" class="inline-flex items-center text-sm text-primary-700 hover:text-primary-800 mb-4">
      ← Back to Staff Performance
    </router-link>

    <div v-if="loading" class="space-y-4">
      <div class="bg-white rounded-lg border border-gray-200 p-4">
        <div class="h-5 w-44 bg-gray-200 rounded animate-pulse mb-3"></div>
        <div class="h-4 w-64 bg-gray-100 rounded animate-pulse"></div>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
        <div v-for="i in 6" :key="i" class="bg-white rounded-lg border border-gray-200 p-4">
          <div class="h-3 w-20 bg-gray-200 rounded animate-pulse mb-2"></div>
          <div class="h-6 w-24 bg-gray-100 rounded animate-pulse"></div>
        </div>
      </div>
    </div>

    <ErrorState
      v-else-if="error"
      title="Failed to load staff detail"
      :message="errorMessage"
      :details="errorDetails"
      :show-home="false"
      @retry="fetchDetail"
    />

    <div v-else-if="staffData">
      <div class="mb-6 bg-white rounded-lg border border-gray-200 p-4">
        <div class="flex items-center gap-3">
          <img
            v-if="staffData.photo_url"
            :src="staffData.photo_url"
            :alt="staffData.name"
            class="h-14 w-14 rounded-full object-cover border border-gray-200"
          />
          <div
            v-else
            class="h-14 w-14 rounded-full bg-gray-200 text-gray-700 flex items-center justify-center text-base font-semibold"
          >
            {{ initials(staffData.name) }}
          </div>

          <div>
            <h2 class="text-lg font-semibold text-gray-900">{{ staffData.name }}</h2>
            <p class="text-sm text-gray-600">{{ staffData.title || '—' }}</p>
            <p class="text-xs text-gray-500 mt-1">Member since {{ formatDate(staffData.member_since) }}</p>
          </div>
        </div>
      </div>

      <div class="mb-4">
        <DateRangeSelector
          :model-from="dateFrom"
          :model-to="dateTo"
          :active-filter="activeFilter"
          @update:active-filter="activeFilter = $event"
          @change="onDateRangeChange"
        />
      </div>

      <div
        v-if="isNewTeamMember"
        class="mb-4 rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800"
      >
        New team member — not enough data for trends yet.
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-6 gap-4 mb-3">
        <div class="bg-white rounded-lg border border-gray-200 p-4">
          <p class="text-xs font-medium text-gray-500 mb-1">Bookings</p>
          <p class="text-xl font-semibold text-gray-900">{{ Number(staffData.bookings || 0) }}</p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-4">
          <p class="text-xs font-medium text-gray-500 mb-1">Completed</p>
          <p class="text-xl font-semibold text-gray-900">{{ Number(staffData.completed || 0) }}</p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-4">
          <p class="text-xs font-medium text-gray-500 mb-1">No-Shows</p>
          <p class="text-xl font-semibold text-gray-900">{{ Number(staffData.no_shows || 0) }}</p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-4">
          <p class="text-xs font-medium text-gray-500 mb-1">No-Show Rate</p>
          <p class="text-xl font-semibold" :class="noShowRateClass(staffData.no_show_rate)">
            {{ formatPercent(staffData.no_show_rate) }}
          </p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-4">
          <p class="text-xs font-medium text-gray-500 mb-1">Revenue</p>
          <p class="text-xl font-semibold text-gray-900">{{ formatMoney(staffData.revenue) }}</p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-4">
          <p class="text-xs font-medium text-gray-500 mb-1">Avg Booking Value</p>
          <p class="text-xl font-semibold text-gray-900">{{ formatMoney(staffData.avg_booking_value) }}</p>
        </div>
      </div>

      <div
        v-if="Number(staffData.no_show_rate || 0) > 10"
        class="mb-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800"
      >
        ⚠️ High no-show rate. May indicate scheduling or experience issues.
      </div>

      <p class="text-sm text-gray-600 mb-6">
        All time: {{ Number(staffData.total_bookings_alltime || 0) }} bookings · {{ formatMoney(staffData.total_revenue_alltime) }} revenue
      </p>

      <div class="mb-4 border-b border-gray-200">
        <nav class="-mb-px flex space-x-6">
          <button
            type="button"
            class="py-2 text-sm font-medium border-b-2"
            :class="activeTab === 'performance' ? 'border-primary-600 text-primary-700' : 'border-transparent text-gray-500 hover:text-gray-700'"
            @click="activeTab = 'performance'"
          >
            Performance
          </button>
          <button
            type="button"
            class="py-2 text-sm font-medium border-b-2"
            :class="activeTab === 'services' ? 'border-primary-600 text-primary-700' : 'border-transparent text-gray-500 hover:text-gray-700'"
            @click="activeTab = 'services'"
          >
            Services
          </button>
          <button
            type="button"
            class="py-2 text-sm font-medium border-b-2"
            :class="activeTab === 'time_off' ? 'border-primary-600 text-primary-700' : 'border-transparent text-gray-500 hover:text-gray-700'"
            @click="activeTab = 'time_off'"
          >
            Time Off
          </button>
        </nav>
      </div>

      <div v-if="activeTab === 'performance'" class="bg-white rounded-lg border border-gray-200 p-4">
        <h3 class="text-base font-semibold text-gray-900 mb-4">Weekly Bookings Trend</h3>
        <EmptyState
          v-if="weeklyTrend.length === 0"
          icon="📉"
          title="No trend data for this period"
          description="Try another date range to view weekly booking trends."
        />
        <div v-else style="height: 240px; position: relative;">
          <Bar
            v-if="useBarChart"
            :data="weeklyTrendChartData"
            :options="lineChartOptions"
          />
          <Line
            v-else
            :data="weeklyTrendChartData"
            :options="lineChartOptions"
          />
        </div>
      </div>

      <div v-else-if="activeTab === 'services'" class="bg-white rounded-lg border border-gray-200 p-4 overflow-x-auto">
        <h3 class="text-base font-semibold text-gray-900 mb-4">Services</h3>
        <EmptyState
          v-if="sortedServices.length === 0"
          icon="🧾"
          title="No service data for this period"
          description="No service data for this period"
        />
        <table v-else class="min-w-full text-sm">
          <thead>
            <tr class="text-left text-gray-500 border-b border-gray-200">
              <th class="py-2 pr-4">Service</th>
              <th class="py-2 pr-4">
                <button class="font-medium hover:text-gray-700" @click="toggleServiceSort('booking_count')">Bookings</button>
              </th>
              <th class="py-2 pr-1">
                <button class="font-medium hover:text-gray-700" @click="toggleServiceSort('revenue')">Revenue</button>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="service in sortedServices" :key="service.service_name" class="border-b border-gray-100 last:border-0">
              <td class="py-3 pr-4 text-gray-900">{{ service.service_name }}</td>
              <td class="py-3 pr-4 text-gray-700">{{ Number(service.booking_count || 0) }}</td>
              <td class="py-3 pr-1 text-gray-900">{{ formatMoney(service.revenue) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-else class="space-y-3">
        <h3 class="text-base font-semibold text-gray-900">Upcoming Time Off</h3>
        <EmptyState
          v-if="timeOffRows.length === 0"
          icon="🗓️"
          title="No upcoming time off scheduled"
          description="No upcoming time off scheduled"
        />
        <div
          v-for="entry in timeOffRows"
          :key="entry.id"
          class="bg-white rounded-lg border border-gray-200 p-4"
        >
          <p class="text-sm font-semibold text-gray-900">{{ formatDate(entry.specific_date) }}</p>
          <p class="text-sm text-gray-600 mt-1">{{ timeRangeLabel(entry.start_time, entry.end_time) }}</p>
          <p class="text-xs text-gray-500 mt-1">Reason: {{ reasonLabel(entry.notes) }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { Bar, Line } from 'vue-chartjs'
import {
  Chart as ChartJS,
  BarElement,
  CategoryScale,
  LinearScale,
  LineElement,
  PointElement,
  Title,
  Tooltip,
  Legend
} from 'chart.js'
import { useApi } from '../composables/useApi'
import DateRangeSelector from '../components/DateRangeSelector.vue'
import ErrorState from '../components/ErrorState.vue'
import EmptyState from '../components/EmptyState.vue'

ChartJS.register(BarElement, CategoryScale, LinearScale, LineElement, PointElement, Title, Tooltip, Legend)

const route = useRoute()
const api = useApi()

const staffId = computed(() => route.params.id)
const staffData = ref(null)
const loading = ref(false)
const error = ref(false)
const errorMessage = ref('')
const errorDetails = ref('')
const activeTab = ref('performance')

const dateFrom = ref('')
const dateTo = ref('')
const activeFilter = ref('this_month')

const servicesSortBy = ref('revenue')
const servicesSortDir = ref('desc')

function toLocalDateString(date) {
  return new Intl.DateTimeFormat('en-CA', { timeZone: 'Europe/London' }).format(date)
}

function applyDefaultDates() {
  const now = new Date()
  dateFrom.value = toLocalDateString(new Date(now.getFullYear(), now.getMonth(), 1))
  dateTo.value = toLocalDateString(now)
}

const weeklyTrend = computed(() => staffData.value?.weekly_trend || [])
const servicesRows = computed(() => staffData.value?.by_service || [])
const timeOffRows = computed(() => staffData.value?.time_off || [])
const useBarChart = computed(() => {
  if (!dateFrom.value || !dateTo.value) return false
  const from = new Date(dateFrom.value)
  const to = new Date(dateTo.value)
  const days = Math.round((to - from) / (1000 * 60 * 60 * 24))
  return days <= 7
})

const sortedServices = computed(() => {
  const rows = [...servicesRows.value]
  return rows.sort((a, b) => {
    const numA = Number(a[servicesSortBy.value] || 0)
    const numB = Number(b[servicesSortBy.value] || 0)
    return servicesSortDir.value === 'asc' ? numA - numB : numB - numA
  })
})

const weeklyTrendChartData = computed(() => {
  return {
    labels: weeklyTrend.value.map((row) => row.week_label),
    datasets: [
      {
        label: 'Bookings',
        data: weeklyTrend.value.map((row) => Number(row.booking_count || 0)),
        borderColor: '#6366F1',
        backgroundColor: '#6366F1',
        tension: 0.25,
        pointRadius: 3,
        pointHoverRadius: 5
      }
    ]
  }
})

const lineChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false }
  },
  scales: {
    y: {
      beginAtZero: true,
      ticks: {
        precision: 0
      }
    }
  }
}

const isNewTeamMember = computed(() => {
  if (!staffData.value?.member_since) {
    return false
  }
  const joined = new Date(staffData.value.member_since)
  const now = new Date()
  const daysSinceJoined = (now.getTime() - joined.getTime()) / (1000 * 60 * 60 * 24)
  return daysSinceJoined >= 0 && daysSinceJoined <= 30
})

function toggleServiceSort(columnKey) {
  if (servicesSortBy.value === columnKey) {
    servicesSortDir.value = servicesSortDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    servicesSortBy.value = columnKey
    servicesSortDir.value = 'desc'
  }
}

function formatMoney(value) {
  return `£${Number(value || 0).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}

function formatPercent(value) {
  return `${Number(value || 0).toFixed(1)}%`
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

function initials(name) {
  const parts = String(name || '').trim().split(/\s+/).filter(Boolean)
  if (parts.length === 0) {
    return 'NA'
  }
  return parts.slice(0, 2).map((part) => part.charAt(0).toUpperCase()).join('')
}

function formatDate(dateString) {
  if (!dateString) {
    return '—'
  }
  const [year, month, day] = String(dateString).split('-')
  if (!year || !month || !day) {
    return dateString
  }
  return `${day}/${month}/${year}`
}

function formatTime(timeString) {
  if (!timeString) {
    return ''
  }
  return String(timeString).slice(0, 5)
}

function timeRangeLabel(startTime, endTime) {
  const start = String(startTime || '')
  const end = String(endTime || '')
  if (!start || !end || (start === '00:00:00' && (end === '23:59:59' || end === '00:00:00'))) {
    return 'All Day'
  }
  return `${formatTime(start)} - ${formatTime(end)}`
}

function reasonLabel(notes) {
  const labels = {
    vacation: 'Vacation',
    sick_leave: 'Sick Leave',
    lunch_break: 'Lunch Break',
    personal: 'Personal',
    other: 'Other'
  }

  const reason = parseReasonKey(notes)
  return labels[reason] || 'Other'
}

function parseReasonKey(notes) {
  if (!notes) {
    return 'other'
  }

  const parts = String(notes).split('|')
  for (const part of parts) {
    const [key, value] = part.split(':')
    if (key && value && key.trim() === 'reason') {
      return value.trim()
    }
  }
  return 'other'
}

function onDateRangeChange({ from, to }) {
  dateFrom.value = from
  dateTo.value = to
  fetchDetail()
}

async function fetchDetail() {
  loading.value = true
  error.value = false
  errorMessage.value = ''
  errorDetails.value = ''

  try {
    const response = await api.get(`/reports/staff/${staffId.value}?date_from=${dateFrom.value}&date_to=${dateTo.value}`)
    if (response.data?.success) {
      staffData.value = response.data.staff
    } else {
      throw new Error(response.data?.message || 'Failed to load staff detail')
    }
  } catch (err) {
    error.value = true
    errorMessage.value = err.message || 'An unexpected error occurred.'
    errorDetails.value = `Status: ${err.status || 'N/A'}`
  } finally {
    loading.value = false
  }
}

watch(() => route.params.id, () => {
  fetchDetail()
})

onMounted(() => {
  applyDefaultDates()
  fetchDetail()
})
</script>
