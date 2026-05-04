<template>
  <div class="max-w-6xl mx-auto">
    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
      <p class="mt-2 text-sm text-gray-600">Loading templates...</p>
    </div>

    <div v-else class="space-y-6">
      <!-- Success/Error Messages -->
      <div v-if="saveSuccess" class="bg-green-50 border border-green-200 rounded p-3">
        <p class="text-sm text-green-800">&#10003; {{ saveSuccess }}</p>
      </div>
      <div v-if="saveError" class="bg-red-50 border border-red-200 rounded p-3">
        <p class="text-sm text-red-800">{{ saveError }}</p>
      </div>

      <!-- Available Variables Info -->
      <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div class="flex items-start gap-3">
          <span class="text-blue-600 text-xl flex-shrink-0">&#8505;&#65039;</span>
          <div class="flex-1">
            <p class="text-sm font-medium text-blue-900 mb-2">
              Available Template Variables
            </p>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-xs text-blue-800">
              <div>
                <span class="font-semibold">Customer:</span><br />
                {customer_name}<br />
                {customer_email}<br />
                {customer_phone}
              </div>
              <div>
                <span class="font-semibold">Booking:</span><br />
                {service_name}<br />
                {date}<br />
                {time}<br />
                {duration}
              </div>
              <div>
                <span class="font-semibold">Staff:</span><br />
                {staff_name}
              </div>
              <div>
                <span class="font-semibold">Business:</span><br />
                {business_name}<br />
                {business_phone}<br />
                {business_address}
              </div>
            </div>
            <p class="text-xs text-blue-700 mt-2">
              Use these variables in your templates - they will be replaced with real data when emails are sent.
              In the editor, click a variable to insert it at the cursor, or click the clipboard icon to copy.
            </p>
          </div>
        </div>
      </div>

      <!-- Template Cards -->
      <div
        v-for="template in templates"
        :key="template.template_key"
        class="bg-white rounded-lg shadow-sm border border-gray-200"
      >
        <!-- Card Header -->
        <div class="px-6 py-4 border-b border-gray-200">
          <div class="flex items-center justify-between">
            <div class="flex-1">
              <div class="flex items-center gap-3">
                <h2 class="text-lg font-semibold text-gray-900">
                  {{ getTemplateTitle(template.template_key) }}
                </h2>
                <span
                  class="inline-flex items-center px-2.5 py-0.5 text-xs font-medium rounded-full"
                  :class="template.enabled
                    ? 'bg-green-100 text-green-800'
                    : 'bg-gray-100 text-gray-800'"
                >
                  {{ template.enabled ? 'Enabled' : 'Disabled' }}
                </span>
              </div>
              <p class="text-sm text-gray-500 mt-1">
                {{ getTemplateDescription(template.template_key) }}
              </p>
            </div>

            <div class="flex items-center gap-2">
              <!-- Enable/Disable Toggle -->
              <label class="relative inline-flex items-center cursor-pointer">
                <input
                  type="checkbox"
                  :checked="template.enabled"
                  @change="toggleEnabled(template)"
                  class="sr-only peer"
                />
                <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
              </label>

              <button
                @click="editTemplate(template)"
                class="px-3 py-1.5 text-sm font-medium text-primary-700 hover:bg-primary-50 rounded-lg"
              >
                Edit
              </button>

              <button
                @click="resetTemplate(template)"
                class="px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg"
              >
                Reset
              </button>
            </div>
          </div>
        </div>

        <!-- Preview (Collapsed by Default) -->
        <div
          v-if="expandedTemplate === template.template_key"
          class="px-6 py-4 bg-gray-50 border-b border-gray-200"
        >
          <p class="text-xs font-semibold text-gray-700 uppercase mb-2">Preview</p>
          <div class="bg-white border border-gray-200 rounded-lg p-4">
            <p class="text-sm font-semibold text-gray-900 mb-2">
              Subject: {{ template.subject }}
            </p>
            <div class="text-sm text-gray-700 whitespace-pre-wrap">{{ template.body }}</div>
          </div>
          <button
            @click="expandedTemplate = null"
            class="mt-2 text-xs text-primary-600 hover:text-primary-700"
          >
            Hide Preview
          </button>
        </div>
        <div v-else class="px-6 py-3 bg-gray-50">
          <button
            @click="expandedTemplate = template.template_key"
            class="text-xs text-primary-600 hover:text-primary-700"
          >
            Show Preview
          </button>
        </div>
      </div>
    </div>

    <!-- Edit Template Modal -->
    <div
      v-if="editingTemplate"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
      @click.self="closeEditModal"
      @keydown.escape="closeEditModal"
    >
      <div class="bg-white rounded-lg shadow-xl max-w-3xl w-full max-h-[90vh] overflow-hidden">
        <!-- Modal Header -->
        <div class="px-6 py-4 border-b border-gray-200">
          <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900">
              Edit: {{ getTemplateTitle(editingTemplate.template_key) }}
            </h3>
            <button
              @click="closeEditModal"
              class="text-gray-400 hover:text-gray-600"
            >
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>

        <!-- Modal Body -->
        <form @submit.prevent="saveTemplate" class="px-6 py-4 overflow-y-auto max-h-[calc(90vh-140px)]">
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Email Subject *
            </label>
            <input
              v-model="editForm.subject"
              type="text"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            />
          </div>

          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Email Body *
            </label>
            <textarea
              v-model="editForm.body"
              rows="12"
              required
              class="template-body-editor w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 font-mono text-sm"
            ></textarea>
            <p class="text-xs text-gray-500 mt-1">
              Use variables like {customer_name}, {service_name}, {date}, {time}, etc.
            </p>
          </div>

          <!-- Variables Quick Insert / Copy -->
          <div class="mb-4 bg-gray-50 border border-gray-200 rounded-lg p-3">
            <p class="text-xs font-semibold text-gray-700 mb-2">
              Quick Insert Variables:
              <span class="font-normal text-gray-500">
                (click to insert at cursor, or click clipboard icon to copy)
              </span>
            </p>
            <div class="flex flex-wrap gap-2">
              <div
                v-for="variable in getRelevantVariables(editingTemplate.template_key)"
                :key="variable"
                class="flex items-center gap-1"
              >
                <button
                  type="button"
                  @click="insertVariable(variable)"
                  class="px-2 py-1 text-xs font-mono border rounded transition-all"
                  :class="copiedVariable === variable
                    ? 'bg-green-100 border-green-500 text-green-800'
                    : 'bg-white border-gray-300 hover:bg-blue-50 hover:border-blue-400 text-gray-700'"
                  title="Insert at cursor position"
                >
                  <span v-if="copiedVariable === variable" class="mr-1">&#10003;</span>
                  {{ variable }}
                </button>
                <button
                  type="button"
                  @click.stop="copyToClipboard(variable)"
                  class="px-1.5 py-1 text-xs rounded transition-colors hover:bg-gray-200"
                  :class="copiedVariable === variable
                    ? 'text-green-600'
                    : 'text-gray-500 hover:text-gray-700'"
                  title="Copy to clipboard"
                >&#128203;</button>
              </div>
            </div>
          </div>

          <!-- Live Preview -->
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Preview
            </label>
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
              <p class="text-sm font-semibold text-gray-900 mb-2">
                Subject: {{ editForm.subject }}
              </p>
              <div class="text-sm text-gray-700 whitespace-pre-wrap">{{ editForm.body }}</div>
            </div>
          </div>
        </form>

        <!-- Modal Footer -->
        <div class="px-6 py-4 border-t border-gray-200 flex justify-end gap-3">
          <button
            type="button"
            @click="closeEditModal"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
          >
            Cancel
          </button>
          <button
            @click="saveTemplate"
            :disabled="saving"
            class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 disabled:opacity-50"
          >
            {{ saving ? 'Saving...' : 'Save Template' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useApi } from '../composables/useApi'

const api = useApi()

const loading = ref(false)
const saving = ref(false)
const saveSuccess = ref('')
const saveError = ref('')
const templates = ref([])
const editingTemplate = ref(null)
const expandedTemplate = ref(null)

const editForm = ref({
  subject: '',
  body: '',
  enabled: true
})

const templateInfo = {
  booking_confirmation: {
    title: 'Booking Confirmation',
    description: 'Sent to customers when their booking is confirmed',
    variables: ['{customer_name}', '{service_name}', '{date}', '{time}', '{staff_name}', '{business_name}', '{business_phone}', '{business_address}', '{reschedule_link}', '{cancel_link}']
  },
  booking_reminder: {
    title: 'Booking Reminder',
    description: 'Sent to customers 24 hours before their appointment',
    variables: ['{customer_name}', '{service_name}', '{date}', '{time}', '{staff_name}', '{business_name}', '{business_phone}', '{business_address}', '{reschedule_link}', '{cancel_link}']
  },
  booking_cancelled: {
    title: 'Booking Cancelled',
    description: 'Sent to customers when their booking is cancelled',
    variables: ['{customer_name}', '{service_name}', '{date}', '{time}', '{business_name}', '{business_phone}']
  },
  admin_new_booking: {
    title: 'New Booking (Admin)',
    description: 'Sent to admin when a new booking is created',
    variables: ['{customer_name}', '{customer_email}', '{customer_phone}', '{service_name}', '{date}', '{time}', '{staff_name}', '{duration}', '{total_price}', '{deposit_paid}', '{dashboard_link}']
  },
  staff_new_booking: {
    title: 'New Booking (Staff)',
    description: 'Sent to staff when they are assigned a new booking',
    variables: ['{staff_name}', '{customer_name}', '{customer_phone}', '{service_name}', '{date}', '{time}', '{duration}', '{dashboard_link}']
  }
}

const getTemplateTitle = (key) => templateInfo[key]?.title || key
const getTemplateDescription = (key) => templateInfo[key]?.description || ''
const getRelevantVariables = (key) => templateInfo[key]?.variables || []

const loadTemplates = async () => {
  loading.value = true

  try {
    const response = await api.get('email-templates')

    if (response.data.success) {
      templates.value = response.data.templates
    }
  } catch (err) {
    saveError.value = 'Failed to load templates.'
  } finally {
    loading.value = false
  }
}

const editTemplate = (template) => {
  editingTemplate.value = template
  editForm.value = {
    subject: template.subject,
    body: template.body,
    enabled: template.enabled
  }
}

const closeEditModal = () => {
  editingTemplate.value = null
  editForm.value = { subject: '', body: '', enabled: true }
}

const saveTemplate = async () => {
  saving.value = true
  saveSuccess.value = ''
  saveError.value = ''

  try {
    const response = await api.put(
      `email-templates/${editingTemplate.value.template_key}`,
      editForm.value
    )

    if (response.data.success) {
      saveSuccess.value = 'Template saved successfully.'

      const index = templates.value.findIndex(
        t => t.template_key === editingTemplate.value.template_key
      )
      if (index !== -1) {
        templates.value[index] = { ...templates.value[index], ...editForm.value }
      }

      closeEditModal()

      setTimeout(() => { saveSuccess.value = '' }, 3000)
    } else {
      saveError.value = response.data.message || 'Failed to save template.'
    }
  } catch (err) {
    saveError.value = err.message || 'Failed to save template.'
  } finally {
    saving.value = false
  }
}

const toggleEnabled = async (template) => {
  const newEnabled = !template.enabled

  try {
    const response = await api.put(
      `email-templates/${template.template_key}`,
      { subject: template.subject, body: template.body, enabled: newEnabled }
    )

    if (response.data.success) {
      template.enabled = newEnabled
      saveSuccess.value = `Template ${newEnabled ? 'enabled' : 'disabled'}.`
      setTimeout(() => { saveSuccess.value = '' }, 2000)
    }
  } catch (err) {
    saveError.value = 'Failed to update template status.'
  }
}

const resetTemplate = async (template) => {
  if (!confirm(`Reset "${getTemplateTitle(template.template_key)}" to default? This cannot be undone.`)) {
    return
  }

  try {
    const response = await api.post(`email-templates/${template.template_key}`)

    if (response.data.success) {
      saveSuccess.value = 'Template reset to default successfully.'
      await loadTemplates()
      setTimeout(() => { saveSuccess.value = '' }, 3000)
    } else {
      saveError.value = response.data.message || 'Failed to reset template.'
    }
  } catch (err) {
    saveError.value = err.message || 'Failed to reset template.'
  }
}

const copiedVariable = ref('')

const showCopySuccess = (text) => {
  copiedVariable.value = text
  setTimeout(() => { copiedVariable.value = '' }, 2000)
}

const copyToClipboard = async (text) => {
  try {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      await navigator.clipboard.writeText(text)
      showCopySuccess(text)
    } else {
      const textArea = document.createElement('textarea')
      textArea.value = text
      textArea.style.position = 'fixed'
      textArea.style.left = '-999999px'
      textArea.style.top = '-999999px'
      document.body.appendChild(textArea)
      textArea.focus()
      textArea.select()
      document.execCommand('copy')
      document.body.removeChild(textArea)
      showCopySuccess(text)
    }
  } catch {
    // Silent fail if all copy methods unavailable.
  }
}

const insertVariable = (variable) => {
  const textarea = document.querySelector('.template-body-editor')
  if (!textarea) return

  const start = textarea.selectionStart
  const end = textarea.selectionEnd
  const text = editForm.value.body

  editForm.value.body = text.substring(0, start) + variable + text.substring(end)

  setTimeout(() => {
    textarea.focus()
    textarea.selectionStart = textarea.selectionEnd = start + variable.length
  }, 0)

  showCopySuccess(variable)
}

onMounted(() => {
  loadTemplates()
})
</script>
