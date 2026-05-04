<template>
  <!-- Skip to main content (accessibility) -->
  <a
    href="#main-content"
    class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-[60] focus:px-4 focus:py-2 focus:bg-primary-600 focus:text-white focus:rounded-lg focus:shadow-lg"
  >
    Skip to main content
  </a>

  <div class="min-h-screen bg-gray-50">
    <!-- Mobile Header (visible only on mobile) -->
    <div class="lg:hidden fixed top-0 left-0 right-0 bg-white border-b border-gray-200 px-4 py-3 z-30 flex items-center justify-between">
      <button
        @click="sidebarOpen = !sidebarOpen"
        class="p-2 rounded-lg hover:bg-gray-100"
        aria-label="Toggle navigation menu"
        :aria-expanded="sidebarOpen"
      >
        <svg aria-hidden="true" class="w-6 h-6 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
        </svg>
      </button>

      <div class="flex items-center gap-2">
        <img
          v-if="branding.logoUrl"
          :src="branding.logoUrl"
          :alt="brandingAltText"
          class="h-8 w-8 rounded object-cover border border-gray-200"
        />
        <span v-else class="text-lg">📅</span>
        <span class="text-lg font-semibold text-gray-900">{{ brandingDisplayName }}</span>
      </div>

      <!-- User Dropdown (mobile) -->
      <div ref="mobileDropdownRef" class="relative">
        <button
          @click.stop="showUserMenu = !showUserMenu"
          class="p-1.5 rounded-lg hover:bg-gray-100"
          aria-label="User menu"
          :aria-expanded="showUserMenu"
          aria-haspopup="true"
        >
          <div
            class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-semibold"
            :style="{ backgroundColor: getUserColor(staff.name) }"
          >
            {{ getUserInitials(staff.name) }}
          </div>
        </button>

        <Transition name="fade">
          <div
            v-show="showUserMenu"
            @click.stop
            class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-50"
          >
            <div class="px-4 py-2 border-b border-gray-100">
              <p class="text-sm font-medium text-gray-900 truncate">{{ staff.name }}</p>
              <p class="text-xs text-gray-500 capitalize">{{ staff.role }}</p>
            </div>

            <router-link
              to="/profile"
              @click="showUserMenu = false"
              class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
            >
              <span>👤</span>
              <span>My Profile</span>
            </router-link>

            <div class="border-t border-gray-100 my-1"></div>

            <button
              @click="handleLogout"
              class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50"
            >
              <span>🚪</span>
              <span>Logout</span>
            </button>
          </div>
        </Transition>
      </div>
    </div>

    <!-- Sidebar Overlay (mobile only - click to close) -->
    <div
      v-if="sidebarOpen"
      @click="sidebarOpen = false"
      class="fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden transition-opacity"
      aria-hidden="true"
    ></div>

    <!-- Sidebar -->
    <aside
      class="fixed top-0 left-0 h-full w-64 bg-white border-r border-gray-200 transition-transform duration-300 ease-in-out z-50"
      :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
      role="navigation"
      aria-label="Main navigation"
    >
      <Sidebar
        :staff="staff"
        :branding="branding"
        @close="sidebarOpen = false"
        @open-setup-guide="showSetupGuide = true"
      />
    </aside>

    <!-- Main Content Area -->
    <main id="main-content" class="lg:ml-64 transition-all duration-300">
      <!-- Mobile header spacer (push content down on mobile) -->
      <div class="h-16 lg:h-0"></div>

      <!-- Desktop Header (hidden on mobile) -->
      <header class="hidden lg:block sticky top-0 bg-white border-b border-gray-200 px-6 py-4 z-20">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-4">
            <div class="flex items-center gap-2">
              <img
                v-if="branding.logoUrl"
                :src="branding.logoUrl"
                :alt="brandingAltText"
                class="h-9 w-9 rounded object-cover border border-gray-200"
              />
              <span v-else class="text-xl">📅</span>
              <span class="text-base font-semibold text-gray-900">{{ brandingDisplayName }}</span>
            </div>
            <div class="h-6 w-px bg-gray-200"></div>
            <h1 class="text-2xl font-semibold text-gray-900">
              {{ pageTitle }}
            </h1>
          </div>

          <!-- User Dropdown -->
          <div ref="userDropdownRef" class="relative ml-auto">
            <button
              @click.stop="showUserMenu = !showUserMenu"
              class="flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
              aria-label="User menu"
              :aria-expanded="showUserMenu"
              aria-haspopup="true"
            >
              <div
                class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-semibold"
                :style="{ backgroundColor: getUserColor(staff.name) }"
              >
                {{ getUserInitials(staff.name) }}
              </div>
              <span>{{ staff.name }}</span>
              <svg
                aria-hidden="true"
                class="w-4 h-4 transition-transform"
                :class="{ 'rotate-180': showUserMenu }"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>

            <!-- Dropdown Menu -->
            <Transition name="fade">
              <div
                v-show="showUserMenu"
                @click.stop
                class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-50"
              >
                <div class="px-4 py-2 border-b border-gray-100">
                  <p class="text-sm font-medium text-gray-900 truncate">{{ staff.name }}</p>
                  <p class="text-xs text-gray-500 capitalize">{{ staff.role }}</p>
                </div>

                <router-link
                  to="/profile"
                  @click="showUserMenu = false"
                  class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                >
                  <span>👤</span>
                  <span>My Profile</span>
                </router-link>

                <div class="border-t border-gray-100 my-1"></div>

                <button
                  @click="handleLogout"
                  class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50"
                >
                  <span>🚪</span>
                  <span>Logout</span>
                </button>
              </div>
            </Transition>
          </div>
        </div>
      </header>

      <!-- Page Content -->
      <div class="p-4 lg:p-6">
        <router-view />
      </div>
    </main>

    <!-- Toast Notifications -->
    <ToastContainer />

    <SetupGuideOverlay
      v-if="showSetupGuide"
      @close="showSetupGuide = false"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, provide, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Sidebar from './components/Sidebar.vue'
import ToastContainer from './components/ToastContainer.vue'
import SetupGuideOverlay from './components/SetupGuideOverlay.vue'
import { useApi } from './composables/useApi'
import { useSetupGuide } from './composables/useSetupGuide'
import { applyBranding, normalizeBranding } from './utils/branding'

const api = useApi()
const staff = window.BOOKIT_DASHBOARD.staff
const branding = ref(normalizeBranding(window.BOOKIT_DASHBOARD?.branding || {}))
const {
  setupGuideStatus,
  currentStep,
  stepsCompleted,
  isLoading: isSetupGuideLoading,
  showGuide,
  fetchStatus,
  markComplete,
  dismiss,
  updateStep
} = useSetupGuide()

provide('setupGuideState', {
  setupGuideStatus,
  currentStep,
  stepsCompleted,
  isSetupGuideLoading,
  showGuide,
  fetchStatus,
  markComplete,
  dismiss,
  updateStep
})

const route = useRoute()
const router = useRouter()
const pageTitle = computed(() => route.meta.title || 'Dashboard')
const brandingDisplayName = computed(() => branding.value.businessName || 'Bookit')
const brandingAltText = computed(() => branding.value.businessName || 'Dashboard')

const sidebarOpen = ref(false)
const showUserMenu = ref(false)
const userDropdownRef = ref(null)
const mobileDropdownRef = ref(null)
const showSetupGuide = ref(false)

router.afterEach(() => {
  sidebarOpen.value = false
  showUserMenu.value = false
})

const onDocumentClick = (event) => {
  if (showUserMenu.value) {
    const inDesktop = userDropdownRef.value?.contains(event.target)
    const inMobile = mobileDropdownRef.value?.contains(event.target)
    if (!inDesktop && !inMobile) {
      showUserMenu.value = false
    }
  }
}

const onBrandingUpdated = (event) => {
  const updatedBranding = normalizeBranding(event?.detail || {})
  branding.value = applyBranding(updatedBranding)
  window.BOOKIT_DASHBOARD.branding = { ...branding.value }
}

onMounted(() => {
  branding.value = applyBranding(branding.value)
  window.BOOKIT_DASHBOARD.branding = { ...branding.value }
  document.addEventListener('click', onDocumentClick)
  window.addEventListener('bookit:branding-updated', onBrandingUpdated)

  const role = window.BOOKIT_DASHBOARD?.staff?.role
  const isAdminRole = role === 'admin' || role === 'bookit_admin'

  if (isAdminRole) {
    void fetchStatus().then(() => {
      if (showGuide.value) {
        showSetupGuide.value = true
      }
    })
  }
})

watch(showGuide, (shouldShow) => {
  if (shouldShow) {
    showSetupGuide.value = true
  }
})

onUnmounted(() => {
  document.removeEventListener('click', onDocumentClick)
  window.removeEventListener('bookit:branding-updated', onBrandingUpdated)
})

const getUserInitials = (fullName) => {
  if (!fullName || fullName.trim() === '') return '??'
  const names = fullName.trim().split(' ').filter(n => n)
  if (names.length === 0) return '??'
  if (names.length === 1) return names[0].substring(0, 2).toUpperCase()
  return (names[0][0] + names[names.length - 1][0]).toUpperCase()
}

const getUserColor = (name) => {
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

const handleLogout = async () => {
  showUserMenu.value = false
  try {
    await api.post('logout')
  } catch {
    // Proceed with redirect even if API call fails.
  }
  window.location.href = window.BOOKIT_DASHBOARD.logoutUrl
}

</script>
