<template>
  <div class="max-w-4xl mx-auto">
    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
      <p class="mt-2 text-sm text-gray-600">Loading profile...</p>
    </div>

    <div v-else class="space-y-6">
      <!-- Success/Error Messages -->
      <div v-if="saveSuccess" role="status" aria-live="polite" class="bg-green-50 border border-green-200 rounded p-3">
        <p class="text-sm text-green-800">&#10003; {{ saveSuccess }}</p>
      </div>
      <div v-if="saveError" role="alert" aria-live="assertive" class="bg-red-50 border border-red-200 rounded p-3">
        <p class="text-sm text-red-800">{{ saveError }}</p>
      </div>
      <div v-if="googleOauthSuccess" role="status" aria-live="polite" class="bg-green-50 border border-green-200 rounded p-3">
        <p class="text-sm text-green-800">&#10003; {{ googleOauthSuccess }}</p>
      </div>
      <div v-if="googleOauthError" role="alert" aria-live="assertive" class="bg-red-50 border border-red-200 rounded p-3">
        <p class="text-sm text-red-800">{{ googleOauthError }}</p>
      </div>

      <!-- Profile Information Card -->
      <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
          <h2 class="text-lg font-semibold text-gray-900">Profile Information</h2>
          <p class="text-sm text-gray-500 mt-1">
            Update your personal details and profile photo
          </p>
        </div>

        <form @submit.prevent="saveProfile" class="px-4 sm:px-6 py-6 space-y-6">
          <!-- Profile Photo -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Profile Photo
            </label>
            <div class="flex items-center gap-4">
              <div class="flex-shrink-0">
                <img
                  v-if="profile.photo_url"
                  :src="profile.photo_url"
                  alt="Profile photo"
                  class="h-20 w-20 rounded-full object-cover border-2 border-gray-200"
                />
                <div
                  v-else
                  class="h-20 w-20 rounded-full flex items-center justify-center text-white font-semibold text-xl border-2 border-gray-200"
                  :style="{ backgroundColor: getColorForInitials(profile.first_name + ' ' + profile.last_name) }"
                >
                  {{ getInitials(profile.first_name + ' ' + profile.last_name) }}
                </div>
              </div>

              <div class="flex-1">
                <button
                  type="button"
                  @click="openMediaLibrary"
                  class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
                >
                  {{ profile.photo_url ? 'Change Photo' : 'Upload Photo' }}
                </button>
                <button
                  v-if="profile.photo_url"
                  type="button"
                  @click="profile.photo_url = ''"
                  class="ml-2 px-4 py-2 text-sm font-medium text-red-600 hover:text-red-700"
                >
                  Remove
                </button>
                <p class="text-xs text-gray-500 mt-1">
                  JPG, PNG or GIF. Max 5MB.
                </p>
              </div>
            </div>
          </div>

          <!-- Name -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label for="profile-first-name" class="block text-sm font-medium text-gray-700 mb-1">
                First Name *
              </label>
              <input
                id="profile-first-name"
                v-model="profile.first_name"
                type="text"
                required
                aria-required="true"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              />
            </div>
            <div>
              <label for="profile-last-name" class="block text-sm font-medium text-gray-700 mb-1">
                Last Name *
              </label>
              <input
                id="profile-last-name"
                v-model="profile.last_name"
                type="text"
                required
                aria-required="true"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              />
            </div>
          </div>

          <!-- Email (with password verification if changed) -->
          <div>
            <label for="profile-email" class="block text-sm font-medium text-gray-700 mb-1">
              Email Address *
            </label>
            <input
              id="profile-email"
              v-model="profile.email"
              type="email"
              required
              aria-required="true"
              @input="emailChanged = profile.email !== originalEmail"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            />

            <div v-if="emailChanged" class="mt-3 bg-amber-50 border border-amber-200 rounded-lg p-4">
              <label class="block text-sm font-medium text-amber-900 mb-2">
                Confirm Current Password *
              </label>
              <input
                v-model="emailPasswordConfirm"
                type="password"
                placeholder="Enter your current password"
                class="w-full px-3 py-2 border border-amber-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
              />
              <p class="text-xs text-amber-700 mt-2">
                For security, we need to verify your password before changing your email address.
              </p>
            </div>
          </div>

          <!-- Phone and Title -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label for="profile-phone" class="block text-sm font-medium text-gray-700 mb-1">
                Phone
              </label>
              <input
                id="profile-phone"
                v-model="profile.phone"
                type="tel"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              />
            </div>
            <div>
              <label for="profile-title" class="block text-sm font-medium text-gray-700 mb-1">
                Job Title
              </label>
              <input
                id="profile-title"
                v-model="profile.title"
                type="text"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              />
            </div>
          </div>

          <!-- Bio -->
          <div>
            <label for="profile-bio" class="block text-sm font-medium text-gray-700 mb-1">
              Bio
            </label>
            <textarea
              id="profile-bio"
              v-model="profile.bio"
              rows="3"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              placeholder="Brief description about yourself..."
            ></textarea>
          </div>

          <!-- Role (Read-Only) -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Role
            </label>
            <div class="flex items-center gap-2">
              <span
                class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg"
                :class="profile.role === 'admin'
                  ? 'bg-purple-100 text-purple-800'
                  : 'bg-blue-100 text-blue-800'"
              >
                {{ profile.role === 'admin' ? 'Admin' : 'Staff' }}
              </span>
              <p class="text-xs text-gray-500">
                Contact an administrator to change your role
              </p>
            </div>
          </div>

          <!-- Save Button -->
          <div class="flex justify-end pt-4 border-t border-gray-200">
            <button
              type="submit"
              :disabled="savingProfile"
              class="w-full sm:w-auto px-4 py-2.5 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 disabled:opacity-50"
            >
              {{ savingProfile ? 'Saving...' : 'Save Profile' }}
            </button>
          </div>
        </form>
      </div>

      <!-- Change Password Card -->
      <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
          <h2 class="text-lg font-semibold text-gray-900">Change Password</h2>
          <p class="text-sm text-gray-500 mt-1">
            Update your password to keep your account secure
          </p>
        </div>

        <form @submit.prevent="changePassword" class="px-4 sm:px-6 py-6 space-y-4">
          <!-- Current Password -->
          <div>
            <label for="current-password" class="block text-sm font-medium text-gray-700 mb-1">
              Current Password *
            </label>
            <input
              id="current-password"
              v-model="passwordForm.current_password"
              type="password"
              required
              aria-required="true"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              :class="{ 'border-red-500': passwordError }"
            />
          </div>

          <!-- New Password -->
          <div>
            <label for="new-password" class="block text-sm font-medium text-gray-700 mb-1">
              New Password *
            </label>
            <input
              id="new-password"
              v-model="passwordForm.new_password"
              type="password"
              required
              aria-required="true"
              minlength="8"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            />
            <p class="text-xs text-gray-500 mt-1">
              Minimum 8 characters
            </p>
          </div>

          <!-- Confirm New Password -->
          <div>
            <label for="confirm-password" class="block text-sm font-medium text-gray-700 mb-1">
              Confirm New Password *
            </label>
            <input
              id="confirm-password"
              v-model="passwordForm.confirm_password"
              type="password"
              required
              aria-required="true"
              minlength="8"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              :class="{ 'border-red-500': passwordMismatch }"
            />
            <p v-if="passwordMismatch" class="text-xs text-red-600 mt-1">
              Passwords do not match
            </p>
          </div>

          <!-- Password Error -->
          <div v-if="passwordError" role="alert" aria-live="assertive" class="bg-red-50 border border-red-200 rounded p-3">
            <p class="text-sm text-red-800">{{ passwordError }}</p>
          </div>

          <!-- Password Success -->
          <div v-if="passwordSuccess" role="status" aria-live="polite" class="bg-green-50 border border-green-200 rounded p-3">
            <p class="text-sm text-green-800">&#10003; {{ passwordSuccess }}</p>
          </div>

          <!-- Change Password Button -->
          <div class="flex justify-end pt-4 border-t border-gray-200">
            <button
              type="submit"
              :disabled="changingPassword || passwordMismatch"
              class="w-full sm:w-auto px-4 py-2.5 text-sm font-medium text-white bg-amber-600 rounded-lg hover:bg-amber-700 disabled:opacity-50"
            >
              {{ changingPassword ? 'Changing...' : 'Change Password' }}
            </button>
          </div>
        </form>
      </div>

      <!-- Notification Preferences Card -->
      <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
          <h2 class="text-lg font-semibold text-gray-900">Notification Preferences</h2>
          <p class="text-sm text-gray-500 mt-1">
            Control when you receive email notifications about your bookings
          </p>
        </div>

        <form @submit.prevent="savePreferences" class="px-4 sm:px-6 py-6 space-y-5">

          <!-- New Booking -->
          <div class="flex items-center justify-between">
            <label class="text-sm font-medium text-gray-700">New Booking</label>
            <select
              v-model="notificationPrefs.new_booking"
              class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
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
              v-model="notificationPrefs.reschedule"
              class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
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
              v-model="notificationPrefs.cancellation"
              class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
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
                Receive a summary of today's bookings each morning
              </p>
            </div>
            <button
              type="button"
              role="switch"
              :aria-checked="notificationPrefs.daily_schedule"
              @click="notificationPrefs.daily_schedule = !notificationPrefs.daily_schedule"
              class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 mt-0.5"
              :class="notificationPrefs.daily_schedule ? 'bg-primary-600' : 'bg-gray-200'"
            >
              <span
                class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                :class="notificationPrefs.daily_schedule ? 'translate-x-5' : 'translate-x-0'"
              />
            </button>
          </div>

          <!-- Error / Success -->
          <div v-if="prefsError" role="alert" aria-live="assertive" class="bg-red-50 border border-red-200 rounded p-3">
            <p class="text-sm text-red-800">{{ prefsError }}</p>
          </div>
          <div v-if="prefsSuccess" role="status" aria-live="polite" class="bg-green-50 border border-green-200 rounded p-3">
            <p class="text-sm text-green-800">&#10003; {{ prefsSuccess }}</p>
          </div>

          <!-- Save Button -->
          <div class="flex justify-end pt-4 border-t border-gray-200">
            <button
              type="submit"
              :disabled="savingPrefs"
              class="w-full sm:w-auto px-4 py-2.5 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 disabled:opacity-50"
            >
              {{ savingPrefs ? 'Saving...' : 'Save Preferences' }}
            </button>
          </div>

        </form>
      </div>

      <!-- Google Calendar -->
      <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
          <h2 class="text-lg font-semibold text-gray-900">Google Calendar</h2>
          <p class="text-sm text-gray-500 mt-1">
            Sync your bookings to your Google Calendar automatically
          </p>
        </div>
        <div class="px-4 sm:px-6 py-6 space-y-4">
          <div v-if="googleCalError" role="alert" class="bg-red-50 border border-red-200 rounded p-3">
            <p class="text-sm text-red-800">{{ googleCalError }}</p>
          </div>

          <div v-if="profile.google_calendar_connected" class="space-y-3">
            <div class="flex items-start gap-2">
              <span class="mt-1 h-2 w-2 rounded-full bg-green-500 flex-shrink-0" aria-hidden="true" />
              <div>
                <p class="text-sm font-medium text-gray-900">
                  Connected<span v-if="profile.google_calendar_email"> ({{ profile.google_calendar_email }})</span>
                </p>
              </div>
            </div>
            <button
              type="button"
              :disabled="googleCalLoading"
              class="px-4 py-2 text-sm font-medium text-red-700 bg-white border border-red-300 rounded-lg hover:bg-red-50 disabled:opacity-50"
              @click="disconnectGoogleCalendar"
            >
              {{ googleCalLoading ? 'Working…' : 'Disconnect' }}
            </button>
          </div>

          <div v-else class="space-y-3">
            <div class="flex items-start gap-2">
              <span class="mt-1 h-2 w-2 rounded-full border-2 border-gray-300 flex-shrink-0" aria-hidden="true" />
              <p class="text-sm text-gray-700">Not connected</p>
            </div>
            <button
              type="button"
              :disabled="googleCalLoading"
              class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 disabled:opacity-50"
              @click="connectGoogleCalendar"
            >
              {{ googleCalLoading ? 'Connecting…' : 'Connect Google Calendar' }}
            </button>
          </div>
        </div>
      </div>

      <!-- My Stats Section -->
      <div v-if="showStats" class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
          <h2 class="text-base sm:text-lg font-semibold text-gray-900">My Stats</h2>
          <p class="text-sm text-gray-500 mt-1">Your booking performance</p>
        </div>
        <div class="px-4 sm:px-6 py-6">
          <!-- Loading state -->
          <div v-if="statsLoading" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="h-24 bg-gray-100 animate-pulse rounded-lg"></div>
            <div class="h-24 bg-gray-100 animate-pulse rounded-lg"></div>
            <div class="h-24 bg-gray-100 animate-pulse rounded-lg"></div>
          </div>

          <!-- Stats tiles -->
          <div v-else class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div
              v-for="(stat, key) in stats"
              :key="key"
              class="bg-gray-50 rounded-lg p-4 border border-gray-200"
            >
              <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">
                {{ stat.period_label }}
              </p>
              <p class="text-2xl font-bold text-gray-900 mt-1">
                {{ stat.booking_count }}
                <span class="text-sm font-normal text-gray-500">bookings</span>
              </p>
              <p class="text-lg font-semibold text-primary-600 mt-1">
                £{{ formatCurrency(stat.revenue) }}
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useApi } from '../composables/useApi'

const api = useApi()
const route = useRoute()
const router = useRouter()

const loading = ref(false)
const savingProfile = ref(false)
const changingPassword = ref(false)
const saveSuccess = ref('')
const saveError = ref('')
const passwordSuccess = ref('')
const passwordError = ref('')
const originalEmail = ref('')
const emailChanged = ref(false)
const emailPasswordConfirm = ref('')
const showStats = ref(false)
const statsLoading = ref(false)
const stats = ref(null)

const savingPrefs = ref(false)
const prefsSuccess = ref('')
const prefsError = ref('')
const googleOauthSuccess = ref('')
const googleOauthError = ref('')
const googleCalLoading = ref(false)
const googleCalError = ref('')

const notificationPrefs = ref({
  new_booking: 'immediate',
  reschedule: 'immediate',
  cancellation: 'immediate',
  daily_schedule: false
})

const profile = ref({
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  title: '',
  bio: '',
  photo_url: '',
  role: '',
  google_calendar_connected: false,
  google_calendar_email: ''
})

const passwordForm = ref({
  current_password: '',
  new_password: '',
  confirm_password: ''
})

const passwordMismatch = computed(() => {
  return passwordForm.value.new_password &&
         passwordForm.value.confirm_password &&
         passwordForm.value.new_password !== passwordForm.value.confirm_password
})

const loadProfile = async () => {
  loading.value = true

  try {
    const response = await api.get('profile')

    if (response.data.success) {
      profile.value = response.data.profile
      originalEmail.value = response.data.profile.email

      if (response.data.profile.notification_preferences) {
        notificationPrefs.value = response.data.profile.notification_preferences
      }
      if (typeof response.data.profile.google_calendar_connected !== 'undefined') {
        profile.value.google_calendar_connected = Boolean(response.data.profile.google_calendar_connected)
      }
      if (typeof response.data.profile.google_calendar_email !== 'undefined') {
        profile.value.google_calendar_email = response.data.profile.google_calendar_email || ''
      }
    }
  } catch (err) {
    saveError.value = 'Failed to load profile.'
  } finally {
    loading.value = false
  }
}

const savePreferences = async () => {
  savingPrefs.value = true
  prefsSuccess.value = ''
  prefsError.value = ''

  try {
    const response = await api.put('profile/notification-preferences', {
      new_booking: notificationPrefs.value.new_booking,
      reschedule: notificationPrefs.value.reschedule,
      cancellation: notificationPrefs.value.cancellation,
      daily_schedule: notificationPrefs.value.daily_schedule
    })

    if (response.data.success) {
      prefsSuccess.value = 'Preferences saved.'
      setTimeout(() => { prefsSuccess.value = '' }, 3000)
    } else {
      prefsError.value = response.data.message || 'Failed to save preferences.'
    }
  } catch (err) {
    prefsError.value = err.message || 'Failed to save preferences.'
  } finally {
    savingPrefs.value = false
  }
}

const saveProfile = async () => {
  saveSuccess.value = ''
  saveError.value = ''

  if (emailChanged.value) {
    if (!emailPasswordConfirm.value) {
      saveError.value = 'Please enter your current password to change your email address.'
      return
    }

    try {
      await api.post('profile/verify-password', {
        password: emailPasswordConfirm.value
      })
    } catch (err) {
      saveError.value = 'Current password is incorrect. Email not changed.'
      return
    }
  }

  savingProfile.value = true

  try {
    const response = await api.put('profile', {
      first_name: profile.value.first_name,
      last_name: profile.value.last_name,
      email: profile.value.email,
      phone: profile.value.phone,
      title: profile.value.title,
      bio: profile.value.bio,
      photo_url: profile.value.photo_url
    })

    if (response.data.success) {
      saveSuccess.value = response.data.message
      profile.value = response.data.profile
      originalEmail.value = profile.value.email
      emailChanged.value = false
      emailPasswordConfirm.value = ''

      setTimeout(() => {
        saveSuccess.value = ''
      }, 3000)
    } else {
      saveError.value = response.data.message || 'Failed to save profile'
    }
  } catch (err) {
    if (err.code === 'duplicate_email') {
      saveError.value = 'This email is already in use by another staff member.'
    } else {
      saveError.value = err.message || 'Failed to save profile'
    }
  } finally {
    savingProfile.value = false
  }
}

const changePassword = async () => {
  if (passwordMismatch.value) return

  changingPassword.value = true
  passwordSuccess.value = ''
  passwordError.value = ''

  try {
    const response = await api.post('profile/change-password', {
      current_password: passwordForm.value.current_password,
      new_password: passwordForm.value.new_password
    })

    if (response.data.success) {
      passwordSuccess.value = response.data.message

      passwordForm.value = {
        current_password: '',
        new_password: '',
        confirm_password: ''
      }

      setTimeout(() => {
        passwordSuccess.value = ''
      }, 5000)
    } else {
      passwordError.value = response.data.message || 'Failed to change password'
    }
  } catch (err) {
    if (err.code === 'invalid_password') {
      passwordError.value = 'Current password is incorrect.'
    } else {
      passwordError.value = err.message || 'Failed to change password'
    }
  } finally {
    changingPassword.value = false
  }
}

const formatCurrency = (value) => {
  return Number(value).toLocaleString('en-GB', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  })
}

const loadStats = async () => {
  // Determine the current user's role from the staff prop already available
  // in this component. Use the exact same prop/variable name already used
  // elsewhere in MyProfile.vue to access the staff role.
  const isAdmin = profile.value?.role === 'admin'

  if (isAdmin) {
    // Admin: check the setting before calling my-stats to avoid a 403.
    try {
      const settingResponse = await api.get('settings?keys=show_staff_earnings')
      const enabled = settingResponse.data?.settings?.show_staff_earnings
      if (!enabled) {
        showStats.value = false
        return
      }
    } catch (err) {
      showStats.value = false
      return
    }
  }

  // For staff (and admin when setting is enabled): call my-stats directly.
  // The backend returns 403 if earnings are hidden — handle that silently.
  statsLoading.value = true
  try {
    const response = await api.get('my-stats')
    if (response.data.success) {
      stats.value = response.data.stats
      showStats.value = true
    }
  } catch (err) {
    // err.status (not err.response?.status) — useApi.js interceptor maps
    // HTTP status onto err.status directly.
    if (err.status !== 403) {
      console.error('Failed to load stats:', err)
    }
    showStats.value = false
  } finally {
    statsLoading.value = false
  }
}

const openMediaLibrary = () => {
  if (typeof wp !== 'undefined' && wp.media) {
    const mediaFrame = wp.media({
      title: 'Select Profile Photo',
      button: { text: 'Use this photo' },
      multiple: false,
      library: { type: 'image' }
    })

    mediaFrame.on('select', () => {
      const attachment = mediaFrame.state().get('selection').first().toJSON()
      profile.value.photo_url = attachment.url
    })

    mediaFrame.open()
  } else {
    const url = prompt('WordPress media library not available.\nEnter image URL manually:')
    if (url) {
      profile.value.photo_url = url
    }
  }
}

const getInitials = (fullName) => {
  if (!fullName || fullName.trim() === '') return '??'
  const names = fullName.trim().split(' ').filter(n => n)
  if (names.length === 0) return '??'
  if (names.length === 1) return names[0].substring(0, 2).toUpperCase()
  return (names[0][0] + names[names.length - 1][0]).toUpperCase()
}

const clearGoogleOAuthQueryParams = () => {
  const q = { ...route.query }
  delete q.google_connected
  delete q.google_error
  router.replace({ path: route.path, query: q })
}

const connectGoogleCalendar = async () => {
  googleCalError.value = ''
  googleCalLoading.value = true
  try {
    const restBase = window.BOOKIT_DASHBOARD?.restBase || ''
    const response = await api.get(`${restBase}google-calendar/auth-url`)
    const url = response.data?.url
    if (url) {
      window.location.href = url
    } else {
      googleCalError.value = 'Could not start Google Calendar connection.'
    }
  } catch (err) {
    googleCalError.value = err.message || 'Failed to connect Google Calendar.'
  } finally {
    googleCalLoading.value = false
  }
}

const disconnectGoogleCalendar = async () => {
  googleCalError.value = ''
  googleCalLoading.value = true
  try {
    const response = await api.post('profile/google-calendar/disconnect')
    if (response.data?.success) {
      profile.value.google_calendar_connected = false
      profile.value.google_calendar_email = ''
    } else {
      googleCalError.value = 'Could not disconnect Google Calendar.'
    }
  } catch (err) {
    googleCalError.value = err.message || 'Failed to disconnect.'
  } finally {
    googleCalLoading.value = false
  }
}

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

onMounted(() => {
  loadProfile()
    .then(() => {
      if (route.query.google_connected === '1') {
        googleOauthSuccess.value = 'Google Calendar connected successfully'
        clearGoogleOAuthQueryParams()
        setTimeout(() => { googleOauthSuccess.value = '' }, 5000)
      }
      if (route.query.google_error === '1') {
        googleOauthError.value = 'Google Calendar connection failed. Please try again.'
        clearGoogleOAuthQueryParams()
        setTimeout(() => { googleOauthError.value = '' }, 8000)
      }
    })
  loadStats()
})
</script>
