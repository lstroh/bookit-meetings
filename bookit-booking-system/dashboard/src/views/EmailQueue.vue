<template>
  <div>
    <div class="mb-6 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
      <div>
        <h2 class="text-lg font-semibold text-gray-900">Email Queue</h2>
        <p class="text-sm text-gray-600 mt-1">Read-only log of outbound notification emails</p>
      </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6 p-4">
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label for="email-queue-status-filter" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
          <select
            id="email-queue-status-filter"
            v-model="filters.status"
            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            @change="loadEmailQueue(1)"
          >
            <option value="">All</option>
            <option value="pending">Pending</option>
            <option value="processing">Processing</option>
            <option value="sent">Sent</option>
            <option value="failed">Failed</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>
        <div>
          <label for="email-queue-per-page" class="block text-sm font-medium text-gray-700 mb-1">Per page</label>
          <select
            id="email-queue-per-page"
            v-model.number="filters.per_page"
            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            @change="loadEmailQueue(1)"
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
        <span role="status" aria-live="polite" class="sr-only">Loading email queue...</span>
        <TableSkeleton :rows="8" :columns="7" />
      </div>

      <ErrorState
        v-else-if="error"
        title="Failed to load email queue"
        :message="errorMessage"
        :show-home="false"
        @retry="loadEmailQueue(pagination.current_page)"
      />

      <div v-else-if="items.length === 0" class="p-8 text-center text-sm text-gray-600">
        <p>No email queue items found.</p>
        <p class="mt-1 text-xs text-gray-500">Try changing the status filter or check back later.</p>
      </div>

      <div v-else>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recipient</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Attempts</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Scheduled</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sent</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Error</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="row in items" :key="row.id" class="hover:bg-gray-50 transition-colors">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ row.email_type }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ row.recipient_email }}</td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span
                    class="px-2 py-1 text-xs font-medium rounded-full"
                    :class="statusBadgeClass(row.status)"
                  >
                    {{ formatStatusLabel(row.status) }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                  {{ Number(row.attempts || 0) }} / {{ Number(row.max_attempts || 0) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ formatDateTime(row.scheduled_at) }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ row.sent_at ? formatDateTime(row.sent_at) : '—' }}</td>
                <td
                  class="px-6 py-4 text-sm text-gray-700 max-w-xs truncate"
                  :title="row.last_error || ''"
                >
                  {{ truncateError(row.last_error) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <nav class="bg-gray-50 px-4 sm:px-6 py-4 border-t border-gray-200" aria-label="Email queue pagination">
          <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
            <div class="text-sm text-gray-700">
              Showing
              <span class="font-medium">{{ resultsStart }}</span>
              to
              <span class="font-medium">{{ resultsEnd }}</span>
              of
              <span class="font-medium">{{ pagination.total }}</span>
              items
            </div>

            <div class="flex items-center gap-1 sm:gap-2">
              <button
                type="button"
                @click="goToPage(1)"
                :disabled="pagination.current_page <= 1"
                class="hidden sm:block px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                &laquo; First
              </button>
              <button
                type="button"
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
                  type="button"
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
                type="button"
                @click="goToPage(pagination.current_page + 1)"
                :disabled="pagination.current_page >= pagination.total_pages"
                class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Next &rsaquo;
              </button>
              <button
                type="button"
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
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useApi } from '../composables/useApi'
import ErrorState from '../components/ErrorState.vue'
import TableSkeleton from '../components/TableSkeleton.vue'

const router = useRouter()
const api = useApi()
const currentUser = window.BOOKIT_DASHBOARD?.staff || {}

const isAdmin = computed(() => currentUser.role === 'admin' || currentUser.role === 'bookit_admin')

const loading = ref(true)
const error = ref(false)
const errorMessage = ref('')
const items = ref([])
const filters = ref({ status: '', per_page: 25 })
const pagination = ref({ total: 0, per_page: 25, current_page: 1, total_pages: 1 })

const resultsStart = computed(() => {
  if (pagination.value.total === 0) return 0
  return ((pagination.value.current_page - 1) * pagination.value.per_page) + 1
})

const resultsEnd = computed(() => {
  if (pagination.value.total === 0) return 0
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

function formatStatusLabel(status) {
  if (!status) return 'Unknown'
  return status.charAt(0).toUpperCase() + status.slice(1)
}

function statusBadgeClass(status) {
  const map = {
    pending: 'bg-gray-100 text-gray-700',
    processing: 'bg-blue-100 text-blue-700',
    sent: 'bg-green-100 text-green-700',
    failed: 'bg-red-100 text-red-700',
    cancelled: 'bg-gray-100 text-gray-700'
  }
  return map[status] || 'bg-gray-100 text-gray-700'
}

function formatDateTime(value) {
  if (!value) return '—'
  const normalized = String(value).includes(' ') ? String(value).replace(' ', 'T') : `${value}T00:00:00`
  const date = new Date(normalized)
  if (Number.isNaN(date.getTime())) return 'Invalid date'
  return date.toLocaleString('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

function truncateError(value) {
  if (!value) return '—'
  const s = String(value)
  if (s.length <= 60) return s
  return `${s.slice(0, 60)}…`
}

async function loadEmailQueue(page = 1) {
  loading.value = true
  error.value = false
  errorMessage.value = ''

  try {
    const params = new URLSearchParams({
      page: String(page),
      per_page: String(filters.value.per_page)
    })
    if (filters.value.status) params.append('status', filters.value.status)

    const response = await api.get(`/email-queue?${params.toString()}`)
    const data = response.data || {}

    items.value = Array.isArray(data.items) ? data.items : []
    const total = Number(data.total || 0)
    const rawPages = Number(data.pages ?? 0)
    const totalPages = total === 0 ? 1 : Math.max(1, rawPages)

    pagination.value = {
      total,
      per_page: filters.value.per_page,
      current_page: Number(data.page || page),
      total_pages: totalPages
    }
  } catch (err) {
    error.value = true
    errorMessage.value = err.message || 'An unexpected error occurred.'
    items.value = []
  } finally {
    loading.value = false
  }
}

async function goToPage(page) {
  if (page < 1 || page > pagination.value.total_pages) return
  await loadEmailQueue(page)
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

onMounted(() => {
  if (!isAdmin.value) {
    router.push('/')
    return
  }
  loadEmailQueue(1)
})
</script>
