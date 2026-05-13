<script>
// Module-level state — persists across component remounts
import { ref } from 'vue'

const activeBookingId = ref(null)
const loading = ref(false)
const booking = ref(null)

let originalFetch = null
let observer = null
let lastDialogPresent = false

export { activeBookingId, loading, booking }
</script>

<script setup>
import { computed, onMounted, onUnmounted } from 'vue'
import MeetingInfoPanel from '../components/MeetingInfoPanel.vue'

const meetingsEnabled = computed(() => Boolean(window?.bookitMeetings?.meetings_enabled))
const meetingsPlatform = computed(() => String(window?.bookitMeetings?.meetings_platform ?? ''))
const supportedPlatform = computed(() => ['whatsapp', 'teams', 'generic'].includes(meetingsPlatform.value))

const shouldRender = computed(() =>
    meetingsEnabled.value &&
    supportedPlatform.value &&
    Number.isInteger(activeBookingId.value) &&
    activeBookingId.value > 0
)

function getDialogPresent() {
    return Boolean(document.querySelector('div[role="dialog"][aria-labelledby="booking-view-modal-title"]'))
}

async function loadMeetingInfo(bookingId) {
    if (!Number.isInteger(bookingId) || bookingId <= 0) return
    if (!meetingsEnabled.value || !supportedPlatform.value) return

    loading.value = true
    try {
        const base = String(window?.BOOKIT_DASHBOARD?.restBase ?? '')
        const nonce = String(window?.BOOKIT_DASHBOARD?.nonce ?? '')
        if (!base || !nonce) return

        const url = `${base}bookings/${bookingId}`
        const res = await originalFetch(url, {
            method: 'GET',
            headers: { 'X-WP-Nonce': nonce },
        })
        if (!res.ok) { booking.value = null; return }
        const json = await res.json()
        booking.value = json?.booking ?? json?.data ?? json
    } catch {
        booking.value = null
    } finally {
        loading.value = false
    }
}

function patchFetchOnce() {
    if (typeof window.fetch !== 'function') return
    if (originalFetch) return

    const nativeFetch = window.__bookitOriginalFetch ?? window.fetch.bind(window)
    window.__bookitOriginalFetch = nativeFetch
    originalFetch = nativeFetch

    window.fetch = async (...args) => {
        try {
            const raw = args?.[0]
            const url = typeof raw === 'string' ? raw : (raw?.url ?? '')
            const match = String(url).match(/\/dashboard\/bookings\/(\d+)$/)
            if (match) {
                const id = parseInt(match[1], 10)
                if (Number.isInteger(id) && id > 0) {
                    activeBookingId.value = id
                    await loadMeetingInfo(id)
                }
            }
        } catch { /* ignore */ }
        return originalFetch(...args)
    }
}

function startModalCloseObserver() {
    lastDialogPresent = getDialogPresent()
    observer = new MutationObserver(() => {
        const present = getDialogPresent()
        if (lastDialogPresent && !present) {
            activeBookingId.value = null
            booking.value = null
        }
        lastDialogPresent = present
    })
    observer.observe(document.body, { childList: true, subtree: true })
}

onMounted(() => {
    patchFetchOnce()
    startModalCloseObserver()
})

onUnmounted(() => {
    if (originalFetch) {
        window.fetch = originalFetch
        originalFetch = null
    }
    delete window.__bookitOriginalFetch
    if (observer) {
        observer.disconnect()
        observer = null
    }
})
</script>

<template>
    <div v-if="shouldRender" class="bm-booking-detail">
        <div v-if="loading" class="bm-booking-detail__state">Loading meeting info…</div>
        <MeetingInfoPanel v-else :booking="booking || {}" />
    </div>
</template>

<style scoped>
.bm-booking-detail {
    margin-top: 16px;
}
.bm-booking-detail__state {
    padding: 12px 14px;
    border: 1px solid var(--bookit-border-color);
    border-radius: 12px;
    background: var(--bookit-bg-card);
    color: var(--bookit-text-secondary);
}
</style>
