import { ref } from 'vue'

// Shared state — module singleton, persists across component remounts
const activeBookingId = ref(null)
const loading = ref(false)
const booking = ref(null)
let originalFetch = null
let observer = null
let lastDialogPresent = false

function getDialogPresent() {
    return Boolean(document.querySelector('div[role="dialog"][aria-labelledby="booking-view-modal-title"]'))
}

async function loadMeetingInfo(bookingId) {
    console.log('[bookit] loadMeetingInfo called:', bookingId)
    if (!Number.isInteger(bookingId) || bookingId <= 0) return
    const enabled = Boolean(window?.bookitMeetings?.meetings_enabled)
    const platform = String(window?.bookitMeetings?.meetings_platform ?? '')
    console.log('[bookit] enabled:', enabled, 'platform:', platform)
    if (!enabled || !['whatsapp', 'teams', 'generic'].includes(platform)) return

    loading.value = true
    try {
        const base = String(window?.BOOKIT_DASHBOARD?.restBase ?? '')
        const nonce = String(window?.BOOKIT_DASHBOARD?.nonce ?? '')
        if (!base || !nonce) return

        const res = await originalFetch(`${base}bookings/${bookingId}`, {
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
    if (typeof window.fetch !== 'function' || originalFetch) return
    const nativeFetch = window.__bookitOriginalFetch ?? window.fetch.bind(window)
    window.__bookitOriginalFetch = nativeFetch
    originalFetch = nativeFetch

    window.fetch = async (...args) => {
        try {
            const raw = args?.[0]
            const url = typeof raw === 'string' ? raw : (raw?.url ?? '')
            const match = String(url).match(/\/dashboard\/bookings\/(\d+)$/)
            console.log('[bookit] fetch intercepted:', url, 'match:', match)
            if (match) {
                const id = parseInt(match[1], 10)
                if (Number.isInteger(id) && id > 0) {
                    console.log('[bookit] setting activeBookingId:', id)
                    activeBookingId.value = id
                    await loadMeetingInfo(id)
                }
            }
        } catch { /* ignore */ }
        return originalFetch(...args)
    }
}

function startModalObserver() {
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

function stopModalObserver() {
    if (observer) { observer.disconnect(); observer = null }
}

function unpatchFetch() {
    // Intentionally empty — fetch intercept must persist across client-side
    // navigation. Full page reloads reset window.fetch naturally.
}

export function useBookingDetector() {
    return {
        activeBookingId,
        loading,
        booking,
        patchFetchOnce,
        startModalObserver,
        stopModalObserver,
        unpatchFetch,
    }
}
