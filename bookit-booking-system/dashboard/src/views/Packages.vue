<template>
  <div>
    <div class="mb-6 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
      <div>
        <h2 class="text-lg font-semibold text-gray-900">Packages</h2>
        <p class="text-sm text-gray-600 mt-1">Manage package definitions and customer session bundles</p>
      </div>
    </div>

    <div class="border-b border-gray-200 mb-6">
      <div class="flex gap-2">
        <button
          type="button"
          class="px-3 py-1.5 text-sm font-medium rounded-lg mb-3"
          :class="activeTab === 'packageTypes' ? 'bg-primary-600 text-white' : 'text-gray-700 bg-gray-100 hover:bg-gray-200'"
          @click="activeTab = 'packageTypes'"
        >
          Package Types
        </button>
        <button
          type="button"
          class="px-3 py-1.5 text-sm font-medium rounded-lg mb-3"
          :class="activeTab === 'customerPackages' ? 'bg-primary-600 text-white' : 'text-gray-700 bg-gray-100 hover:bg-gray-200'"
          @click="activeTab = 'customerPackages'"
        >
          Customer Packages
        </button>
      </div>
    </div>

    <div v-if="activeTab === 'packageTypes'">
      <div class="mb-4 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
        <div>
          <h3 class="text-base font-semibold text-gray-900">Package Types</h3>
          <p class="text-sm text-gray-600 mt-1">Create and manage reusable session bundle definitions</p>
        </div>
        <button
          type="button"
          class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition-colors"
          @click="openCreatePackageTypeModal"
        >
          + Add Package Type
        </button>
      </div>

      <div v-if="packageTypeActionError" role="alert" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        {{ packageTypeActionError }}
      </div>

      <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div v-if="packageTypesLoading">
          <span role="status" aria-live="polite" class="sr-only">Loading package types...</span>
          <TableSkeleton :rows="6" :columns="7" />
        </div>

        <ErrorState
          v-else-if="packageTypesError"
          title="Failed to load package types"
          :message="packageTypesError"
          :show-home="false"
          @retry="loadPackageTypes"
        />

        <div v-else-if="packageTypes.length === 0" class="p-8 text-center text-sm text-gray-600">
          <p>No package types yet.</p>
          <p class="mt-1 text-xs text-gray-500">
            Create your first package type to start offering session bundles.
          </p>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sessions</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pricing</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Applicable Services</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expiry</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr
                v-for="type in packageTypes"
                :key="type.id"
                class="hover:bg-gray-50 transition-colors"
                :class="{ 'opacity-70': !type.is_active }"
              >
                <td class="px-6 py-4 text-sm text-gray-900">
                  <div class="font-medium">{{ type.name }}</div>
                  <div v-if="type.description" class="text-xs text-gray-500 mt-1 line-clamp-2">{{ type.description }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                  {{ Number(type.sessions_count || 0) }} session{{ Number(type.sessions_count || 0) === 1 ? '' : 's' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                  {{ formatPackageTypePricing(type) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                  <span v-if="!Array.isArray(type.applicable_service_ids) || type.applicable_service_ids.length === 0">
                    All services
                  </span>
                  <span v-else class="inline-flex items-center gap-2">
                    {{ type.applicable_service_ids.length }} service{{ type.applicable_service_ids.length === 1 ? '' : 's' }}
                    <BookitTooltip
                      :content="getApplicableServicesTooltip(type.applicable_service_ids)"
                      position="top"
                    />
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                  {{ type.expiry_enabled ? `${Number(type.expiry_days || 0)} days` : 'Never' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span
                    class="px-2.5 py-0.5 text-xs font-medium rounded-full"
                    :class="type.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700'"
                  >
                    {{ type.is_active ? 'Active' : 'Inactive' }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                  <button
                    type="button"
                    class="text-primary-600 hover:text-primary-900 mr-3"
                    @click="openEditPackageTypeModal(type)"
                  >
                    Edit
                  </button>
                  <button
                    v-if="type.is_active"
                    type="button"
                    class="text-red-600 hover:text-red-900"
                    :disabled="deactivatingPackageTypeId === type.id"
                    @click="confirmDeactivatePackageType(type)"
                  >
                    {{ deactivatingPackageTypeId === type.id ? 'Deactivating...' : 'Deactivate' }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div v-else>
    <div class="mb-4">
      <div class="relative">
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
          <svg aria-hidden="true" class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search by package type or customer..."
          class="w-full pl-10 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
        />
      </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6 p-4">
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label for="status-filter" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
          <select
            id="status-filter"
            v-model="filters.status"
            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            @change="loadPackages(1)"
          >
            <option value="">All</option>
            <option value="active">Active</option>
            <option value="exhausted">Exhausted</option>
            <option value="expired">Expired</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>
        <div>
          <label for="per-page-filter" class="block text-sm font-medium text-gray-700 mb-1">Per page</label>
          <select
            id="per-page-filter"
            v-model.number="filters.per_page"
            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            @change="setPerPage"
          >
            <option :value="25">25</option>
            <option :value="50">50</option>
            <option :value="100">100</option>
          </select>
        </div>
      </div>
    </div>

    <div v-if="redeemSuccess" class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
      {{ redeemSuccess }}
    </div>

    <div v-if="redeemError" role="alert" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
      {{ redeemError }}
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
      <div v-if="loading">
        <span role="status" aria-live="polite" class="sr-only">Loading packages...</span>
        <TableSkeleton :rows="8" :columns="7" />
      </div>

      <ErrorState
        v-else-if="error"
        title="Failed to load packages"
        :message="errorMessage"
        :show-home="false"
        @retry="loadPackages(pagination.current_page)"
      />

      <div v-else-if="pagedPackages.length === 0" class="p-8 text-center text-sm text-gray-600">
        <p>No packages found.</p>
        <p class="mt-1 text-xs text-gray-500">Customer packages will appear here once customers purchase session bundles.</p>
      </div>

      <div v-else>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Package</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sessions</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Purchased</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expires</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <template v-for="pkg in pagedPackages" :key="pkg.id">
              <tr class="hover:bg-gray-50 transition-colors">
                <td class="px-6 py-4 whitespace-nowrap text-sm">
                  <router-link :to="`/customers/${pkg.customer_id}`" class="text-primary-600 hover:text-primary-700 font-medium">
                    {{ getCustomerDisplayName(pkg) }}
                  </router-link>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ pkg.package_type_name || 'Package' }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                  {{ Number(pkg.sessions_remaining || 0) }} / {{ Number(pkg.sessions_total || 0) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <!-- A11y audit: status is conveyed with explicit text (not colour alone). -->
                  <span
                    class="px-2 py-1 text-xs font-medium rounded-full"
                    :class="getStatusClass(pkg.status)"
                  >
                    {{ formatStatus(pkg.status) }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ formatDate(pkg.purchased_at) }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ pkg.expires_at ? formatDate(pkg.expires_at) : 'Never' }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                  <button
                    type="button"
                    class="px-3 py-1.5 text-xs font-medium text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 mr-2"
                    :aria-expanded="expandedPackageId === pkg.id ? 'true' : 'false'"
                    :aria-controls="`redemptions-panel-${pkg.id}`"
                    @click="toggleRedemptions(pkg)"
                  >
                    {{ expandedPackageId === pkg.id ? 'Hide History' : 'History' }}
                  </button>
                  <button
                    v-if="pkg.status === 'active' && Number(pkg.sessions_remaining || 0) > 0"
                    class="px-3 py-1.5 text-xs font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 disabled:opacity-50"
                    :disabled="redeemingId === pkg.id"
                    @click="openRedeemModal(pkg)"
                  >
                    {{ redeemingId === pkg.id ? 'Redeeming...' : 'Redeem Session' }}
                  </button>
                </td>
              </tr>
              <tr v-if="expandedPackageId === pkg.id" :key="`redemptions-${pkg.id}`">
                <td colspan="7" class="px-6 py-0 bg-gray-50">
                  <div :id="`redemptions-panel-${pkg.id}`" class="py-4" aria-live="polite">
                    <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">
                      Redemption History
                    </h4>

                    <div v-if="redemptionsLoading && !redemptionsCache[pkg.id]" role="status" aria-live="polite" class="text-sm text-gray-500">
                      Loading...
                    </div>

                    <div v-else-if="redemptionsError[pkg.id]" role="alert" class="text-sm text-red-600">
                      {{ redemptionsError[pkg.id] }}
                    </div>

                    <div v-else-if="!redemptionsCache[pkg.id]?.length" class="text-sm text-gray-500 italic">
                      No sessions redeemed yet.
                    </div>

                    <table v-else class="min-w-full text-sm">
                      <thead>
                        <tr class="text-xs text-gray-400 uppercase">
                          <th class="pb-2 text-left font-medium">Date</th>
                          <th class="pb-2 text-left font-medium">Booking</th>
                          <th class="pb-2 text-left font-medium">Service</th>
                          <th class="pb-2 text-left font-medium">Staff</th>
                          <th class="pb-2 text-left font-medium">Redeemed By</th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-gray-100">
                        <tr v-for="r in redemptionsCache[pkg.id]" :key="r.id" class="text-gray-700">
                          <td class="py-2 pr-4">{{ formatDate(r.redeemed_at) }}</td>
                          <td class="py-2 pr-4">
                            <span class="text-xs text-gray-500">#{{ r.booking_id }}</span>
                            <span v-if="r.booking_reference" class="ml-1 text-xs text-gray-400">({{ r.booking_reference }})</span>
                          </td>
                          <td class="py-2 pr-4">{{ r.service_name || '—' }}</td>
                          <td class="py-2 pr-4">{{ r.staff_name || '—' }}</td>
                          <td class="py-2">{{ r.redeemed_by_name }}</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </td>
              </tr>
              </template>
            </tbody>
          </table>
        </div>

        <nav class="bg-gray-50 px-4 sm:px-6 py-4 border-t border-gray-200" aria-label="Packages pagination">
          <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
            <div class="text-sm text-gray-700">
              Showing
              <span class="font-medium">{{ resultsStart }}</span>
              to
              <span class="font-medium">{{ resultsEnd }}</span>
              of
              <span class="font-medium">{{ pagination.total }}</span>
              packages
            </div>

            <div class="flex items-center gap-1 sm:gap-2">
              <button
                @click="goToPage(1)"
                :disabled="pagination.current_page <= 1"
                class="hidden sm:block px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                &laquo; First
              </button>
              <button
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
                @click="goToPage(pagination.current_page + 1)"
                :disabled="pagination.current_page >= pagination.total_pages"
                class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Next &rsaquo;
              </button>
              <button
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

    <Teleport to="body">
      <div
        v-if="redeemModalOpen"
        class="fixed inset-0 z-50 flex items-center justify-center"
        role="dialog"
        aria-modal="true"
        aria-labelledby="redeem-modal-title"
      >
        <!-- Backdrop -->
        <div
          class="absolute inset-0 bg-black/50"
          @click="closeRedeemModal"
          aria-hidden="true"
        />

        <!-- Panel -->
        <div
          ref="redeemModalRef"
          tabindex="-1"
          class="relative z-10 w-full max-w-lg mx-4 bg-white rounded-xl shadow-xl focus:outline-none"
        >
          <!-- Header -->
          <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <h2 id="redeem-modal-title" class="text-base font-semibold text-gray-900">
              Redeem Session
            </h2>
            <button
              class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-500"
              aria-label="Close"
              @click="closeRedeemModal"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Body -->
          <div class="px-6 py-4">
            <!-- Package summary -->
            <div v-if="redeemModalPackage" class="mb-4 rounded-lg bg-gray-50 border border-gray-200 px-4 py-3 text-sm text-gray-700">
              <div class="font-medium text-gray-900">{{ redeemModalPackage.package_type_name }}</div>
              <div class="mt-0.5">
                {{ Number(redeemModalPackage.sessions_remaining) }} session{{ Number(redeemModalPackage.sessions_remaining) !== 1 ? 's' : '' }} remaining
              </div>
            </div>

            <!-- Loading state -->
            <div v-if="redeemModalLoading" class="py-6 text-center text-sm text-gray-500">
              Loading bookings...
            </div>

            <!-- Error state -->
            <div
              v-else-if="redeemModalError && redeemModalBookings.length === 0"
              role="alert"
              class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700"
            >
              {{ redeemModalError }}
            </div>

            <!-- Booking list -->
            <div v-else>
              <p class="text-sm text-gray-600 mb-3">Select the booking to redeem this session against:</p>
              <div class="space-y-2 max-h-64 overflow-y-auto">
                <label
                  v-for="booking in redeemModalBookings"
                  :key="booking.id"
                  class="flex items-start gap-3 rounded-lg border border-gray-200 px-4 py-3 cursor-pointer hover:bg-gray-50 transition-colors"
                  :class="{ 'border-primary-500 bg-primary-50': redeemModalSelectedBookingId === booking.id }"
                >
                  <input
                    type="radio"
                    :value="booking.id"
                    v-model="redeemModalSelectedBookingId"
                    class="mt-0.5 text-primary-600 focus:ring-primary-500"
                  />
                  <div class="text-sm text-gray-700">
                    <div class="font-medium text-gray-900">
                      #{{ booking.id }} · {{ booking.booking_date }} {{ booking.start_time }}
                    </div>
                    <div>{{ booking.service_name }} · {{ booking.staff_name }}</div>
                    <div class="text-xs text-gray-500 capitalize">{{ booking.status }}</div>
                  </div>
                </label>
              </div>

              <!-- Inline error after submit attempt -->
              <div
                v-if="redeemModalError && redeemModalBookings.length > 0"
                role="alert"
                class="mt-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
              >
                {{ redeemModalError }}
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200">
            <button
              class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
              @click="closeRedeemModal"
              :disabled="redeemModalSubmitting"
            >
              Cancel
            </button>
            <button
              class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 disabled:opacity-50"
              :disabled="!redeemModalSelectedBookingId || redeemModalSubmitting || redeemModalLoading"
              @click="submitRedemption"
            >
              {{ redeemModalSubmitting ? 'Redeeming...' : 'Confirm Redemption' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
    </div>

    <Transition name="fade">
      <PackageTypeFormModal
        v-if="showPackageTypeFormModal"
        :package-type="editingPackageType"
        :services="activeServices"
        @close="closePackageTypeFormModal"
        @saved="handlePackageTypeSaved"
      />
    </Transition>

    <div v-if="showDeactivatePackageTypeModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
        <div class="flex items-start mb-4">
          <span class="text-3xl mr-3">&#x26A0;&#xFE0F;</span>
          <div>
            <h3 class="text-lg font-semibold text-gray-900">Deactivate Package Type</h3>
          </div>
        </div>
        <p class="text-sm text-gray-700 mb-4">
          Are you sure you want to deactivate <strong>{{ packageTypePendingDeactivate?.name }}</strong>?
        </p>
        <p class="text-xs text-gray-500 mb-4">
          Existing customer packages remain usable. New purchases of this package type will be blocked.
        </p>

        <div class="flex justify-end gap-2">
          <button
            type="button"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
            :disabled="deactivatingPackageTypeId !== null"
            @click="closeDeactivatePackageTypeModal"
          >
            Cancel
          </button>
          <button
            type="button"
            class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-50"
            :disabled="deactivatingPackageTypeId !== null"
            @click="deactivatePackageType"
          >
            {{ deactivatingPackageTypeId !== null ? 'Deactivating...' : 'Deactivate' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useApi } from '../composables/useApi'
import ErrorState from '../components/ErrorState.vue'
import TableSkeleton from '../components/TableSkeleton.vue'
import BookitTooltip from '../components/BookitTooltip.vue'
import PackageTypeFormModal from '../components/PackageTypeFormModal.vue'

const router = useRouter()
const api = useApi()
const currentUser = window.BOOKIT_DASHBOARD?.staff || {}

const isAdmin = computed(() => currentUser.role === 'admin' || currentUser.role === 'bookit_admin')

const activeTab = ref('packageTypes')
const loading = ref(true)
const error = ref(false)
const errorMessage = ref('')
const packages = ref([])
const filters = ref({ status: '', per_page: 25 })
const pagination = ref({ total: 0, per_page: 25, current_page: 1, total_pages: 1 })
const searchQuery = ref('')
const redeemingId = ref(null)
const redeemError = ref('')
const redeemSuccess = ref('')
const customerNames = ref({})
const expandedPackageId = ref(null)
const redemptionsCache = ref({})
const redemptionsLoading = ref(false)
const redemptionsError = ref({})
// Redeem modal state
const redeemModalOpen = ref(false)
const redeemModalPackage = ref(null)       // the package row being redeemed
const redeemModalBookings = ref([])        // unlinked bookings for this customer
const redeemModalLoading = ref(false)
const redeemModalError = ref('')
const redeemModalSelectedBookingId = ref(null)
const redeemModalSubmitting = ref(false)
const redeemModalRef = ref(null)           // template ref for focus trap
const redeemPreviousActive = ref(null)     // element to restore focus to on close

// Package type tab state
const packageTypes = ref([])
const packageTypesLoading = ref(true)
const packageTypesError = ref('')
const packageTypeActionError = ref('')
const activeServices = ref([])
const serviceNameMap = ref({})
const showPackageTypeFormModal = ref(false)
const editingPackageType = ref(null)
const showDeactivatePackageTypeModal = ref(false)
const packageTypePendingDeactivate = ref(null)
const deactivatingPackageTypeId = ref(null)

let searchTimeout = null

const filteredPackages = computed(() => {
  const term = searchQuery.value.trim().toLowerCase()
  if (!term) {
    return packages.value
  }

  return packages.value.filter((pkg) => {
    const packageType = String(pkg.package_type_name || '').toLowerCase()
    const customerName = String(customerNames.value[pkg.customer_id] || '').toLowerCase()
    const fallbackCustomer = `customer #${pkg.customer_id}`.toLowerCase()

    return packageType.includes(term) || customerName.includes(term) || fallbackCustomer.includes(term)
  })
})

const pagedPackages = computed(() => {
  const start = (pagination.value.current_page - 1) * pagination.value.per_page
  return filteredPackages.value.slice(start, start + pagination.value.per_page)
})

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

const syncPagination = () => {
  const total = filteredPackages.value.length
  const perPage = Number(filters.value.per_page || 25)
  const totalPages = Math.max(1, Math.ceil(total / perPage))
  const currentPage = Math.min(pagination.value.current_page, totalPages)

  pagination.value = {
    total,
    per_page: perPage,
    current_page: currentPage,
    total_pages: totalPages
  }
}

async function hydrateCustomerNamesForPage() {
  const missingIds = [...new Set(
    pagedPackages.value
      .map((pkg) => Number(pkg.customer_id))
      .filter((id) => id > 0 && !customerNames.value[id])
  )]

  if (!missingIds.length) {
    return
  }

  const responses = await Promise.allSettled(
    missingIds.map((id) => api.get(`/customers/${id}`))
  )

  responses.forEach((result, index) => {
    const id = missingIds[index]
    if (result.status !== 'fulfilled') {
      return
    }

    const customer = result.value?.data?.customer
    if (!customer) {
      return
    }

    customerNames.value[id] = customer.full_name || `${customer.first_name || ''} ${customer.last_name || ''}`.trim() || `Customer #${id}`
  })
}

function getCustomerDisplayName(pkg) {
  return customerNames.value[pkg.customer_id] || `Customer #${pkg.customer_id}`
}

function formatDate(value) {
  if (!value) return 'Never'
  const normalized = String(value).includes(' ') ? String(value).replace(' ', 'T') : `${value}T00:00:00`
  const date = new Date(normalized)
  if (Number.isNaN(date.getTime())) return 'Invalid date'
  return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

function formatStatus(status) {
  if (!status) return 'Unknown'
  return status.charAt(0).toUpperCase() + status.slice(1)
}

function getStatusClass(status) {
  const map = {
    active: 'bg-green-100 text-green-800',
    exhausted: 'bg-gray-100 text-gray-600',
    expired: 'bg-amber-100 text-amber-800',
    cancelled: 'bg-red-100 text-red-700'
  }
  return map[status] || 'bg-gray-100 text-gray-700'
}

function formatPounds(value) {
  const amount = Number(value || 0)
  return `£${amount.toFixed(2)}`
}

function formatPackageTypePricing(type) {
  if (type.price_mode === 'fixed') {
    return formatPounds(type.fixed_price)
  }
  return `${Number(type.discount_percentage || 0).toFixed(2)}% discount`
}

function getApplicableServicesTooltip(serviceIds) {
  if (!Array.isArray(serviceIds) || serviceIds.length === 0) {
    return 'All services'
  }

  return serviceIds
    .map((serviceId) => serviceNameMap.value[serviceId] || `Service #${serviceId}`)
    .join('\n')
}

async function loadActiveServices() {
  try {
    const response = await api.get('/services/list?status=active')
    if (response.data?.success && Array.isArray(response.data.services)) {
      activeServices.value = response.data.services
      serviceNameMap.value = response.data.services.reduce((acc, service) => {
        acc[service.id] = service.name
        return acc
      }, {})
    }
  } catch {
    activeServices.value = []
    serviceNameMap.value = {}
  }
}

async function loadPackageTypes() {
  packageTypesLoading.value = true
  packageTypesError.value = ''

  try {
    const response = await api.get('/package-types')
    packageTypes.value = Array.isArray(response.data) ? response.data : []
  } catch (err) {
    packageTypesError.value = err.message || 'Failed to load package types.'
  } finally {
    packageTypesLoading.value = false
  }
}

function openCreatePackageTypeModal() {
  packageTypeActionError.value = ''
  editingPackageType.value = null
  showPackageTypeFormModal.value = true
}

function openEditPackageTypeModal(packageType) {
  packageTypeActionError.value = ''
  editingPackageType.value = packageType
  showPackageTypeFormModal.value = true
}

function closePackageTypeFormModal() {
  showPackageTypeFormModal.value = false
  editingPackageType.value = null
}

async function handlePackageTypeSaved() {
  closePackageTypeFormModal()
  await loadPackageTypes()
}

function confirmDeactivatePackageType(packageType) {
  packageTypeActionError.value = ''
  packageTypePendingDeactivate.value = packageType
  showDeactivatePackageTypeModal.value = true
}

function closeDeactivatePackageTypeModal() {
  showDeactivatePackageTypeModal.value = false
  packageTypePendingDeactivate.value = null
}

async function deactivatePackageType() {
  if (!packageTypePendingDeactivate.value) {
    return
  }

  const packageTypeId = packageTypePendingDeactivate.value.id
  deactivatingPackageTypeId.value = packageTypeId
  packageTypeActionError.value = ''

  try {
    await api.post(`/package-types/${packageTypeId}/deactivate`)
    closeDeactivatePackageTypeModal()
    await loadPackageTypes()
  } catch (err) {
    packageTypeActionError.value = err.message || 'Failed to deactivate package type.'
  } finally {
    deactivatingPackageTypeId.value = null
  }
}

async function toggleRedemptions(pkg) {
  if (expandedPackageId.value === pkg.id) {
    expandedPackageId.value = null
    return
  }

  expandedPackageId.value = pkg.id
  if (redemptionsCache.value[pkg.id]) return

  redemptionsLoading.value = true
  redemptionsError.value[pkg.id] = ''

  try {
    const response = await api.get(`/customer-packages/${pkg.id}/redemptions`)
    redemptionsCache.value[pkg.id] = response.data?.redemptions || []
  } catch (err) {
    redemptionsError.value[pkg.id] = err.message || 'Failed to load redemption history.'
  } finally {
    redemptionsLoading.value = false
  }
}

async function loadPackages(page = 1) {
  loading.value = true
  error.value = false
  errorMessage.value = ''
  redeemError.value = ''
  redeemSuccess.value = ''

  try {
    const params = new URLSearchParams({
      page: String(page),
      per_page: String(filters.value.per_page)
    })
    if (filters.value.status) params.append('status', filters.value.status)

    const response = await api.get(`/customer-packages?${params.toString()}`)
    const responseData = response.data
    const rows = Array.isArray(responseData)
      ? responseData
      : (Array.isArray(responseData?.packages) ? responseData.packages : [])

    packages.value = rows
    pagination.value.current_page = page
    syncPagination()
    await hydrateCustomerNamesForPage()
  } catch (err) {
    error.value = true
    errorMessage.value = err.message || 'An unexpected error occurred.'
  } finally {
    loading.value = false
  }
}

const getRedeemFocusableElements = () => {
  if (!redeemModalRef.value) return []
  return Array.from(
    redeemModalRef.value.querySelectorAll(
      'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
    )
  )
}

const trapFocusHandler = (e) => {
  if (!redeemModalRef.value) return

  const focusable = getRedeemFocusableElements()
  if (focusable.length === 0) return

  const first = focusable[0]
  const last = focusable[focusable.length - 1]

  if (e.key === 'Tab') {
    if (e.shiftKey) {
      if (document.activeElement === first) {
        last.focus()
        e.preventDefault()
      }
    } else {
      if (document.activeElement === last) {
        first.focus()
        e.preventDefault()
      }
    }
  }

  if (e.key === 'Escape') {
    closeRedeemModal()
  }
}

async function openRedeemModal(row) {
  redeemError.value = ''
  redeemSuccess.value = ''
  redeemModalPackage.value = row
  redeemModalBookings.value = []
  redeemModalSelectedBookingId.value = null
  redeemModalError.value = ''
  redeemModalSubmitting.value = false
  redeemModalLoading.value = true
  redeemModalOpen.value = true

  // Save focus so we can restore it when the modal closes
  redeemPreviousActive.value = document.activeElement

  try {
    // Fetch this customer's bookings that are not yet linked to a package.
    // Uses customer_id filter + per_page=100 to get a full list.
    // Read the bookings API controller to confirm the correct param names
    // before implementing — do NOT assume param names.
    const response = await api.get(
      `/bookings?customer_id=${row.customer_id}&per_page=100`
    )
    const allBookings = response.data?.bookings || response.data || []

    // Filter client-side to only unlinked bookings (customer_package_id is null/0)
    // and non-cancelled/non-no-show statuses
    redeemModalBookings.value = allBookings.filter(b =>
      !b.customer_package_id &&
      b.status !== 'cancelled' &&
      b.status !== 'no_show'
    )

    if (redeemModalBookings.value.length === 0) {
      redeemModalError.value = 'No eligible bookings found for this customer. A booking must exist and not already be linked to a package.'
    }
  } catch (err) {
    redeemModalError.value = err.message || 'Failed to load bookings.'
  } finally {
    redeemModalLoading.value = false
    // Focus the modal after data loads
    await nextTick()
    redeemModalRef.value?.focus()
  }
}

function closeRedeemModal() {
  redeemModalOpen.value = false
  redeemModalPackage.value = null
  redeemModalBookings.value = []
  redeemModalSelectedBookingId.value = null
  redeemModalError.value = ''
  redeemModalSubmitting.value = false
  // Restore focus to the button that triggered the modal
  nextTick(() => redeemPreviousActive.value?.focus())
}

async function submitRedemption() {
  if (!redeemModalSelectedBookingId.value || !redeemModalPackage.value) return

  redeemModalSubmitting.value = true
  redeemModalError.value = ''

  try {
    await api.post('/package-redemptions', {
      customer_package_id: redeemModalPackage.value.id,
      booking_id: redeemModalSelectedBookingId.value,
      notes: ''
    })
    redeemSuccess.value = 'Session redeemed successfully.'
    closeRedeemModal()
    await loadPackages(pagination.value.current_page)
  } catch (err) {
    const code = err.code ? `${err.code}: ` : ''
    redeemModalError.value = `${code}${err.message || 'Failed to redeem session.'}`
  } finally {
    redeemModalSubmitting.value = false
  }
}

async function goToPage(page) {
  if (page < 1 || page > pagination.value.total_pages) return
  pagination.value.current_page = page
  await hydrateCustomerNamesForPage()
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

async function setPerPage() {
  pagination.value.current_page = 1
  syncPagination()
  await hydrateCustomerNamesForPage()
}

watch(searchQuery, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(async () => {
    pagination.value.current_page = 1
    syncPagination()
    await hydrateCustomerNamesForPage()
  }, 400)
})

watch(
  () => filteredPackages.value.length,
  async () => {
    syncPagination()
    await hydrateCustomerNamesForPage()
  }
)

watch(redeemModalOpen, async (isOpen) => {
  if (isOpen) {
    document.addEventListener('keydown', trapFocusHandler)

    await nextTick()
    const focusable = getRedeemFocusableElements()
    if (focusable.length > 0) {
      focusable[0].focus()
    } else {
      redeemModalRef.value?.focus()
    }
  } else {
    document.removeEventListener('keydown', trapFocusHandler)
  }
})

onMounted(() => {
  if (!isAdmin.value) {
    router.push('/')
    return
  }

  loadPackageTypes()
  loadActiveServices()
  loadPackages(1)
})

onBeforeUnmount(() => {
  clearTimeout(searchTimeout)
  document.removeEventListener('keydown', trapFocusHandler)
})
</script>
