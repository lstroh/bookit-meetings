<template>
  <div>
    <div class="mb-6">
      <h2 class="text-lg font-semibold text-gray-900">Reports</h2>
      <p class="text-sm text-gray-600 mt-1">Business performance overview</p>
    </div>

    <div class="inline-flex bg-gray-100 rounded-lg p-1 mb-6">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        type="button"
        class="px-4 py-2 text-sm font-medium rounded-md transition-colors"
        :class="activeTab === tab.key ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900'"
        @click="activeTab = tab.key"
      >
        {{ tab.label }}
      </button>
    </div>

    <div v-if="loading">
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <CardSkeleton v-for="i in 4" :key="i" />
      </div>
      <div class="bg-white rounded-lg border border-gray-200 p-4">
        <div class="h-4 w-40 bg-gray-200 rounded animate-pulse mb-2"></div>
        <div class="h-3 w-24 bg-gray-100 rounded animate-pulse mb-4"></div>
        <div class="h-[280px] bg-gray-100 rounded animate-pulse"></div>
      </div>
    </div>

    <ErrorState
      v-else-if="error"
      title="Failed to load reports"
      :message="errorMessage"
      :details="errorDetails"
      :show-home="false"
      @retry="loadOverview"
    />

    <EmptyState
      v-else-if="!currentMetrics"
      icon="📊"
      title="No report data"
      description="No metrics are available for this period yet."
    />

    <div v-else>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg border border-gray-200 p-4">
          <div class="flex items-center justify-between mb-2">
            <p class="text-sm font-medium text-gray-500">Total Bookings</p>
            <span class="text-xl">📋</span>
          </div>
          <p class="text-2xl font-semibold text-gray-900">{{ currentMetrics.total_bookings }}</p>
          <p class="text-xs text-gray-500 mt-1">Excluding cancellations</p>
        </div>

        <div class="bg-white rounded-lg border border-gray-200 p-4">
          <div class="flex items-center justify-between mb-2">
            <p class="text-sm font-medium text-gray-500">Total Revenue</p>
            <span class="text-xl">💷</span>
          </div>
          <p class="text-2xl font-semibold text-gray-900">£{{ formatCurrency(currentMetrics.total_revenue) }}</p>
          <p class="text-xs text-gray-500 mt-1">From completed payments</p>
        </div>

        <div class="bg-white rounded-lg border border-gray-200 p-4">
          <div class="flex items-center justify-between mb-2">
            <p class="text-sm font-medium text-gray-500">No-Show Rate</p>
            <span class="text-xl">⚠️</span>
          </div>
          <p class="text-2xl font-semibold" :class="noShowRateClass">
            {{ formatRate(currentMetrics.no_show_rate) }}
          </p>
          <p class="text-xs text-gray-500 mt-1">Of completed appointments</p>
        </div>

        <div class="bg-white rounded-lg border border-gray-200 p-4">
          <div class="flex items-center justify-between mb-2">
            <p class="text-sm font-medium text-gray-500">Cancellation Rate</p>
            <span class="text-xl">❌</span>
          </div>
          <p class="text-2xl font-semibold" :class="cancellationRateClass">
            {{ formatRate(currentMetrics.cancellation_rate) }}
          </p>
          <p class="text-xs text-gray-500 mt-1">Of all bookings</p>
        </div>
      </div>

      <div v-if="activeTab !== 'all_time'" class="bg-white rounded-lg border border-gray-200 p-4">
        <h3 class="text-base font-semibold text-gray-900">Revenue Trend</h3>
        <p class="text-sm text-gray-500 mb-4">{{ activeTab === 'this_week' ? 'Daily' : 'Weekly' }}</p>
        <div style="height: 280px; position: relative;">
          <Bar v-if="chartData" :data="chartData" :options="chartOptions" />
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

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend)

const api = useApi()

const tabs = [
  { key: 'this_week', label: 'This Week' },
  { key: 'this_month', label: 'This Month' },
  { key: 'all_time', label: 'All Time' }
]

const loading = ref(true)
const error = ref(false)
const errorMessage = ref('')
const errorDetails = ref('')
const activeTab = ref('this_week')
const overviewData = ref(null)

const currentMetrics = computed(() => {
  return overviewData.value?.data?.[activeTab.value] || null
})

const noShowRateClass = computed(() => {
  const rate = Number(currentMetrics.value?.no_show_rate || 0)
  if (rate > 10) return 'text-red-600'
  if (rate >= 5) return 'text-amber-600'
  return 'text-green-600'
})

const cancellationRateClass = computed(() => {
  const rate = Number(currentMetrics.value?.cancellation_rate || 0)
  if (rate > 20) return 'text-red-600'
  if (rate >= 10) return 'text-amber-600'
  return 'text-green-600'
})

const chartData = computed(() => {
  if (activeTab.value === 'all_time') {
    return null
  }

  const trend = overviewData.value?.data?.revenue_trend?.[activeTab.value] || []
  const labels = trend.map((item) => (
    activeTab.value === 'this_week' ? formatChartDate(item.date) : item.week_label
  ))
  const revenues = trend.map((item) => Number(item.revenue || 0))

  return {
    labels,
    datasets: [
      {
        label: 'Revenue (£)',
        data: revenues,
        backgroundColor: '#3B82F6'
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

const formatCurrency = (value) => {
  return Number(value || 0).toLocaleString('en-GB', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  })
}

const formatRate = (value) => {
  return `${Number(value || 0).toFixed(1)}%`
}

const formatChartDate = (dateStr) => {
  const [year, month, day] = String(dateStr).split('-')
  if (!year || !month || !day) return dateStr
  return `${day}/${month}`
}

const loadOverview = async () => {
  loading.value = true
  error.value = false
  errorMessage.value = ''
  errorDetails.value = ''

  try {
    const response = await api.get('/reports/overview')
    if (response.data?.success) {
      overviewData.value = response.data
    } else {
      throw new Error(response.data?.message || 'Failed to load overview report')
    }
  } catch (err) {
    error.value = true
    errorMessage.value = err.message || 'An unexpected error occurred.'
    errorDetails.value = `Status: ${err.status || 'N/A'}`
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadOverview()
})
</script>
