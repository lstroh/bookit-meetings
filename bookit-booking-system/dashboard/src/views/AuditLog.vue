<template>
  <div>
    <div class="mb-6">
      <h2 class="text-lg font-semibold text-gray-900">Audit Log</h2>
      <p class="text-sm text-gray-600 mt-1">Read-only history of significant system actions</p>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6 p-4 space-y-4">
      <DateRangeSelector
        :model-from="draftFilters.date_from"
        :model-to="draftFilters.date_to"
        :active-filter="activeDateFilter"
        @change="handleDateRangeChange"
        @update:activeFilter="activeDateFilter = $event"
      />

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="md:col-span-2">
          <label for="audit-action-filter" class="block text-sm font-medium text-gray-700 mb-1">Action</label>
          <select
            id="audit-action-filter"
            v-model="draftFilters.action"
            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
          >
            <option value="">All actions</option>
            <option v-for="action in commonActions" :key="action" :value="action">
              {{ action }}
            </option>
          </select>
        </div>

        <div class="flex items-end gap-2">
          <button
            type="button"
            class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed text-sm font-medium"
            :disabled="loading"
            @click="applyFilters"
          >
            Filter
          </button>
          <button
            type="button"
            class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed text-sm font-medium"
            :disabled="loading"
            @click="clearFilters"
          >
            Clear
          </button>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
      <div v-if="loading" class="p-8 flex flex-col items-center justify-center gap-3 text-sm text-gray-600">
        <div class="h-6 w-6 border-2 border-gray-200 border-t-primary-600 rounded-full animate-spin"></div>
        <p>Loading audit log entries...</p>
      </div>

      <div v-else-if="entries.length === 0" class="p-8 text-sm text-gray-600 text-center">
        No audit log entries found for the selected filters.
      </div>

      <div v-else>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Timestamp</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actor</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Object</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP Address</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="entry in entries" :key="entry.id" class="hover:bg-gray-50">
                <td class="px-4 py-3 text-sm text-gray-900 whitespace-nowrap">{{ formatTimestamp(entry.created_at) }}</td>
                <td class="px-4 py-3 text-sm text-gray-900">
                  <div class="flex items-center gap-2">
                    <span>{{ entry.actor_name }}</span>
                    <span class="px-2 py-0.5 text-xs rounded-full font-medium" :class="getActorBadgeClass(entry.actor_type)">
                      {{ entry.actor_type }}
                    </span>
                  </div>
                </td>
                <td
                  class="px-4 py-3 text-sm text-gray-900 max-w-[220px] sm:max-w-[280px] truncate"
                  :title="entry.action"
                >
                  {{ entry.action }}
                </td>
                <td class="px-4 py-3 text-sm text-gray-900">
                  <span class="font-medium">{{ entry.object_type }}</span>
                  <span class="text-gray-600 ml-1">{{ entry.object_summary || '' }}</span>
                </td>
                <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">{{ entry.actor_ip || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        
        <nav
          v-if="pagination.total_pages > 1"
          class="bg-gray-50 px-4 py-3 border-t border-gray-200"
          aria-label="Audit log pagination"
        >
          <div class="flex items-center justify-between">
            <div class="text-sm text-gray-700">
              Page {{ pagination.current_page }} of {{ pagination.total_pages }} ({{ pagination.total }} total)
            </div>
            <div class="flex items-center gap-2">
              <button
                type="button"
                class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                :disabled="pagination.current_page <= 1"
                @click="changePage(pagination.current_page - 1)"
              >
                Previous
              </button>
              <span class="text-sm text-gray-700">{{ pagination.current_page }}</span>
              <button
                type="button"
                class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                :disabled="pagination.current_page >= pagination.total_pages"
                @click="changePage(pagination.current_page + 1)"
              >
                Next
              </button>
            </div>
          </div>
        </nav>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useApi } from '../composables/useApi'
import DateRangeSelector from '../components/DateRangeSelector.vue'

const api = useApi()

const commonActions = [
  'booking.created',
  'booking.updated',
  'booking.cancelled',
  'booking.completed',
  'booking.no_show',
  'payment.completed',
  'staff.created',
  'staff.updated',
  'staff.deleted',
  'setting.updated',
  'customer.anonymised',
  'audit_log.viewed'
]

const loading = ref(false)
const entries = ref([])
const activeDateFilter = ref('custom')

const pagination = ref({
  total: 0,
  per_page: 10,
  current_page: 1,
  total_pages: 1
})

const draftFilters = ref({
  date_from: '',
  date_to: '',
  action: ''
})

const appliedFilters = ref({
  date_from: '',
  date_to: '',
  action: ''
})

function handleDateRangeChange({ from, to }) {
  draftFilters.value.date_from = from || ''
  draftFilters.value.date_to = to || ''
  applyFilters()
}

function getActorBadgeClass(actorType) {
  const badgeMap = {
    admin: 'bg-blue-100 text-blue-800',
    staff: 'bg-green-100 text-green-800',
    customer: 'bg-purple-100 text-purple-800',
    system: 'bg-gray-100 text-gray-700'
  }

  return badgeMap[actorType] || badgeMap.system
}

function formatTimestamp(value) {
  if (!value) return '-'
  const date = new Date(value.replace(' ', 'T'))
  return date.toLocaleString('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

async function fetchAuditLog(page = 1) {
  loading.value = true

  try {
    const params = new URLSearchParams({
      page: String(page),
      per_page: '10'
    })

    if (appliedFilters.value.date_from) params.append('date_from', appliedFilters.value.date_from)
    if (appliedFilters.value.date_to) params.append('date_to', appliedFilters.value.date_to)
    if (appliedFilters.value.action) params.append('action', appliedFilters.value.action)

    const response = await api.get(`${window.BOOKIT_DASHBOARD.restBase}audit-log?${params.toString()}`)
    entries.value = response.data?.data || []

    pagination.value = {
      total: response.data?.pagination?.total || 0,
      per_page: response.data?.pagination?.per_page || 10,
      current_page: response.data?.pagination?.current_page || 1,
      total_pages: response.data?.pagination?.total_pages || 1
    }
  } catch {
    entries.value = []
    pagination.value = {
      total: 0,
      per_page: 10,
      current_page: 1,
      total_pages: 1
    }
  } finally {
    loading.value = false
  }
}

function applyFilters() {
  appliedFilters.value = {
    date_from: draftFilters.value.date_from,
    date_to: draftFilters.value.date_to,
    action: draftFilters.value.action
  }

  fetchAuditLog(1)
}

function clearFilters() {
  draftFilters.value = {
    date_from: '',
    date_to: '',
    action: ''
  }
  appliedFilters.value = {
    date_from: '',
    date_to: '',
    action: ''
  }
  activeDateFilter.value = 'custom'
  fetchAuditLog(1)
}

function changePage(page) {
  if (page < 1 || page > pagination.value.total_pages) return
  fetchAuditLog(page)
}

onMounted(() => {
  fetchAuditLog(1)
})
</script>
