<template>
  <div class="max-w-4xl mx-auto space-y-6">
    <div v-if="!isAdmin" class="bg-amber-50 border border-amber-200 rounded p-4">
      <p class="text-sm text-amber-800">
        Only administrators can view installed extensions.
      </p>
    </div>

    <div v-else class="bg-white rounded-lg shadow-sm border border-gray-200">
      <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
        <h2 class="text-lg font-semibold text-gray-900">Extensions</h2>
        <p class="text-sm text-gray-500 mt-1">
          Active extension modules integrated with Bookit core
        </p>
      </div>

      <div class="px-4 sm:px-6 py-6">
        <p v-if="loading" class="text-sm text-gray-500">Loading extensions...</p>

        <div v-else-if="error" class="bg-red-50 border border-red-200 rounded p-3">
          <p class="text-sm text-red-800">{{ error }}</p>
        </div>

        <div v-else-if="extensions.length === 0" class="text-sm text-gray-600 text-center border border-dashed border-gray-300 rounded-lg p-6">
          No extensions installed. Extensions add features like recurring appointments and group bookings.
        </div>

        <div v-else class="space-y-4">
          <div
            v-for="extension in extensions"
            :key="extension.slug"
            class="border border-gray-200 rounded-lg p-4"
          >
            <div class="flex items-start justify-between gap-4">
              <div>
                <p class="font-semibold text-gray-900">{{ extension.name }}</p>
                <p class="text-sm text-gray-600 mt-1">Version: {{ extension.version }}</p>
                <p v-if="extension.description" class="text-sm text-gray-600 mt-1">
                  {{ extension.description }}
                </p>
                <p v-if="extension.author" class="text-sm text-gray-600 mt-1">
                  Author: {{ extension.author }}
                </p>
              </div>

              <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-green-50 text-green-700 border border-green-200">
                Compatible
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useApi } from '../composables/useApi'

const api = useApi()
const staff = window.BOOKIT_DASHBOARD?.staff || {}
const isAdmin = computed(() => staff.role === 'admin' || staff.role === 'bookit_admin')

const loading = ref(true)
const error = ref('')
const extensions = ref([])

const loadExtensions = async () => {
  if (!isAdmin.value) {
    loading.value = false
    return
  }

  loading.value = true
  error.value = ''

  try {
    const response = await api.get(`${window.BOOKIT_DASHBOARD.restBase}extensions`)
    extensions.value = Array.isArray(response.data?.extensions) ? response.data.extensions : []
  } catch (err) {
    error.value = err.message || 'Failed to load extensions.'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadExtensions()
})
</script>
