<template>
  <div class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4" @click.self="$emit('close')">
    <div
      ref="modalRef"
      role="dialog"
      aria-modal="true"
      aria-labelledby="staff-modal-title"
      class="bg-white rounded-lg shadow-xl max-w-3xl w-full max-h-[90vh] overflow-y-auto"
    >
      <!-- Header -->
      <div class="px-4 sm:px-6 py-4 border-b border-gray-200 sticky top-0 bg-white z-10">
        <div class="flex items-center justify-between">
          <h2 id="staff-modal-title" class="text-xl font-semibold text-gray-900">
            {{ isEditing ? 'Edit Staff Member' : 'Add New Staff Member' }}
          </h2>
          <button
            @click="$emit('close')"
            class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100"
            aria-label="Close dialog"
          >
            <svg aria-hidden="true" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </div>

      <!-- Loading Details -->
      <div v-if="loadingDetails" class="px-4 sm:px-6 py-12 text-center">
        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
        <p class="mt-2 text-sm text-gray-600">Loading staff details...</p>
      </div>

      <template v-else>
        <!-- Body -->
        <form @submit.prevent="saveStaff" class="px-4 sm:px-6 py-6 space-y-6">
          <!-- Error Message -->
          <div v-if="errorMessage" role="alert" aria-live="assertive" class="bg-red-50 border border-red-200 rounded-lg p-3">
            <p class="text-sm text-red-800">{{ errorMessage }}</p>
          </div>

          <!-- Profile Photo -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Profile Photo
            </label>
            <div class="flex items-center gap-4">
              <div class="flex-shrink-0">
                <img
                  v-if="formData.photo_url"
                  :src="formData.photo_url"
                  alt="Profile photo"
                  class="h-20 w-20 rounded-full object-cover border-2 border-gray-200"
                />
                <div
                  v-else
                  class="h-20 w-20 rounded-full flex items-center justify-center text-white font-semibold text-xl border-2 border-gray-200"
                  :style="{ backgroundColor: getColorForInitials(formData.first_name + ' ' + formData.last_name) }"
                >
                  {{ getInitials(formData.first_name + ' ' + formData.last_name) }}
                </div>
              </div>
              <div class="flex-1">
                <!-- Hidden file input — triggered by the button below -->
                <input
                  ref="photoInput"
                  type="file"
                  accept="image/jpeg,image/png,image/gif,image/webp"
                  class="hidden"
                  @change="onPhotoSelected"
                />

                <!-- Upload button (editing only — photo upload requires a staff ID) -->
                <template v-if="isEditing">
                  <button
                    type="button"
                    @click="photoInput.click()"
                    :disabled="uploadingPhoto"
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border
                           border-gray-300 rounded-lg hover:bg-gray-50
                           disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    <span v-if="uploadingPhoto">Uploading...</span>
                    <span v-else>{{ formData.photo_url ? 'Change Photo' : 'Upload Photo' }}</span>
                  </button>

                  <button
                    v-if="formData.photo_url"
                    type="button"
                    @click="formData.photo_url = ''"
                    class="ml-2 px-4 py-2 text-sm font-medium text-red-600 hover:text-red-700"
                  >
                    Remove
                  </button>

                  <p v-if="photoUploadError" class="text-xs text-red-600 mt-1">
                    {{ photoUploadError }}
                  </p>
                </template>

                <!-- New staff: photo upload requires saving first -->
                <template v-else>
                  <p class="text-xs text-gray-500">
                    Save the staff member first, then add a photo.
                  </p>
                </template>

                <p class="text-xs text-gray-500 mt-1">
                  JPG, PNG, GIF or WebP. Max 5MB.
                </p>
              </div>
            </div>
          </div>

          <!-- Name -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label for="staff-first-name" class="block text-sm font-medium text-gray-700 mb-1">
                First Name *
              </label>
              <input
                id="staff-first-name"
                v-model="formData.first_name"
                type="text"
                required
                aria-required="true"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                placeholder="John"
              />
            </div>
            <div>
              <label for="staff-last-name" class="block text-sm font-medium text-gray-700 mb-1">
                Last Name *
              </label>
              <input
                id="staff-last-name"
                v-model="formData.last_name"
                type="text"
                required
                aria-required="true"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                placeholder="Doe"
              />
            </div>
          </div>

          <!-- Email and Password -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label for="staff-email" class="block text-sm font-medium text-gray-700 mb-1">
                Email *
              </label>
              <input
                id="staff-email"
                v-model="formData.email"
                type="email"
                required
                aria-required="true"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                placeholder="john@example.com"
              />
            </div>
            <div v-if="!isEditing">
              <label for="staff-password" class="block text-sm font-medium text-gray-700 mb-1">
                Password *
              </label>
              <input
                id="staff-password"
                v-model="formData.password"
                type="password"
                :required="!isEditing"
                aria-required="true"
                minlength="8"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                placeholder="Min 8 characters"
              />
            </div>
          </div>

          <!-- Password Reset (Edit Mode Only) -->
          <div v-if="isEditing" class="border border-gray-300 rounded-lg p-4 bg-gray-50">
            <div class="flex items-center justify-between mb-2">
              <h4 class="text-sm font-medium text-gray-900">Password Reset</h4>
              <button
                v-if="!showPasswordReset"
                type="button"
                @click="showPasswordReset = true"
                class="text-sm text-primary-600 hover:text-primary-700"
              >
                Reset Password
              </button>
            </div>

            <div v-if="showPasswordReset" class="space-y-3 mt-3">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                  New Password *
                </label>
                <input
                  v-model="newPassword"
                  type="text"
                  minlength="8"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                  placeholder="Min 8 characters"
                />
                <button
                  type="button"
                  @click="generatePassword"
                  class="text-xs text-primary-600 hover:text-primary-700 mt-1"
                >
                  Generate secure password
                </button>
              </div>

              <div>
                <label class="flex items-center">
                  <input
                    type="checkbox"
                    v-model="sendPasswordEmail"
                    class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500"
                  />
                  <span class="ml-2 text-sm text-gray-700">
                    Email new password to staff member
                  </span>
                </label>
              </div>

              <div class="flex gap-2">
                <button
                  type="button"
                  @click="resetPassword"
                  :disabled="!newPassword || newPassword.length < 8 || resettingPassword"
                  class="px-3 py-1.5 text-sm font-medium text-white bg-amber-600 rounded hover:bg-amber-700 disabled:opacity-50"
                >
                  {{ resettingPassword ? 'Resetting...' : 'Reset Password' }}
                </button>
                <button
                  type="button"
                  @click="showPasswordReset = false; newPassword = ''"
                  class="px-3 py-1.5 text-sm font-medium text-gray-700 border border-gray-300 rounded hover:bg-gray-50"
                >
                  Cancel
                </button>
              </div>
            </div>
          </div>

          <!-- Phone and Title -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label for="staff-phone" class="block text-sm font-medium text-gray-700 mb-1">
                Phone
              </label>
              <input
                id="staff-phone"
                v-model="formData.phone"
                type="tel"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                placeholder="01234567890"
              />
            </div>
            <div>
              <label for="staff-title" class="block text-sm font-medium text-gray-700 mb-1">
                Job Title
              </label>
              <input
                id="staff-title"
                v-model="formData.title"
                type="text"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                placeholder="e.g., Senior Stylist"
              />
            </div>
          </div>

          <!-- Bio -->
          <div>
            <label for="staff-bio" class="block text-sm font-medium text-gray-700 mb-1">
              Bio
            </label>
            <textarea
              id="staff-bio"
              v-model="formData.bio"
              rows="3"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              placeholder="Brief description about this staff member..."
            ></textarea>
          </div>

          <!-- Service Assignments -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Service Assignments
            </label>
            <div v-if="services.length === 0" class="border border-gray-200 rounded-lg p-4 text-center text-sm text-gray-500">
              No services available. Create services first.
            </div>
            <div v-else class="border border-gray-200 rounded-lg divide-y divide-gray-200 max-h-64 overflow-y-auto">
              <div
                v-for="service in services"
                :key="service.id"
                class="p-3 hover:bg-gray-50"
              >
                <div class="flex items-start">
                  <div class="flex items-center h-5 mt-0.5">
                    <input
                      type="checkbox"
                      :value="service.id"
                      v-model="selectedServices"
                      @change="onServiceToggle(service)"
                      class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500"
                    />
                  </div>
                  <div class="ml-3 flex-1">
                    <label class="text-sm font-medium text-gray-900">
                      {{ service.name }}
                    </label>
                    <p class="text-xs text-gray-500">
                      Base price: &pound;{{ parseFloat(service.price).toFixed(2) }}
                    </p>
                    <div v-if="selectedServices.includes(service.id)" class="mt-2">
                      <label class="block text-xs text-gray-600 mb-1">
                        Custom Price (optional)
                      </label>
                      <div class="flex items-center gap-2">
                        <div class="relative flex-1">
                          <span class="absolute left-3 top-2 text-gray-500 text-sm">&pound;</span>
                          <input
                            v-model.number="customPrices[service.id]"
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="Leave empty for base price"
                            class="w-full pl-8 pr-3 py-1.5 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                          />
                        </div>
                        <button
                          v-if="customPrices[service.id]"
                          type="button"
                          @click="customPrices[service.id] = null"
                          class="text-xs text-red-600 hover:text-red-700"
                        >
                          Clear
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <p class="text-xs text-gray-500 mt-2">
              Select services this staff member can provide. Set custom prices to override the base service price.
            </p>
          </div>

          <!-- Role, Status, and Display Order -->
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
              <label for="staff-role" class="block text-sm font-medium text-gray-700 mb-1">
                Role *
              </label>
              <select
                id="staff-role"
                v-model="formData.role"
                required
                aria-required="true"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              >
                <option value="staff">Staff</option>
                <option value="admin">Admin</option>
              </select>
            </div>
            <div>
              <label for="staff-display-order" class="block text-sm font-medium text-gray-700 mb-1">
                Display Order
              </label>
              <input
                id="staff-display-order"
                v-model.number="formData.display_order"
                type="number"
                min="0"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              />
            </div>
            <div class="flex items-end pb-2">
              <label class="flex items-center">
                <input
                  type="checkbox"
                  v-model="formData.is_active"
                  class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500"
                />
                <span class="ml-2 text-sm font-medium text-gray-700">
                  Active
                </span>
              </label>
            </div>
          </div>

          <!-- Notification Preferences (admin editing only) -->
          <div v-if="isEditing" class="border-t border-gray-200 pt-4">
            <h3 class="text-sm font-semibold text-gray-900 mb-3">Notification Preferences</h3>
            <p class="text-xs text-gray-500 mb-4">
              Control when this staff member receives email notifications.
              Staff members can also update these from their own profile.
            </p>

            <div class="space-y-3">
              <!-- New Booking -->
              <div class="flex items-center justify-between">
                <label class="text-sm font-medium text-gray-700">New Booking</label>
                <select
                  v-model="staffNotificationPrefs.new_booking"
                  class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                >
                  <option value="immediate">Immediate</option>
                  <option value="daily">Daily digest</option>
                  <option value="weekly">Weekly digest</option>
                </select>
              </div>

              <!-- Reschedule -->
              <div class="flex items-center justify-between">
                <label class="text-sm font-medium text-gray-700">Reschedule</label>
                <select
                  v-model="staffNotificationPrefs.reschedule"
                  class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                >
                  <option value="immediate">Immediate</option>
                  <option value="daily">Daily digest</option>
                  <option value="weekly">Weekly digest</option>
                </select>
              </div>

              <!-- Cancellation -->
              <div class="flex items-center justify-between">
                <label class="text-sm font-medium text-gray-700">Cancellation</label>
                <select
                  v-model="staffNotificationPrefs.cancellation"
                  class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                >
                  <option value="immediate">Immediate</option>
                  <option value="daily">Daily digest</option>
                  <option value="weekly">Weekly digest</option>
                </select>
              </div>

              <!-- Daily Schedule Toggle -->
              <div class="flex items-start justify-between pt-2 border-t border-gray-100">
                <div>
                  <p class="text-sm font-medium text-gray-700">Daily Schedule Email</p>
                  <p class="text-xs text-gray-500 mt-0.5">
                    Send a morning summary of today's bookings
                  </p>
                </div>
                <button
                  type="button"
                  role="switch"
                  :aria-checked="staffNotificationPrefs.daily_schedule"
                  @click="staffNotificationPrefs.daily_schedule = !staffNotificationPrefs.daily_schedule"
                  class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 mt-0.5"
                  :class="staffNotificationPrefs.daily_schedule ? 'bg-primary-600' : 'bg-gray-200'"
                >
                  <span
                    class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                    :class="staffNotificationPrefs.daily_schedule ? 'translate-x-5' : 'translate-x-0'"
                  />
                </button>
              </div>
            </div>
          </div>

          <!-- Google Calendar connection (edit mode, admin view — read-only status + admin disconnect) -->
          <div v-if="isEditing" class="border-t border-gray-200 pt-4">
            <!-- Connected -->
            <div
              v-if="googleCalendarConnected"
              class="rounded-lg border border-green-200 bg-green-50 p-4 shadow-sm"
            >
              <div class="flex gap-3">
                <span
                  class="mt-1.5 h-4 w-4 shrink-0 rounded-full bg-green-500 ring-2 ring-green-200"
                  aria-hidden="true"
                />
                <div class="min-w-0 flex-1">
                  <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                      <div class="flex items-center gap-2">
                        <svg
                          class="h-5 w-5 shrink-0 text-green-700"
                          fill="none"
                          stroke="currentColor"
                          viewBox="0 0 24 24"
                          aria-hidden="true"
                        >
                          <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                          />
                        </svg>
                        <h3 class="text-base font-semibold text-gray-900">
                          Google Calendar
                        </h3>
                      </div>
                      <p class="mt-2 text-base font-semibold text-green-700">
                        Connected
                      </p>
                      <p class="mt-1 text-sm break-all">
                        <span v-if="googleCalendarEmail" class="text-gray-600">
                          ({{ googleCalendarEmail }})
                        </span>
                      </p>
                    </div>
                    <button
                      type="button"
                      :disabled="disconnectingGoogleCalendar"
                      class="ml-auto shrink-0 px-3 py-2 text-sm font-medium text-red-700 bg-white border border-red-300 rounded-lg shadow-sm hover:bg-red-50 disabled:opacity-50 disabled:cursor-not-allowed"
                      @click="disconnectGoogleCalendar"
                    >
                      {{ disconnectingGoogleCalendar ? 'Disconnecting...' : 'Disconnect' }}
                    </button>
                  </div>
                  <p
                    v-if="googleCalendarDisconnectError"
                    role="alert"
                    class="mt-3 text-sm text-red-800"
                  >
                    {{ googleCalendarDisconnectError }}
                  </p>
                </div>
              </div>
            </div>

            <!-- Not connected -->
            <div
              v-else
              class="rounded-lg border border-gray-200 bg-gray-50 p-4 shadow-sm"
            >
              <div class="flex gap-3">
                <span
                  class="mt-1.5 h-4 w-4 shrink-0 rounded-full border-2 border-gray-300 bg-white"
                  aria-hidden="true"
                />
                <div class="min-w-0">
                  <div class="flex items-center gap-2">
                    <svg
                      class="h-5 w-5 shrink-0 text-gray-500"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                      aria-hidden="true"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                      />
                    </svg>
                    <h3 class="text-base font-semibold text-gray-900">
                      Google Calendar
                    </h3>
                  </div>
                  <p class="mt-2 text-base text-gray-500">
                    Not connected
                  </p>
                  <p class="mt-1 text-xs italic text-gray-500">
                    Staff can connect from their profile
                  </p>
                </div>
              </div>
            </div>
          </div>

          <!-- Google Calendar ID -->
          <div>
            <label for="staff-gcal-id" class="block text-sm font-medium text-gray-700 mb-1">
              Google Calendar ID (Optional)
            </label>
            <input
              id="staff-gcal-id"
              v-model="formData.google_calendar_id"
              type="text"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              placeholder="calendar@gmail.com"
            />
            <p class="text-xs text-gray-500 mt-1">
              For Google Calendar sync (future feature)
            </p>
          </div>

          <!-- Working Hours Info (editing only) -->
          <div v-if="isEditing && staffDetails?.has_working_hours" class="bg-blue-50 border border-blue-200 rounded p-3">
            <div class="flex items-center justify-between">
              <p class="text-sm text-blue-800">
                Working hours are <strong>configured</strong>.
              </p>
              <router-link
                :to="`/staff/${staffMember.id}/hours`"
                @click="$emit('close')"
                class="text-sm font-medium text-blue-600 hover:text-blue-700 underline"
              >
                Edit Working Hours &rarr;
              </router-link>
            </div>
          </div>
          <div v-else-if="isEditing && staffDetails && !staffDetails.has_working_hours" class="bg-amber-50 border border-amber-200 rounded p-3">
            <div class="flex items-center justify-between">
              <p class="text-sm text-amber-800">
                Working hours <strong>not configured</strong>.
                This staff member won't appear in booking availability.
              </p>
              <router-link
                :to="`/staff/${staffMember.id}/hours`"
                @click="$emit('close')"
                class="text-sm font-medium text-amber-600 hover:text-amber-700 underline whitespace-nowrap ml-3"
              >
                Configure Now &rarr;
              </router-link>
            </div>
          </div>

          <!-- Bookings Info (editing only) -->
          <div v-if="isEditing && staffDetails?.future_bookings_count > 0" class="bg-purple-50 border border-purple-200 rounded p-3">
            <p class="text-sm text-purple-800">
              This staff member has <strong>{{ staffDetails.future_bookings_count }} future booking(s)</strong>.
            </p>
          </div>
        </form>

        <!-- Footer -->
        <div class="px-4 sm:px-6 py-4 border-t border-gray-200 bg-gray-50 sticky bottom-0">
          <div class="flex flex-col-reverse sm:flex-row justify-end gap-2">
            <button
              @click="$emit('close')"
              :disabled="saving"
              class="w-full sm:w-auto px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50"
            >
              Cancel
            </button>
            <button
              @click="saveStaff"
              :disabled="saving || !isValid"
              class="w-full sm:w-auto px-4 py-2.5 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {{ saving ? 'Saving...' : (isEditing ? 'Update Staff Member' : 'Create Staff Member') }}
            </button>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { useApi } from '../composables/useApi'
import { useToast } from '../composables/useToast'

const api = useApi()
const { success: toastSuccess, error: toastError } = useToast()

const props = defineProps({
  staffMember: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['close', 'saved'])

const modalRef = ref(null)
const previousActiveElement = ref(null)
const photoInput = ref(null)

const getFocusableElements = () => {
  if (!modalRef.value) return []
  return Array.from(
    modalRef.value.querySelectorAll(
      'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
    )
  )
}

const trapFocus = (e) => {
  if (!modalRef.value) return

  const focusable = getFocusableElements()
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
    emit('close')
  }
}

// State
const saving = ref(false)
const loadingDetails = ref(false)
const errorMessage = ref('')
const uploadingPhoto = ref(false)
const photoUploadError = ref('')
const services = ref([])
const selectedServices = ref([])
const customPrices = ref({})
const staffDetails = ref(null)

const staffNotificationPrefs = ref({
  new_booking: 'immediate',
  reschedule: 'immediate',
  cancellation: 'immediate',
  daily_schedule: false
})

// Password reset state
const showPasswordReset = ref(false)
const newPassword = ref('')
const sendPasswordEmail = ref(true)
const resettingPassword = ref(false)

const disconnectingGoogleCalendar = ref(false)
const googleCalendarDisconnectError = ref('')

/** Google OAuth summary from GET staff (explicit refs so the UI updates reliably). */
const googleCalendarConnected = ref(false)
const googleCalendarEmail = ref(null)

const formData = ref({
  email: '',
  password: '',
  first_name: '',
  last_name: '',
  phone: '',
  photo_url: '',
  bio: '',
  title: '',
  role: 'staff',
  google_calendar_id: '',
  is_active: true,
  display_order: 0
})

// Computed
const isEditing = computed(() => !!props.staffMember)

const isValid = computed(() => {
  const basicValid = formData.value.email &&
    formData.value.first_name &&
    formData.value.last_name &&
    formData.value.role

  if (isEditing.value) {
    return basicValid
  }
  return basicValid && formData.value.password && formData.value.password.length >= 8
})

// Load available services for assignment checkboxes.
const loadServices = async () => {
  try {
    const response = await api.get('services/list?status=active')
    if (response.data.success) {
      services.value = response.data.services
    }
  } catch (err) {
    console.error('Error loading services:', err)
  }
}

// Load full staff details (includes service_assignments).
const loadStaffDetails = async (staffId) => {
  loadingDetails.value = true

  try {
    const response = await api.get(`staff/${staffId}`)
    if (response.data.success) {
      staffDetails.value = response.data.staff
      populateForm(response.data.staff)
    }
  } catch (err) {
    console.error('Error loading staff details:', err)
    errorMessage.value = 'Failed to load staff details.'
  } finally {
    loadingDetails.value = false
  }
}

// Populate form fields from staff data.
const populateForm = (member) => {
  formData.value = {
    email: member.email || '',
    password: '',
    first_name: member.first_name || '',
    last_name: member.last_name || '',
    phone: member.phone || '',
    photo_url: member.photo_url || '',
    bio: member.bio || '',
    title: member.title || '',
    role: member.role || 'staff',
    google_calendar_id: member.google_calendar_id || '',
    is_active: member.is_active ?? true,
    display_order: member.display_order || 0
  }

  if (member.service_assignments) {
    selectedServices.value = member.service_assignments.map(a => a.service_id)
    customPrices.value = {}
    member.service_assignments.forEach(assignment => {
      if (assignment.custom_price) {
        customPrices.value[assignment.service_id] = assignment.custom_price
      }
    })
  }

  if (member.notification_preferences) {
    staffNotificationPrefs.value = { ...staffNotificationPrefs.value, ...member.notification_preferences }
  }

  const rawGcal = member.google_calendar_connected
  googleCalendarConnected.value =
    rawGcal === true ||
    rawGcal === 1 ||
    rawGcal === '1'
  const em = member.google_calendar_email
  googleCalendarEmail.value = em != null && String(em).trim() !== '' ? String(em) : null
}

// Handle service checkbox toggle.
const onServiceToggle = (service) => {
  if (!selectedServices.value.includes(service.id)) {
    delete customPrices.value[service.id]
  }
}

// Handle photo file selection and upload.
const onPhotoSelected = async (event) => {
  const file = event.target.files?.[0]
  if (!file) return

  // Client-side validation before uploading.
  const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp']
  if (!allowedTypes.includes(file.type)) {
    photoUploadError.value = 'Please select a JPG, PNG, GIF, or WebP image.'
    event.target.value = ''
    return
  }
  if (file.size > 5 * 1024 * 1024) {
    photoUploadError.value = 'Image must be 5MB or less.'
    event.target.value = ''
    return
  }

  photoUploadError.value = ''
  uploadingPhoto.value = true

  try {
    const formPayload = new FormData()
    formPayload.append('photo', file)

    // Use fetch directly — axios sets Content-Type: application/json by default.
    // Multipart requires the browser to set Content-Type with the correct boundary,
    // which only works when no Content-Type header is set manually.
    const response = await fetch(
      `${window.BOOKIT_DASHBOARD.apiBase}/staff/${props.staffMember?.id}/photo`,
      {
        method: 'POST',
        headers: {
          'X-WP-Nonce': window.BOOKIT_DASHBOARD.nonce,
          // Do NOT set Content-Type here — let the browser set it with the boundary.
        },
        body: formPayload,
        credentials: 'include',
      }
    )

    const data = await response.json()

    if (data.success && data.url) {
      formData.value.photo_url = data.url
      photoUploadError.value = ''
    } else {
      photoUploadError.value = data.message || 'Upload failed. Please try again.'
    }
  } catch (err) {
    photoUploadError.value = 'Upload failed. Please try again.'
    console.error('Photo upload error:', err)
  } finally {
    uploadingPhoto.value = false
    // Reset input so the same file can be re-selected if needed.
    event.target.value = ''
  }
}

// Get initials from a full name string.
const getInitials = (fullName) => {
  if (!fullName || fullName.trim() === '') return '??'
  const names = fullName.trim().split(' ').filter(n => n)
  if (names.length === 0) return '??'
  if (names.length === 1) {
    return names[0].substring(0, 2).toUpperCase()
  }
  return (names[0][0] + names[names.length - 1][0]).toUpperCase()
}

// Generate a consistent color based on name for avatar backgrounds.
const getColorForInitials = (name) => {
  const colors = [
    '#3B82F6', '#8B5CF6', '#EC4899', '#10B981',
    '#F59E0B', '#EF4444', '#6366F1', '#14B8A6'
  ]

  let hash = 0
  for (let i = 0; i < name.length; i++) {
    hash = name.charCodeAt(i) + ((hash << 5) - hash)
  }

  return colors[Math.abs(hash) % colors.length]
}

// Generate a random 12-character password.
const generatePassword = () => {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%'
  let password = ''
  for (let i = 0; i < 12; i++) {
    password += chars.charAt(Math.floor(Math.random() * chars.length))
  }
  newPassword.value = password
}

// Reset staff member password.
const resetPassword = async () => {
  if (!newPassword.value || newPassword.value.length < 8) return

  resettingPassword.value = true

  try {
    const response = await api.post(`staff/${props.staffMember.id}/reset-password`, {
      new_password: newPassword.value,
      send_email: sendPasswordEmail.value
    })

    if (response.data.success) {
      toastSuccess('Password reset successfully!' + (sendPasswordEmail.value ? ' Email sent.' : ''))
      showPasswordReset.value = false
      newPassword.value = ''
    } else {
      throw new Error(response.data.message || 'Failed to reset password')
    }
  } catch (err) {
    console.error('Error resetting password:', err)
    toastError(err.message || 'Failed to reset password')
  } finally {
    resettingPassword.value = false
  }
}

// Admin: disconnect staff member's Google Calendar (OAuth tokens cleared server-side).
const disconnectGoogleCalendar = async () => {
  if (!props.staffMember?.id || disconnectingGoogleCalendar.value) {
    return
  }

  disconnectingGoogleCalendar.value = true
  googleCalendarDisconnectError.value = ''

  try {
    const response = await api.post(`staff/${props.staffMember.id}/google-calendar/disconnect`, {})

    if (response.data?.success) {
      googleCalendarConnected.value = false
      googleCalendarEmail.value = null
      if (staffDetails.value) {
        staffDetails.value = {
          ...staffDetails.value,
          google_calendar_connected: false,
          google_calendar_email: null
        }
      }
    } else {
      googleCalendarDisconnectError.value = response.data?.message || 'Failed to disconnect Google Calendar.'
    }
  } catch (err) {
    console.error('Error disconnecting Google Calendar:', err)
    googleCalendarDisconnectError.value = err.message || 'Failed to disconnect Google Calendar.'
    toastError(googleCalendarDisconnectError.value)
  } finally {
    disconnectingGoogleCalendar.value = false
  }
}

// Save staff member (create or update).
const saveStaff = async () => {
  if (!isValid.value || saving.value) return

  saving.value = true
  errorMessage.value = ''

  try {
    const service_assignments = selectedServices.value.map(serviceId => ({
      service_id: serviceId,
      custom_price: customPrices.value[serviceId] || null
    }))

    const payload = {
      email: formData.value.email,
      first_name: formData.value.first_name,
      last_name: formData.value.last_name,
      phone: formData.value.phone,
      photo_url: formData.value.photo_url,
      bio: formData.value.bio,
      title: formData.value.title,
      role: formData.value.role,
      google_calendar_id: formData.value.google_calendar_id,
      is_active: formData.value.is_active,
      display_order: formData.value.display_order,
      service_assignments: service_assignments
    }

    if (!isEditing.value) {
      payload.password = formData.value.password
    } else {
      payload.notification_preferences = staffNotificationPrefs.value
    }

    let response
    if (isEditing.value) {
      response = await api.put(`staff/${props.staffMember.id}`, payload)
    } else {
      response = await api.post('staff/create', payload)
    }

    if (response.data.success) {
      emit('saved', response.data.staff)
    } else {
      errorMessage.value = response.data.message || 'Failed to save staff member'
    }
  } catch (err) {
    console.error('Error saving staff:', err)

    if (err.message && err.message.includes('email already exists')) {
      errorMessage.value = 'A staff member with this email already exists. Please use a different email.'
    } else {
      errorMessage.value = err.message || 'Failed to save staff member'
    }
  } finally {
    saving.value = false
  }
}

// Initialize: load services, staff details, and set up focus trap.
onMounted(async () => {
  previousActiveElement.value = document.activeElement
  document.addEventListener('keydown', trapFocus)

  await nextTick()
  const focusable = getFocusableElements()
  if (focusable.length > 0) {
    focusable[0].focus()
  }

  await loadServices()

  if (props.staffMember) {
    await loadStaffDetails(props.staffMember.id)
  } else {
    googleCalendarConnected.value = false
    googleCalendarEmail.value = null
  }
})

onUnmounted(() => {
  document.removeEventListener('keydown', trapFocus)
  if (previousActiveElement.value && previousActiveElement.value.focus) {
    previousActiveElement.value.focus()
  }
})
</script>
