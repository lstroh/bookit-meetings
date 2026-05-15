<script setup>
import { computed, onMounted, onUnmounted } from 'vue'
import MeetingInfoPanel from '../components/MeetingInfoPanel.vue'
import { useBookingDetector } from '../composables/useBookingDetector.js'

const {
    activeBookingId,
    loading,
    booking,
    patchFetchOnce,
    startModalObserver,
    stopModalObserver,
    unpatchFetch,
} = useBookingDetector()

const meetingsEnabled = computed(() => Boolean(window?.bookitMeetings?.meetings_enabled))
const meetingsPlatform = computed(() => String(window?.bookitMeetings?.meetings_platform ?? ''))
const supportedPlatform = computed(() => ['whatsapp', 'teams', 'generic'].includes(meetingsPlatform.value))

const shouldRender = computed(() =>
    meetingsEnabled.value &&
    supportedPlatform.value &&
    Number.isInteger(activeBookingId.value) &&
    activeBookingId.value > 0
)

onMounted(() => {
    console.log('BookingDetailView MOUNTED on:', window.location.pathname, window.location.hash)
    patchFetchOnce()
    startModalObserver()
})

onUnmounted(() => {
    console.warn('BookingDetailView UNMOUNTED — stack:', new Error().stack)
    unpatchFetch()
    stopModalObserver()
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
