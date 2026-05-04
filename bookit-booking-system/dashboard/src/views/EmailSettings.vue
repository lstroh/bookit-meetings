<template>
  <div class="max-w-4xl mx-auto">
    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
      <p class="mt-2 text-sm text-gray-600">Loading email settings...</p>
    </div>

    <div v-else class="space-y-6">
      <!-- Success/Error Messages -->
      <div v-if="saveSuccess" class="bg-green-50 border border-green-200 rounded p-3">
        <p class="text-sm text-green-800">&#10003; {{ saveSuccess }}</p>
      </div>
      <div v-if="saveError" class="bg-red-50 border border-red-200 rounded p-3">
        <p class="text-sm text-red-800">{{ saveError }}</p>
      </div>

      <!-- Top Warning Banner -->
      <div v-if="emailProvider === 'wp_mail'" class="bg-amber-50 border border-amber-200 rounded-lg p-4 flex items-start gap-3">
        <span class="text-amber-500 text-xl flex-shrink-0">⚠️</span>
        <p class="text-sm text-amber-800">
          <strong>Using WordPress Mail.</strong> Emails may not be delivered reliably in production.
          Configure Brevo above for reliable delivery.
        </p>
      </div>

      <!-- Section 1: Email Provider -->
      <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
          <h2 class="text-lg font-semibold text-gray-900">Email Provider</h2>
        </div>
        <form @submit.prevent="saveSettings" class="px-4 sm:px-6 py-6 space-y-6">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Email Provider</label>
            <select
              v-model="emailProvider"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            >
              <option value="wp_mail">WordPress Mail (default — no API key needed)</option>
              <option value="brevo">Brevo (recommended for production)</option>
            </select>
          </div>

          <div v-if="emailProvider === 'wp_mail'" class="bg-amber-50 border border-amber-200 rounded p-4">
            <p class="text-sm text-amber-800">
              ⚠️ WordPress Mail uses your server's PHP mail() function. Emails may arrive in spam.
              Recommended for testing only. Configure Brevo for reliable production delivery.
            </p>
          </div>

          <div v-if="emailProvider === 'brevo'" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Brevo API Key</label>
              <input
                v-model="brevoApiKey"
                type="password"
                placeholder="xkeysib-..."
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              />
              <div class="mt-2 flex items-center gap-2 text-sm">
                <span
                  :class="brevoConfigured ? 'bg-green-500' : 'bg-gray-400'"
                  class="inline-block w-2 h-2 rounded-full"
                ></span>
                <span class="text-gray-700">
                  {{ brevoConfigured ? 'Connected' : 'API key required' }}
                </span>
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">From Name</label>
              <input
                v-model="brevoFromName"
                type="text"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">From Email</label>
              <input
                v-model="brevoFromEmail"
                type="email"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              />
            </div>

            <div class="pt-4 border-t border-gray-200 space-y-4">
              <div>
                <h3 class="text-base font-semibold text-gray-900">Brevo Email Templates</h3>
                <p class="text-sm text-gray-500 mt-1">
                  Enter the numeric template ID from your Brevo dashboard for each
                  notification type. Leave blank to use the default HTML email.
                </p>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Customer booking confirmation</label>
                <input
                  v-model="settings.brevo_template_booking_confirmed"
                  type="number"
                  min="1"
                  step="1"
                  placeholder=""
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                />
                <p class="text-xs text-gray-500 mt-1">Brevo template ID for booking confirmations</p>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Customer cancellation confirmation</label>
                <input
                  v-model="settings.brevo_template_booking_cancelled"
                  type="number"
                  min="1"
                  step="1"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                />
                <p class="text-xs text-gray-500 mt-1">Brevo template ID for cancellation confirmations</p>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Customer reschedule confirmation</label>
                <input
                  v-model="settings.brevo_template_booking_rescheduled"
                  type="number"
                  min="1"
                  step="1"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                />
                <p class="text-xs text-gray-500 mt-1">Brevo template ID for reschedule confirmations</p>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Magic link cancellation email</label>
                <input
                  v-model="settings.brevo_template_magic_link_cancel"
                  type="number"
                  min="1"
                  step="1"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                />
                <p class="text-xs text-gray-500 mt-1">Brevo template ID for magic link cancellation emails</p>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Magic link reschedule email</label>
                <input
                  v-model="settings.brevo_template_magic_link_reschedule"
                  type="number"
                  min="1"
                  step="1"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                />
                <p class="text-xs text-gray-500 mt-1">Brevo template ID for magic link reschedule emails</p>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Business/staff new booking alert</label>
                <input
                  v-model="settings.brevo_template_business_notification"
                  type="number"
                  min="1"
                  step="1"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                />
                <p class="text-xs text-gray-500 mt-1">Brevo template ID for business new-booking alerts</p>
              </div>

              <!-- Staff Notification Templates -->
              <div class="pt-4 border-t border-gray-200">
                <h4 class="text-sm font-semibold text-gray-800 mb-3">Staff Notifications</h4>
                <div class="space-y-4">

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Staff: New booking assigned</label>
                    <input v-model="settings.brevo_template_staff_new_booking" type="number" min="1" step="1"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" />
                    <p class="text-xs text-gray-500 mt-1">Brevo template ID for staff new booking notification</p>
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Staff: Booking rescheduled</label>
                    <input v-model="settings.brevo_template_staff_reschedule" type="number" min="1" step="1"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" />
                    <p class="text-xs text-gray-500 mt-1">Brevo template ID for staff reschedule notification</p>
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Staff: Booking cancelled</label>
                    <input v-model="settings.brevo_template_staff_cancellation" type="number" min="1" step="1"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" />
                    <p class="text-xs text-gray-500 mt-1">Brevo template ID for staff cancellation notification</p>
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Staff: Booking assigned to you</label>
                    <input v-model="settings.brevo_template_staff_reassigned_to" type="number" min="1" step="1"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" />
                    <p class="text-xs text-gray-500 mt-1">Brevo template ID for staff reassignment (new assignee)</p>
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Staff: Booking removed from schedule</label>
                    <input v-model="settings.brevo_template_staff_reassigned_away" type="number" min="1" step="1"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" />
                    <p class="text-xs text-gray-500 mt-1">Brevo template ID for staff reassignment (previous assignee)</p>
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Staff: Daily digest</label>
                    <input v-model="settings.brevo_template_staff_daily_digest" type="number" min="1" step="1"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" />
                    <p class="text-xs text-gray-500 mt-1">Brevo template ID for staff daily event digest</p>
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Staff: Weekly digest</label>
                    <input v-model="settings.brevo_template_staff_weekly_digest" type="number" min="1" step="1"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" />
                    <p class="text-xs text-gray-500 mt-1">Brevo template ID for staff weekly event digest</p>
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Staff: Daily schedule summary</label>
                    <input v-model="settings.brevo_template_staff_daily_schedule" type="number" min="1" step="1"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" />
                    <p class="text-xs text-gray-500 mt-1">Brevo template ID for staff daily schedule summary</p>
                  </div>

                </div>
              </div>
            </div>
          </div>

          <div class="flex justify-end pt-4 border-t border-gray-200">
            <button
              type="submit"
              :disabled="saving"
              class="w-full sm:w-auto px-4 py-2.5 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 disabled:opacity-50"
            >
              {{ saving ? 'Saving...' : 'Save SMTP Settings' }}
            </button>
          </div>
        </form>
      </div>

      <!-- Staff Notification Timing -->
      <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
          <h2 class="text-lg font-semibold text-gray-900">Staff Notification Timing</h2>
          <p class="text-sm text-gray-500 mt-1">
            Configure when digest and schedule emails are sent to staff members
          </p>
        </div>

        <div class="px-4 sm:px-6 py-6 space-y-5">
          <!-- Digest Email Send Time -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Digest Email Send Time
            </label>
            <input
              v-model="settings.staff_digest_send_time"
              type="time"
              class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            />
            <p class="text-xs text-gray-500 mt-1">
              Time of day for daily and weekly digest emails (business timezone)
            </p>
          </div>

          <!-- Daily Schedule Email Send Time -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Daily Schedule Email Send Time
            </label>
            <input
              v-model="settings.staff_schedule_send_time"
              type="time"
              class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            />
            <p class="text-xs text-gray-500 mt-1">
              Time of day for the daily schedule summary email (business timezone)
            </p>
          </div>

          <!-- Weekly Digest Day -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Weekly Digest Day
            </label>
            <select
              v-model.number="settings.staff_digest_weekly_day"
              class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            >
              <option :value="1">Monday</option>
              <option :value="2">Tuesday</option>
              <option :value="3">Wednesday</option>
              <option :value="4">Thursday</option>
              <option :value="5">Friday</option>
              <option :value="6">Saturday</option>
              <option :value="7">Sunday</option>
            </select>
            <p class="text-xs text-gray-500 mt-1">
              Day of the week when weekly digest emails are sent
            </p>
          </div>

          <!-- Save Button -->
          <div class="flex justify-end pt-4 border-t border-gray-200">
            <button
              type="button"
              :disabled="saving"
              @click="saveSettings"
              class="w-full sm:w-auto px-4 py-2.5 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 disabled:opacity-50"
            >
              {{ saving ? 'Saving...' : 'Save Notification Timing' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Section 2: SMTP Configuration (Advanced) -->
      <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
          <div class="flex items-center justify-between">
            <div>
              <h2 class="text-lg font-semibold text-gray-900">SMTP Configuration (Advanced)</h2>
              <p class="text-sm text-gray-500 mt-1">
                Configure your email server for sending notifications
              </p>
            </div>
            <label class="flex items-center cursor-pointer">
              <input
                v-model="settings.smtp_enabled"
                type="checkbox"
                class="sr-only peer"
              />
              <div class="relative w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-600"></div>
              <span class="ml-3 text-sm font-medium text-gray-900">
                {{ settings.smtp_enabled ? 'Enabled' : 'Disabled' }}
              </span>
            </label>
          </div>
        </div>

        <form @submit.prevent="saveSettings" class="px-4 sm:px-6 py-6 space-y-6">
          <div class="bg-blue-50 border border-blue-200 rounded p-4">
            <div class="flex items-start gap-3">
              <span class="text-blue-600 text-xl flex-shrink-0">&#8505;&#65039;</span>
              <div class="flex-1 text-sm text-blue-800">
                <p class="font-medium mb-1">SMTP Configuration Required</p>
                <p>
                  WordPress uses PHP mail() by default, which often fails or goes to spam.
                  Configure SMTP for reliable email delivery.
                </p>
                <p class="mt-2">
                  <strong>Popular providers:</strong> Gmail (smtp.gmail.com:587),
                  SendGrid, Mailgun, Amazon SES
                </p>
              </div>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="sm:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1">
                SMTP Host *
              </label>
              <input
                v-model="settings.smtp_host"
                type="text"
                required
                placeholder="smtp.gmail.com"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Port *
              </label>
              <input
                v-model.number="settings.smtp_port"
                type="number"
                required
                placeholder="587"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              />
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Encryption
            </label>
            <select
              v-model="settings.smtp_encryption"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            >
              <option value="">None</option>
              <option value="tls">TLS (recommended)</option>
              <option value="ssl">SSL</option>
            </select>
            <p class="text-xs text-gray-500 mt-1">
              Use TLS for port 587, SSL for port 465
            </p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Username *
              </label>
              <input
                v-model="settings.smtp_username"
                type="text"
                required
                placeholder="your-email@gmail.com"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Password *
              </label>
              <input
                v-model="settings.smtp_password"
                type="password"
                required
                placeholder="••••••••"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              />
              <p class="text-xs text-gray-500 mt-1">
                For Gmail, use an App Password, not your account password
              </p>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                From Name *
              </label>
              <input
                v-model="settings.smtp_from_name"
                type="text"
                required
                placeholder="My Business"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                From Email *
              </label>
              <input
                v-model="settings.smtp_from_email"
                type="email"
                required
                placeholder="noreply@mybusiness.com"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              />
              <p class="text-xs text-gray-500 mt-1">
                Should match or be authorized by your SMTP host
              </p>
            </div>
          </div>

          <div class="flex justify-end pt-4 border-t border-gray-200">
            <button
              type="submit"
              :disabled="saving"
              class="w-full sm:w-auto px-4 py-2.5 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 disabled:opacity-50"
            >
              {{ saving ? 'Saving...' : 'Save SMTP Settings' }}
            </button>
          </div>
        </form>
      </div>

      <!-- Section 3: SMS Notifications -->
      <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
          <h2 class="text-lg font-semibold text-gray-900">SMS Notifications</h2>
        </div>
        <form @submit.prevent="saveSettings" class="px-4 sm:px-6 py-6 space-y-6">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">SMS Provider</label>
            <select
              v-model="smsProvider"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            >
              <option value="none">Disabled</option>
              <option value="brevo">Brevo SMS (coming soon)</option>
            </select>
          </div>

          <div v-if="smsProvider === 'brevo'" class="space-y-4">
            <div class="bg-blue-50 border border-blue-200 rounded p-4 text-sm text-blue-800">
              Brevo SMS will be activated in a future sprint when live credentials are available.
              Save your selection now to enable it automatically.
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Brevo SMS API Key</label>
              <input
                v-model="brevoSmsApiKey"
                type="password"
                disabled
                placeholder="Brevo SMS API key — available in Sprint 5"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-100 text-gray-500 cursor-not-allowed"
              />
            </div>
          </div>

          <div class="flex justify-end pt-4 border-t border-gray-200">
            <button
              type="submit"
              :disabled="saving"
              class="w-full sm:w-auto px-4 py-2.5 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 disabled:opacity-50"
            >
              {{ saving ? 'Saving...' : 'Save SMTP Settings' }}
            </button>
          </div>
        </form>
      </div>

      <!-- Section 4: Test Notifications -->
      <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
          <h2 class="text-lg font-semibold text-gray-900">Test Notifications</h2>
          <p class="text-sm text-gray-500 mt-1">
            Send test notifications to verify configuration and provider routing
          </p>
        </div>

        <div class="px-4 sm:px-6 py-6">
          <div v-if="testSuccess" class="mb-4 bg-green-50 border border-green-200 rounded p-3">
            <p class="text-sm text-green-800">&#10003; {{ testSuccess }}</p>
          </div>

          <div v-if="testError" class="mb-4 bg-red-50 border border-red-200 rounded p-3">
            <p class="text-sm text-red-800">{{ testError }}</p>
          </div>

          <div v-if="!settings.smtp_enabled" class="mb-4 bg-amber-50 border border-amber-200 rounded p-3">
            <p class="text-sm text-amber-800">
              SMTP is currently disabled. Enable it above to use custom SMTP settings.
              Test email will use WordPress default (PHP mail).
            </p>
          </div>

          <form @submit.prevent="sendTestEmail" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Send Test Email To
              </label>
              <input
                v-model="testEmailAddress"
                type="email"
                required
                placeholder="your-email@example.com"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              />
            </div>

            <div class="flex flex-col sm:flex-row sm:justify-end gap-3">
              <button
                type="submit"
                :disabled="sendingTest || !testEmailAddress"
                class="w-full sm:w-auto px-4 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50"
              >
                {{ sendingTest ? 'Sending...' : 'Send Test Email' }}
              </button>
              <button
                type="button"
                disabled
                title="SMS not yet active"
                class="w-full sm:w-auto px-4 py-2.5 text-sm font-medium text-gray-600 bg-gray-200 rounded-lg cursor-not-allowed"
              >
                Send Test SMS
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Help Card -->
      <div class="bg-gray-50 border border-gray-200 rounded-lg p-6">
        <h3 class="text-sm font-semibold text-gray-900 mb-3">
          Quick Setup Guides
        </h3>

        <div class="space-y-3 text-sm text-gray-700">
          <div>
            <p class="font-medium">Gmail:</p>
            <p class="text-xs text-gray-600">
              Host: smtp.gmail.com, Port: 587, Encryption: TLS<br />
              Use App Password (not account password):
              <a
                href="https://support.google.com/accounts/answer/185833"
                target="_blank"
                rel="noopener noreferrer"
                class="text-primary-600 hover:underline"
              >
                Create App Password
              </a>
            </p>
          </div>

          <div>
            <p class="font-medium">SendGrid:</p>
            <p class="text-xs text-gray-600">
              Host: smtp.sendgrid.net, Port: 587, Encryption: TLS<br />
              Username: apikey, Password: Your SendGrid API Key
            </p>
          </div>

          <div>
            <p class="font-medium">Mailgun:</p>
            <p class="text-xs text-gray-600">
              Host: smtp.mailgun.org, Port: 587, Encryption: TLS<br />
              Find credentials in Mailgun dashboard under Domain Settings
            </p>
          </div>
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
const sendingTest = ref(false)
const saveSuccess = ref('')
const saveError = ref('')
const testSuccess = ref('')
const testError = ref('')
const testEmailAddress = ref('')
const emailProvider = ref('wp_mail')
const smsProvider = ref('none')
const brevoApiKey = ref('')
const brevoFromName = ref('')
const brevoFromEmail = ref('')
const brevoSmsApiKey = ref('')
const brevoConfigured = ref(false)

const settings = ref({
  smtp_enabled: false,
  smtp_host: '',
  smtp_port: 587,
  smtp_encryption: 'tls',
  smtp_username: '',
  smtp_password: '',
  smtp_from_name: '',
  smtp_from_email: '',
  staff_digest_send_time: '18:00',
  staff_schedule_send_time: '08:00',
  staff_digest_weekly_day: 1,
  brevo_template_booking_confirmed: '',
  brevo_template_booking_cancelled: '',
  brevo_template_booking_rescheduled: '',
  brevo_template_magic_link_cancel: '',
  brevo_template_magic_link_reschedule: '',
  brevo_template_business_notification: '',
  brevo_template_staff_new_booking: '',
  brevo_template_staff_reschedule: '',
  brevo_template_staff_cancellation: '',
  brevo_template_staff_reassigned_to: '',
  brevo_template_staff_reassigned_away: '',
  brevo_template_staff_daily_digest: '',
  brevo_template_staff_weekly_digest: '',
  brevo_template_staff_daily_schedule: ''
})

const SETTING_KEYS = 'smtp_enabled,smtp_host,smtp_port,smtp_encryption,smtp_username,smtp_password,smtp_from_name,smtp_from_email,email_provider,brevo_api_key,brevo_from_name,brevo_from_email,brevo_template_booking_confirmed,brevo_template_booking_cancelled,brevo_template_booking_rescheduled,brevo_template_magic_link_cancel,brevo_template_magic_link_reschedule,brevo_template_business_notification,brevo_template_staff_new_booking,brevo_template_staff_reschedule,brevo_template_staff_cancellation,brevo_template_staff_reassigned_to,brevo_template_staff_reassigned_away,brevo_template_staff_daily_digest,brevo_template_staff_weekly_digest,brevo_template_staff_daily_schedule,sms_provider,brevo_sms_api_key,staff_digest_send_time,staff_schedule_send_time,staff_digest_weekly_day'

const loadSettings = async () => {
  loading.value = true

  try {
    const response = await api.get(`settings?keys=${SETTING_KEYS}`)

    if (response.data.success && response.data.settings) {
      Object.assign(settings.value, response.data.settings)
      emailProvider.value = response.data.settings.email_provider || 'wp_mail'
      smsProvider.value = response.data.settings.sms_provider || 'none'
      brevoApiKey.value = response.data.settings.brevo_api_key === 'SAVED' ? '' : (response.data.settings.brevo_api_key || '')
      brevoFromName.value = response.data.settings.brevo_from_name || ''
      brevoFromEmail.value = response.data.settings.brevo_from_email || ''
      brevoSmsApiKey.value = response.data.settings.brevo_sms_api_key === 'SAVED' ? '' : (response.data.settings.brevo_sms_api_key || '')
      brevoConfigured.value = response.data.settings.brevo_api_key === 'SAVED'
    }
  } catch (err) {
    saveError.value = 'Failed to load settings.'
  } finally {
    loading.value = false
  }
}

const saveSettings = async () => {
  saving.value = true
  saveSuccess.value = ''
  saveError.value = ''

  try {
    const payload = {
      ...settings.value,
      email_provider: emailProvider.value,
      brevo_from_name: brevoFromName.value,
      brevo_from_email: brevoFromEmail.value,
      sms_provider: smsProvider.value
    }

    if (brevoApiKey.value !== '') {
      payload.brevo_api_key = brevoApiKey.value
    }

    if (brevoSmsApiKey.value !== '') {
      payload.brevo_sms_api_key = brevoSmsApiKey.value
    }

    const response = await api.post('settings', {
      settings: payload
    })

    if (response.data.success) {
      saveSuccess.value = 'SMTP settings saved successfully.'
      brevoConfigured.value = brevoApiKey.value !== '' || response.data.settings?.brevo_api_key === 'SAVED'

      setTimeout(() => {
        saveSuccess.value = ''
      }, 3000)
    } else {
      saveError.value = response.data.message || 'Failed to save settings.'
    }
  } catch (err) {
    saveError.value = err.message || 'Failed to save settings.'
  } finally {
    saving.value = false
  }
}

const sendTestEmail = async () => {
  sendingTest.value = true
  testSuccess.value = ''
  testError.value = ''

  try {
    const response = await api.post('settings/test-email', {
      to_email: testEmailAddress.value
    })

    if (response.data.success) {
      const providerName = response.data.provider ? ` via ${response.data.provider}` : ''
      testSuccess.value = `Test email sent${providerName}.`

      setTimeout(() => {
        testSuccess.value = ''
      }, 5000)
    } else {
      testError.value = response.data.message || 'Failed to send test email.'
    }
  } catch (err) {
    testError.value = err.message || 'Failed to send test email. Check your SMTP settings.'
  } finally {
    sendingTest.value = false
  }
}

onMounted(() => {
  loadSettings()
})
</script>
