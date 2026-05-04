<template>
  <div>
    <div class="mb-6">
      <h2 class="text-lg font-semibold text-gray-900">Booking Analytics</h2>
      <p class="text-sm text-gray-600 mt-1">Understand your booking patterns</p>
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

    <div v-if="loading">
      <div class="grid grid-cols-2 lg:grid-cols-6 gap-4 mb-6">
        <CardSkeleton v-for="i in 6" :key="i" />
      </div>
      <div class="bg-white rounded-lg border border-gray-200 p-4 mb-6">
        <div class="h-4 w-40 bg-gray-200 rounded animate-pulse mb-4"></div>
        <div class="h-[260px] bg-gray-100 rounded animate-pulse"></div>
      </div>
      <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-6">
        <div class="bg-white rounded-lg border border-gray-200 p-4">
          <div class="h-4 w-32 bg-gray-200 rounded animate-pulse mb-4"></div>
          <div class="h-[240px] bg-gray-100 rounded animate-pulse"></div>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-4">
          <div class="h-4 w-32 bg-gray-200 rounded animate-pulse mb-4"></div>
          <div class="h-[360px] bg-gray-100 rounded animate-pulse"></div>
        </div>
      </div>
      <div class="bg-white rounded-lg border border-gray-200 p-4 mb-6">
        <div class="h-4 w-48 bg-gray-200 rounded animate-pulse mb-4"></div>
        <div class="h-[320px] bg-gray-100 rounded animate-pulse"></div>
      </div>
    </div>

    <ErrorState
      v-else-if="error"
      title="Failed to load booking analytics"
      :message="errorMessage"
      :details="errorDetails"
      :show-home="false"
      @retry="fetchAnalytics"
    />

    <div v-else-if="analyticsData">
      <div class="grid grid-cols-2 lg:grid-cols-6 gap-4 mb-6">
        <div class="bg-white rounded-lg border border-gray-200 p-4">
          <p class="text-sm font-medium text-gray-500 mb-1">Total Bookings</p>
          <p class="text-2xl font-semibold text-gray-900">{{ summary.total_bookings }}</p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-4">
          <p class="text-sm font-medium text-gray-500 mb-1">Completed</p>
          <p class="text-2xl font-semibold text-green-600">{{ summary.completed }}</p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-4">
          <p class="text-sm font-medium text-gray-500 mb-1">Cancelled</p>
          <p class="text-2xl font-semibold" :class="summary.cancelled > 0 ? 'text-red-600' : 'text-gray-900'">
            {{ summary.cancelled }}
          </p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-4">
          <p class="text-sm font-medium text-gray-500 mb-1">No-Shows</p>
          <p class="text-2xl font-semibold" :class="summary.no_show > 0 ? 'text-amber-600' : 'text-gray-900'">
            {{ summary.no_show }}
          </p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-4">
          <p class="text-sm font-medium text-gray-500 mb-1">Completion Rate</p>
          <p class="text-2xl font-semibold" :class="completionRateClass">
            {{ formatPercent(summary.completion_rate) }}
          </p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-4">
          <p class="text-sm font-medium text-gray-500 mb-1">Avg Lead Time</p>
          <p class="text-2xl font-semibold text-gray-900">{{ formatDays(summary.avg_lead_days) }}</p>
        </div>
      </div>

      <div class="space-y-3 mb-6">
        <div
          v-if="summary.cancellation_rate > 10"
          class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800"
        >
          ⚠️ High cancellation rate ({{ formatPercent(summary.cancellation_rate) }}). Consider reviewing your cancellation policy.
        </div>
        <div
          v-if="summary.no_show_rate > 10"
          class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800"
        >
          ⚠️ High no-show rate ({{ formatPercent(summary.no_show_rate) }}). Consider sending reminder notifications.
        </div>
        <div
          v-if="summary.total_bookings < 10"
          class="rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800"
        >
          ℹ️ Not enough data for meaningful analysis. Try a longer date range.
        </div>
      </div>

      <div class="bg-white rounded-lg border border-gray-200 p-4 mb-6">
        <h3 class="text-base font-semibold text-gray-900 mb-4">Bookings Over Time</h3>
        <div
          v-if="summary.total_bookings < 10"
          class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600"
        >
          Not enough data to show trend yet.
        </div>
        <div v-else style="height: 260px; position: relative;">
          <Bar :data="bookingsOverTimeData" :options="bookingsOverTimeOptions" />
        </div>
      </div>

      <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-6">
        <div class="bg-white rounded-lg border border-gray-200 p-4">
          <h3 class="text-base font-semibold text-gray-900 mb-4">Popular Days</h3>
          <div style="height: 240px; position: relative;">
            <Bar :data="popularDaysChartData" :options="horizontalBarOptions" />
          </div>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-4">
          <h3 class="text-base font-semibold text-gray-900 mb-4">Popular Times</h3>
          <div style="height: 360px; position: relative;">
            <Bar :data="popularTimesChartData" :options="horizontalBarOptions" />
          </div>
        </div>
      </div>

      <div class="bg-white rounded-lg border border-gray-200 p-4 mb-6">
        <h3 class="text-base font-semibold text-gray-900">Peak Hours Heatmap</h3>
        <p class="text-sm text-gray-600 mt-1 mb-4">Booking volume by day and time — darker = busier</p>

        <div class="overflow-x-auto">
          <div class="min-w-max">
            <div class="flex">
              <div class="w-12 shrink-0"></div>
              <div
                v-for="hour in hours"
                :key="hour"
                class="w-10 text-center text-xs text-gray-400 pb-1"
              >
                {{ hour.slice(0, 5) }}
              </div>
            </div>
            <div v-for="day in days" :key="day" class="flex items-center mb-1">
              <div class="w-12 shrink-0 text-xs text-gray-500 font-medium">{{ day }}</div>
              <div
                v-for="hour in hours"
                :key="hour"
                class="w-10 h-8 mx-0.5 rounded cursor-default transition-colors"
                :class="getHeatmapClass(getHeatmapCount(day, hour))"
                :title="`${day} ${hour}: ${getHeatmapCount(day, hour)} bookings`"
              ></div>
            </div>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-lg border border-gray-200 p-4">
        <h3 class="text-base font-semibold text-gray-900">Booking Lead Time</h3>
        <p class="text-sm text-gray-600 mt-1 mb-4">
          How far in advance customers book — avg {{ formatDays(leadTimeAvgDays) }}
        </p>
        <div style="height: 220px; position: relative;">
          <Bar :data="leadTimeChartData" :options="leadTimeOptions" />
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
  PointElement,
  LineElement,
  BarElement,
  Title,
  Tooltip,
  Legend
} from 'chart.js'
import { useApi } from '../composables/useApi'
import CardSkeleton from '../components/CardSkeleton.vue'
import ErrorState from '../components/ErrorState.vue'
import DateRangeSelector from '../components/DateRangeSelector.vue'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, BarElement, Title, Tooltip, Legend)

const api = useApi()

const loading = ref(true)
const error = ref(false)
const errorMessage = ref('')
const errorDetails = ref('')
const analyticsData = ref(null)

const dateFrom = ref('')
const dateTo = ref('')
const activeFilter = ref('this_month')

const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
const hours = ['07:00', '08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00', '21:00']

function toLocalDateString(date) {
  return new Intl.DateTimeFormat('en-CA', { timeZone: 'Europe/London' }).format(date)
}

function applyDefaultDates() {
  const now = new Date()
  const from = new Date(now)
  from.setDate(from.getDate() - 30)
  dateFrom.value = toLocalDateString(from)
  dateTo.value = toLocalDateString(now)
}

const summary = computed(() => analyticsData.value?.summary || {
  total_bookings: 0,
  completed: 0,
  cancelled: 0,
  no_show: 0,
  completion_rate: 0,
  cancellation_rate: 0,
  no_show_rate: 0,
  avg_lead_days: 0
})

const completionRateClass = computed(() => {
  const rate = Number(summary.value.completion_rate || 0)
  if (rate >= 70) return 'text-green-600'
  if (rate >= 50) return 'text-amber-600'
  return 'text-red-600'
})

const bookingsOverTimeData = computed(() => {
  const rows = analyticsData.value?.daily_trend || []
  return {
    labels: rows.map((row) => formatChartDate(row.date)),
    datasets: [
      {
        label: 'Bookings',
        data: rows.map((row) => Number(row.count || 0)),
        borderColor: '#6366F1',
        backgroundColor: '#6366F1'
      }
    ]
  }
})

const bookingsOverTimeOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false }
  },
  scales: {
    y: {
      beginAtZero: true,
      ticks: { precision: 0 }
    }
  }
}

const horizontalBarOptions = {
  responsive: true,
  maintainAspectRatio: false,
  indexAxis: 'y',
  plugins: {
    legend: { display: false }
  },
  scales: {
    x: { beginAtZero: true, ticks: { precision: 0 } }
  }
}

const popularDaysChartData = computed(() => ({
  labels: analyticsData.value?.by_day_of_week?.labels || days,
  datasets: [
    {
      label: 'Bookings',
      data: analyticsData.value?.by_day_of_week?.data || Array(7).fill(0),
      borderColor: '#3B82F6',
      backgroundColor: '#3B82F6'
    }
  ]
}))

const popularTimesChartData = computed(() => ({
  labels: analyticsData.value?.by_hour?.labels || hours,
  datasets: [
    {
      label: 'Bookings',
      data: analyticsData.value?.by_hour?.data || Array(15).fill(0),
      borderColor: '#8B5CF6',
      backgroundColor: '#8B5CF6'
    }
  ]
}))

const leadTimeAvgDays = computed(() => Number(analyticsData.value?.lead_time?.avg_days || 0))

const leadTimeChartData = computed(() => {
  const buckets = analyticsData.value?.lead_time?.buckets || {}
  return {
    labels: ['Same Day', '1-3 Days', '4-7 Days', '8-14 Days', '15+ Days'],
    datasets: [
      {
        label: 'Bookings',
        data: [
          Number(buckets.same_day || 0),
          Number(buckets.one_to_three || 0),
          Number(buckets.four_to_seven || 0),
          Number(buckets.eight_to_fourteen || 0),
          Number(buckets.fifteen_plus || 0)
        ],
        borderColor: '#10B981',
        backgroundColor: '#10B981'
      }
    ]
  }
})

const leadTimeOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false }
  },
  scales: {
    y: {
      beginAtZero: true,
      ticks: { precision: 0 }
    }
  }
}

const maxCount = computed(() => {
  const cells = analyticsData.value?.heatmap || []
  const counts = cells.map((cell) => Number(cell.count || 0))
  return Math.max(1, ...counts)
})

function getHeatmapCount(day, hour) {
  const cell = analyticsData.value?.heatmap?.find((entry) => entry.day === day && entry.hour === hour)
  return cell ? Number(cell.count || 0) : 0
}

function getHeatmapClass(count) {
  if (count === 0) return 'bg-gray-100'
  const ratio = count / maxCount.value
  if (ratio < 0.2) return 'bg-blue-100'
  if (ratio < 0.4) return 'bg-blue-200'
  if (ratio < 0.6) return 'bg-blue-400'
  if (ratio < 0.8) return 'bg-blue-600'
  return 'bg-blue-800'
}

function formatPercent(value) {
  return `${Number(value || 0).toFixed(1)}%`
}

function formatDays(value) {
  return `${Number(value || 0).toFixed(1)} days`
}

function formatChartDate(dateStr) {
  const [year, month, day] = String(dateStr).split('-')
  if (!year || !month || !day) return dateStr
  return `${day}/${month}`
}

async function fetchAnalytics() {
  loading.value = true
  error.value = false
  errorMessage.value = ''
  errorDetails.value = ''

  try {
    const response = await api.get(`/reports/analytics?date_from=${dateFrom.value}&date_to=${dateTo.value}`)
    if (response.data?.success) {
      analyticsData.value = response.data
    } else {
      throw new Error(response.data?.message || 'Failed to load booking analytics')
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
  fetchAnalytics()
}

onMounted(() => {
  applyDefaultDates()
  fetchAnalytics()
})
</script>
