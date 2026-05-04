<template>
  <div>
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h2 class="text-lg font-semibold text-gray-900">Service Categories</h2>
        <p class="text-sm text-gray-600 mt-1">Organize your services into categories</p>
      </div>
      <button
        v-if="isAdmin"
        @click="openCreateModal"
        class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition-colors"
      >
        + New Category
      </button>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow p-4 mb-6">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Search -->
        <div>
          <label for="cat-filter-search" class="block text-sm font-medium text-gray-700 mb-1">
            Search
          </label>
          <input
            id="cat-filter-search"
            v-model="filters.search"
            type="text"
            placeholder="Search categories..."
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            @input="debouncedSearch"
          />
        </div>

        <!-- Status Filter -->
        <div>
          <label for="cat-filter-status" class="block text-sm font-medium text-gray-700 mb-1">
            Status
          </label>
          <select
            id="cat-filter-status"
            v-model="filters.status"
            @change="loadCategories"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
          >
            <option value="all">All</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="bg-white rounded-lg shadow-sm border border-gray-200">
      <LoadingSpinner size="lg" message="Loading categories..." full-height />
    </div>

    <!-- Empty State -->
    <div v-else-if="categories.length === 0" class="bg-white rounded-lg shadow-sm border border-gray-200">
      <EmptyState
        icon="📁"
        title="No categories yet"
        description="Organize your services by creating categories. This helps customers find what they're looking for."
      >
        <template #action>
          <button
            v-if="isAdmin"
            @click="openCreateModal"
            class="px-4 py-2.5 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition-colors"
          >
            + Add First Category
          </button>
          <p v-else class="text-sm text-gray-500">
            Contact your administrator to add categories.
          </p>
        </template>
      </EmptyState>
    </div>

    <!-- Categories Table -->
    <div v-else class="bg-white rounded-lg shadow overflow-hidden">
      <div class="overflow-x-auto">
        <table id="categories-table" class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th scope="col" class="w-12 px-3 py-3"><span class="sr-only">Reorder</span></th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Category
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Description
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Services
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Status
              </th>
              <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                Actions
              </th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr
              v-for="category in categories"
              :key="category.id"
              :data-category-id="category.id"
              class="hover:bg-gray-50 transition-colors"
              :class="{ 'opacity-50': !category.is_active }"
            >
              <!-- Drag Handle -->
              <td class="px-3 py-4">
                <span
                  class="drag-handle cursor-grab active:cursor-grabbing text-gray-400 hover:text-gray-600 select-none touch-none"
                  title="Drag to reorder"
                >&#x2807;&#x2807;</span>
              </td>

              <!-- Category Name -->
              <td class="px-6 py-4">
                <div class="text-sm font-medium text-gray-900">
                  {{ category.name }}
                </div>
                <div class="text-xs text-gray-400 mt-1">
                  Order: {{ category.display_order }}
                </div>
              </td>

              <!-- Description -->
              <td class="px-6 py-4">
                <div v-if="category.description" class="text-sm text-gray-600 line-clamp-2">
                  {{ category.description }}
                </div>
                <span v-else class="text-xs text-gray-400">No description</span>
              </td>

              <!-- Service Count -->
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900">
                  {{ category.service_count }} service{{ category.service_count !== 1 ? 's' : '' }}
                </div>
              </td>

              <!-- Status -->
              <td class="px-6 py-4 whitespace-nowrap">
                <span
                  class="px-2.5 py-0.5 text-xs font-medium rounded-full"
                  :class="category.is_active
                    ? 'bg-green-100 text-green-800'
                    : 'bg-gray-100 text-gray-800'"
                >
                  {{ category.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>

              <!-- Actions -->
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                <span v-if="!isAdmin" class="text-xs text-gray-400">View only</span>
                <template v-else>
                  <button
                    @click="openEditModal(category)"
                    class="text-primary-600 hover:text-primary-900 mr-3"
                  >
                    Edit
                  </button>
                  <button
                    @click="confirmDelete(category)"
                    class="text-red-600 hover:text-red-900"
                  >
                    Delete
                  </button>
                </template>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Category Form Modal -->
    <Transition name="fade">
      <CategoryFormModal
        v-if="showFormModal"
        :category="editingCategory"
        @close="closeFormModal"
        @saved="handleCategorySaved"
      />
    </Transition>

    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
        <div class="flex items-start mb-4">
          <span class="text-3xl mr-3">&#x26A0;&#xFE0F;</span>
          <div>
            <h3 class="text-lg font-semibold text-gray-900">Delete Category</h3>
          </div>
        </div>

        <p class="text-sm text-gray-700 mb-4">
          Are you sure you want to delete <strong>{{ deletingCategory?.name }}</strong>?
        </p>

        <div v-if="deletingCategory?.service_count > 0" class="bg-amber-50 border border-amber-200 rounded p-3 mb-4">
          <p class="text-sm text-amber-800">
            &#x26A0;&#xFE0F; This category has <strong>{{ deletingCategory.service_count }} service(s)</strong> assigned to it.
            These services will no longer be in this category after deletion.
          </p>
        </div>

        <div v-if="deleteError" class="bg-red-50 border border-red-200 rounded p-3 mb-4">
          <p class="text-sm text-red-800">{{ deleteError }}</p>
        </div>

        <div class="flex justify-end gap-2">
          <button
            @click="showDeleteModal = false; deleteError = ''"
            :disabled="deleting"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
          >
            Cancel
          </button>
          <button
            @click="deleteCategory"
            :disabled="deleting"
            class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-50"
          >
            {{ deleting ? 'Deleting...' : 'Delete Category' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick, watch } from 'vue'
import Sortable from 'sortablejs'
import { useApi } from '../composables/useApi'
import { useToast } from '../composables/useToast'
import CategoryFormModal from '../components/CategoryFormModal.vue'
import LoadingSpinner from '../components/LoadingSpinner.vue'
import EmptyState from '../components/EmptyState.vue'

const api = useApi()
const { success: toastSuccess } = useToast()

// State
const loading = ref(true)
const categories = ref([])

// Filters
const filters = ref({
  search: '',
  status: 'all'
})

// Modal state
const showFormModal = ref(false)
const editingCategory = ref(null)
const showDeleteModal = ref(false)
const deletingCategory = ref(null)
const deleting = ref(false)
const deleteError = ref('')

// Debounce timer
let searchTimeout = null

// Drag & drop reorder
let sortableInstance = null

const initCategoriesSortable = async () => {
  await nextTick()
  const tbody = document.querySelector('#categories-table tbody')
  if (!tbody || sortableInstance) return

  sortableInstance = Sortable.create(tbody, {
    animation: 150,
    handle: '.drag-handle',
    ghostClass: 'opacity-40',
    dragClass: 'opacity-0',
    onEnd: async (evt) => {
      const { oldIndex, newIndex } = evt
      if (oldIndex === newIndex) return

      const item = categories.value.splice(oldIndex, 1)[0]
      categories.value.splice(newIndex, 0, item)

      const data = categories.value.map((c, i) => ({ id: c.id, display_order: i }))
      try {
        await api.post('categories/reorder', { categories: data })
      } catch (err) {
        console.error('Failed to save categories order:', err)
        loadCategories()
      }
    }
  })
}

watch(loading, async (isLoading) => {
  if (!isLoading) {
    if (sortableInstance) {
      sortableInstance.destroy()
      sortableInstance = null
    }
    await initCategoriesSortable()
  }
})

// Computed
const isAdmin = computed(() => {
  return window.BOOKIT_DASHBOARD?.staff?.role === 'admin'
})

// Methods
const loadCategories = async () => {
  loading.value = true

  try {
    const params = new URLSearchParams({
      status: filters.value.status
    })

    if (filters.value.search) {
      params.append('search', filters.value.search)
    }

    const response = await api.get(`categories/list?${params.toString()}`)

    if (response.data.success) {
      categories.value = response.data.categories
    }
  } catch (err) {
    console.error('Error loading categories:', err)
  } finally {
    loading.value = false
  }
}

const debouncedSearch = () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    loadCategories()
  }, 500)
}

const openCreateModal = () => {
  editingCategory.value = null
  showFormModal.value = true
}

const openEditModal = (category) => {
  editingCategory.value = category
  showFormModal.value = true
}

const closeFormModal = () => {
  showFormModal.value = false
  editingCategory.value = null
}

const handleCategorySaved = () => {
  closeFormModal()
  toastSuccess('Category saved successfully')
  loadCategories()
}

const confirmDelete = (category) => {
  deletingCategory.value = category
  deleteError.value = ''
  showDeleteModal.value = true
}

const deleteCategory = async () => {
  if (!deletingCategory.value) return

  deleting.value = true
  deleteError.value = ''

  try {
    const response = await api.delete(`categories/${deletingCategory.value.id}`)

    if (response.data.success) {
      showDeleteModal.value = false
      deletingCategory.value = null
      toastSuccess('Category deleted successfully')
      loadCategories()
    } else {
      deleteError.value = response.data.message || 'Failed to delete category'
    }
  } catch (err) {
    console.error('Error deleting category:', err)
    deleteError.value = err.message || 'Failed to delete category'
  } finally {
    deleting.value = false
  }
}

// Lifecycle
onMounted(() => {
  loadCategories()
})
</script>
