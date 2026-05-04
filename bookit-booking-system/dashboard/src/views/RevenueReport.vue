<template>
  <div>
    <div class="mb-6 flex items-start justify-between gap-4">
      <div>
        <h2 class="text-lg font-semibold text-gray-900">Revenue Report</h2>
        <p class="text-sm text-gray-600 mt-1">Detailed revenue performance across services, staff, and payment methods.</p>
      </div>
      <button
        type="button"
        class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 whitespace-nowrap"
        @click="exportCsv"
      >
        Export CSV
      </button>
    </div>

    <div class="mb-6">
      <DateRangeSelector
        :model-from="dateFrom"
        :model-to="dateTo"
        :active-filter="activeFilter"
        @update:active-filter="activeFilter = $event"
        @change="handleDateRangeChange"
      />
    </div>

    <div
      v-if="!loading && !error && reportData?.is_today_in_range"
      class="mb-6 rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800"
    >
      ℹ️ Today's data is preliminary and may change as more bookings are processed.
    </div>

    <div v-if="loading">
      <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4 mb-6">
        <CardSkeleton v-for="i in 5" :key="i" />
      </div>
      <div class="bg-white rounded-lg border border-gray-200 p-4 mb-6">
        <div class="h-4 w-48 bg-gray-200 rounded animate-pulse mb-4"></div>
        <div class="h-[260px] bg-gray-100 rounded animate-pulse"></div>
      </div>
      <div class="bg-white rounded-lg border border-gray-200 p-4">
        <div class="h-4 w-40 bg-gray-200 rounded animate-pulse mb-4"></div>
        <div class="h-48 bg-gray-100 rounded animate-pulse"></div>
      </div>
    </div>

    <ErrorState
      v-else-if="error"
      title="Failed to load revenue report"
      :message="errorMessage"
      :details="errorDetails"
      :show-home="false"
      @retry="fetchReport"
    />

    <div v-else-if="reportData">
      <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4 mb-6">
        <div class="bg-white rounded-lg border border-gray-200 p-4">
          <p class="text-sm font-medium text-gray-500 mb-1">Total Revenue</p>
          <p class="text-2xl font-semibold text-gray-900">{{ formatMoney(reportData.summary.total_revenue) }}</p>
          <p class="text-xs text-gray-500 mt-1">Before refunds</p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-4">
          <p class="text-sm font-medium text-gray-500 mb-1">Deposits Collected</p>
          <p class="text-2xl font-semibold text-gray-900">{{ formatMoney(reportData.summary.deposits) }}</p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-4">
          <p class="text-sm font-medium text-gray-500 mb-1">Balance Payments</p>
          <p class="text-2xl font-semibold text-gray-900">{{ formatMoney(reportData.summary.balance_payments) }}</p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-4">
          <p class="text-sm font-medium text-gray-500 mb-1">Refunds Issued</p>
          <p
            class="text-2xl font-semibold"
            :class="Number(reportData.summary.refunds || 0) > 0 ? 'text-red-600' : 'text-gray-900'"
          >
            {{ formatMoney(reportData.summary.refunds) }}
          </p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-4">
          <p class="text-sm font-medium text-gray-500 mb-1">Net Revenue</p>
          <p class="text-2xl font-bold text-green-600">{{ formatMoney(reportData.summary.net_revenue) }}</p>
        </div>
      </div>

      <div class="bg-white rounded-lg border border-gray-200 p-4 mb-6">
        <h3 class="text-base font-semibold text-gray-900 mb-4">Revenue Trend</h3>
        <EmptyState
          v-if="isTrendEmpty"
          icon="📉"
          title="No revenue data for this period"
          description="Try another date range to view trend data."
        />
        <div v-else style="height: 260px; position: relative;">
          <Bar :data="trendChartData" :options="chartOptions" />
        </div>
      </div>

      <div class="bg-white rounded-lg border border-gray-200 p-4 mb-6 overflow-x-auto">
        <h3 class="text-base font-semibold text-gray-900 mb-4">Revenue by Service</h3>
        <EmptyState
          v-if="sortedByService.length === 0"
          icon="🧾"
          title="No service data for this period"
          description="No completed payments were found for services in this date range."
        />
        <table v-else class="min-w-full text-sm">
          <thead>
            <tr class="text-left text-gray-500 border-b border-gray-200">
              <th class="py-2 pr-4">
                <button class="font-medium hover:text-gray-700" @click="toggleServiceSort('service_name')">Service</button>
              </th>
              <th class="py-2 pr-4">
                <button class="font-medium hover:text-gray-700" @click="toggleServiceSort('booking_count')">Bookings</button>
              </th>
              <th class="py-2 pr-4">
                <button class="font-medium hover:text-gray-700" @click="toggleServiceSort('total_revenue')">Total Revenue</button>
              </th>
              <th class="py-2 pr-1">
                <button class="font-medium hover:text-gray-700" @click="toggleServiceSort('avg_price')">Avg Price</button>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in sortedByService"
              :key="row.service_id"
              class="border-b border-gray-100 last:border-0"
            >
              <td class="py-3 pr-4 text-gray-900">{{ row.service_name }}</td>
              <td class="py-3 pr-4 text-gray-700">{{ row.booking_count }}</td>
              <td class="py-3 pr-4 text-gray-900">{{ formatMoney(row.total_revenue) }}</td>
              <td class="py-3 pr-1 text-gray-700">{{ formatMoney(row.avg_price) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="bg-white rounded-lg border border-gray-200 p-4 mb-6 overflow-x-auto">
        <h3 class="text-base font-semibold text-gray-900 mb-4">Revenue by Staff Member</h3>
        <EmptyState
          v-if="sortedByStaff.length === 0"
          icon="👥"
          title="No staff data for this period"
          description="No completed payments were found for staff in this date range."
        />
        <table v-else class="min-w-full text-sm">
          <thead>
            <tr class="text-left text-gray-500 border-b border-gray-200">
              <th class="py-2 pr-4">
                <button class="font-medium hover:text-gray-700" @click="toggleStaffSort('staff_name')">Staff</button>
              </th>
              <th class="py-2 pr-4">
                <button class="font-medium hover:text-gray-700" @click="toggleStaffSort('booking_count')">Bookings</button>
              </th>
              <th class="py-2 pr-4">
                <button class="font-medium hover:text-gray-700" @click="toggleStaffSort('total_revenue')">Total Revenue</button>
              </th>
              <th class="py-2 pr-1">
                <button class="font-medium hover:text-gray-700" @click="toggleStaffSort('avg_per_booking')">Avg per Booking</button>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in sortedByStaff"
              :key="row.staff_name"
              class="border-b border-gray-100 last:border-0"
            >
              <td class="py-3 pr-4 text-gray-900">{{ row.staff_name }}</td>
              <td class="py-3 pr-4 text-gray-700">{{ row.booking_count }}</td>
              <td class="py-3 pr-4 text-gray-900">{{ formatMoney(row.total_revenue) }}</td>
              <td class="py-3 pr-1 text-gray-700">{{ formatMoney(row.avg_per_booking) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="bg-white rounded-lg border border-gray-200 p-4">
        <h3 class="text-base font-semibold text-gray-900 mb-4">Revenue by Payment Method</h3>
        <EmptyState
          v-if="paymentMethodRows.length === 0"
          icon="💳"
          title="No payment data for this period"
          description="No payment methods were recorded for this date range."
        />
        <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
          <div
            v-for="row in paymentMethodRows"
            :key="row.payment_method"
            class="rounded-lg border border-gray-200 p-3"
          >
            <p class="text-sm font-medium text-gray-900">{{ paymentMethodLabel(row.payment_method) }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ row.booking_count }} bookings</p>
            <p class="text-sm font-semibold text-gray-900 mt-2">{{ formatMoney(row.total_revenue) }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { Bar } from 'vue-chartjs'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend
} from 'chart.js'
import { useApi } from '../composables/useApi'
import CardSkeleton from '../components/CardSkeleton.vue'
import ErrorState from '../components/ErrorState.vue'
import EmptyState from '../components/EmptyState.vue'
import DateRangeSelector from '../components/DateRangeSelector.vue'

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend)

const api = useApi()

const loading = ref(true)
const error = ref(false)
const errorMessage = ref('')
const errorDetails = ref('')
const reportData = ref(null)

const serviceSortBy = ref('total_revenue')
const serviceSortDir = ref('desc')
const staffSortBy = ref('total_revenue')
const staffSortDir = ref('desc')

const dateFrom = ref('')
const dateTo = ref('')
const activeFilter = ref('this_month')

function toLocalDateString(date) {
  return new Intl.DateTimeFormat('en-CA', { timeZone: 'Europe/London' }).format(date)
}

function applyDefaultDates() {
  const now = new Date()
  dateFrom.value = toLocalDateString(new Date(now.getFullYear(), now.getMonth(), 1))
  dateTo.value = toLocalDateString(now)
}

applyDefaultDates()

const trendChartData = computed(() => {
  const trend = reportData.value?.revenue_trend || []
  return {
    labels: trend.map((item) => formatChartDate(item.date)),
    datasets: [
      {
        label: 'Revenue (£)',
        data: trend.map((item) => Number(item.revenue || 0)),
        borderColor: '#3B82F6',
        backgroundColor: '#3B82F6',
        pointRadius: 3,
        pointHoverRadius: 5
      }
    ]
  }
})

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: {
      callbacks: {
        label: (context) => `£${Number(context.raw).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
      }
    }
  },
  scales: {
    y: {
      beginAtZero: true,
      ticks: {
        callback: (value) => `£${Number(value).toLocaleString('en-GB')}`
      }
    }
  }
}

const isTrendEmpty = computed(() => {
  const trend = reportData.value?.revenue_trend || []
  return trend.length === 0 || trend.every((item) => Number(item.revenue || 0) === 0)
})

const sortedByService = computed(() => {
  const rows = [...(reportData.value?.by_service || [])]
  return rows.sort((a, b) => sortRows(a, b, serviceSortBy.value, serviceSortDir.value))
})

const sortedByStaff = computed(() => {
  const rows = [...(reportData.value?.by_staff || [])]
  return rows.sort((a, b) => sortRows(a, b, staffSortBy.value, staffSortDir.value))
})

const paymentMethodRows = computed(() => reportData.value?.by_payment_method || [])

function sortRows(a, b, key, dir) {
  const aVal = a[key]
  const bVal = b[key]

  if (typeof aVal === 'string' || typeof bVal === 'string') {
    const strA = String(aVal || '')
    const strB = String(bVal || '')
    return dir === 'asc' ? strA.localeCompare(strB) : strB.localeCompare(strA)
  }

  const numA = Number(aVal || 0)
  const numB = Number(bVal || 0)
  return dir === 'asc' ? numA - numB : numB - numA
}

function toggleServiceSort(columnKey) {
  if (serviceSortBy.value === columnKey) {
    serviceSortDir.value = serviceSortDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    serviceSortBy.value = columnKey
    serviceSortDir.value = columnKey === 'service_name' ? 'asc' : 'desc'
  }
}

function toggleStaffSort(columnKey) {
  if (staffSortBy.value === columnKey) {
    staffSortDir.value = staffSortDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    staffSortBy.value = columnKey
    staffSortDir.value = columnKey === 'staff_name' ? 'asc' : 'desc'
  }
}

function paymentMethodLabel(method) {
  const labels = {
    stripe: 'Stripe',
    paypal: 'PayPal',
    cash: 'Cash',
    card: 'Card Machine',
    pay_on_arrival: 'Pay on Arrival'
  }
  return labels[method] || method
}

function formatMoney(value) {
  return `£${Number(value || 0).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}

function formatChartDate(dateStr) {
  const [year, month, day] = String(dateStr).split('-')
  if (!year || !month || !day) return dateStr
  return `${day}/${month}`
}

async function fetchReport() {
  loading.value = true
  error.value = false
  errorMessage.value = ''
  errorDetails.value = ''

  try {
    const response = await api.get(`/reports/revenue?date_from=${dateFrom.value}&date_to=${dateTo.value}`)
    if (response.data?.success) {
      reportData.value = response.data
    } else {
      throw new Error(response.data?.message || 'Failed to load revenue report')
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
  fetchReport()
}

function exportCsv() {
  // Build the export URL using the WordPress REST API base directly,
  // not the dashboard apiBase which may include extra path segments.
  const restBase = window.BOOKIT_DASHBOARD.restBase ||
    window.location.origin + '/wp-json/bookit/v1/'
  const url = `${restBase}dashboard/reports/revenue/export?date_from=${dateFrom.value}&date_to=${dateTo.value}`
  window.open(url, '_blank')
}

onMounted(() => {
  applyDefaultDates()
  fetchReport()
})
</script>
